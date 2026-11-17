# Testing

> Part of the `laravel-service-pattern` skill. The rules (ARC/SEC/CODE/QUE/CFG) live in `SKILL.md`; the templates below are the canonical shape of each file. Architecture tests are in `arch-tests.md`; gateway (external API) tests are in `external-api-gateway.md`.

Default is **Pest**. If the project only has PHPUnit, write PHPUnit tests in the same structure and propose adding Pest for the arch tests.

**`phpunit.xml`** — register the arch directory, or `composer check` stays green without ever running the arch tests:
```xml
<testsuites>
    <testsuite name="Arch">
        <directory>tests/Arch</directory>
    </testsuite>
    <testsuite name="Unit">
        <directory>tests/Unit</directory>
    </testsuite>
    <testsuite name="Feature">
        <directory>tests/Feature</directory>
    </testsuite>
</testsuites>
```

**`tests/Pest.php`**
```php
<?php

declare(strict_types=1);

pest()->extend(Tests\TestCase::class)
    ->use(Illuminate\Foundation\Testing\RefreshDatabase::class)
    ->in('Feature');

pest()->extend(Tests\TestCase::class)
    ->in('Unit/Services', 'Unit/Actions', 'Unit/Jobs');
```

## Service unit test
```php
<?php

declare(strict_types=1);

use App\Actions\Order\CalculateOrderTotalAction;
use App\Enums\OrderStatus;
use App\Exceptions\Order\OrderCannotBeCancelledException;
use App\Models\Order;
use App\Repositories\Contracts\OrderRepositoryInterface;
use App\Services\Contracts\ProductServiceInterface;
use App\Services\OrderService;
use Illuminate\Database\ConnectionInterface;

beforeEach(function (): void {
    $this->orderRepository = Mockery::mock(OrderRepositoryInterface::class);
    $this->productService = Mockery::mock(ProductServiceInterface::class);
    $this->database = Mockery::mock(ConnectionInterface::class);
    $this->database->shouldReceive('transaction')->andReturnUsing(fn (Closure $callback): mixed => $callback());

    $this->orderService = new OrderService(
        $this->orderRepository,
        new CalculateOrderTotalAction($this->productService),
        $this->database,
    );
});

it('cancels a pending order', function (): void {
    $order = (new Order)->forceFill(['status' => OrderStatus::Pending]);

    $this->orderRepository
        ->shouldReceive('update')
        ->once()
        ->with($order, ['status' => OrderStatus::Cancelled])
        // andReturnUsing, not andReturn: andReturn would mutate $order while the mock is set up, before cancel() runs.
        ->andReturnUsing(fn (Order $order, array $attributes): Order => $order->forceFill($attributes));

    expect($this->orderService->cancel($order)->status)->toBe(OrderStatus::Cancelled);
});

it('refuses to cancel a shipped order', function (): void {
    $order = (new Order)->forceFill(['status' => OrderStatus::Shipped]);

    $this->orderRepository->shouldNotReceive('update');

    expect(fn () => $this->orderService->cancel($order))
        ->toThrow(OrderCannotBeCancelledException::class);
});
```

## API feature test
A complete file — every test file follows this shape: `<?php`, `declare(strict_types=1);`, imports, shared fixtures in `beforeEach`.
```php
<?php

declare(strict_types=1);

use App\Enums\OrderStatus;
use App\Enums\QueueName;
use App\Events\OrderCreated;
use App\Jobs\Order\SendOrderInvoiceJob;
use App\Models\Customer;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Queue;

beforeEach(function (): void {
    $this->user = User::factory()->create();
    // Fixtures belong to the acting user, or the policy (correctly) answers 403.
    $this->customer = Customer::factory()->for($this->user)->create();
    $this->product = Product::factory()->create(['price' => 1500]);
});

it('creates an order and queues the invoice', function (): void {
    Event::fake([OrderCreated::class]);
    Queue::fake();

    $this->actingAs($this->user)
        ->postJson(route('api.v1.orders.store'), [
            'customer_id' => $this->customer->id,
            'items' => [['product_id' => $this->product->id, 'quantity' => 2]],
        ])
        ->assertCreated()
        ->assertJsonPath('data.status', OrderStatus::Pending->value);

    $this->assertDatabaseHas('orders', ['customer_id' => $this->customer->id]);
    Event::assertDispatched(OrderCreated::class);
    Queue::assertPushedOn(QueueName::Notifications->value, SendOrderInvoiceJob::class);
});

it('rejects invalid payloads', function (array $payload, string $invalidField): void {
    $this->actingAs($this->user)
        ->postJson(route('api.v1.orders.store'), $payload)
        ->assertUnprocessable()
        ->assertJsonValidationErrors($invalidField);
})->with([
    'missing customer' => [['items' => [['product_id' => 1, 'quantity' => 1]]], 'customer_id'],
    'empty items' => [['customer_id' => 1, 'items' => []], 'items'],
]);

it('returns 422 when cancelling a shipped order', function (): void {
    $order = Order::factory()->for($this->customer)->shipped()->create();

    $this->actingAs($this->user)
        ->postJson(route('api.v1.orders.cancel', $order))
        ->assertUnprocessable()
        ->assertJsonStructure(['message']);
});
```

## Security feature tests (SEC-1, SEC-2)
Every resource endpoint gets a test proving another user is refused. A complete file:
```php
<?php

declare(strict_types=1);

use App\Models\Customer;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;

beforeEach(function (): void {
    $this->user = User::factory()->create();
    $this->product = Product::factory()->create(['price' => 1500]);
    $this->foreignOrder = Order::factory()->create(); // owned by another user's customer
});

it('does not show another user\'s order', function (): void {
    $this->actingAs($this->user)
        ->getJson(route('api.v1.orders.show', $this->foreignOrder))
        ->assertForbidden();
});

it('does not render another user\'s order page', function (): void {
    $this->actingAs($this->user)
        ->get(route('orders.show', $this->foreignOrder))
        ->assertForbidden();
});

it('does not cancel another user\'s order', function (): void {
    $this->actingAs($this->user)
        ->postJson(route('api.v1.orders.cancel', $this->foreignOrder))
        ->assertForbidden();
});

it('refuses to create an order for another user\'s customer', function (): void {
    $foreignCustomer = Customer::factory()->create();

    $this->actingAs($this->user)
        ->postJson(route('api.v1.orders.store'), [
            'customer_id' => $foreignCustomer->id,
            'items' => [['product_id' => $this->product->id, 'quantity' => 1]],
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('customer_id');

    $this->assertDatabaseMissing('orders', ['customer_id' => $foreignCustomer->id]);
});
```

## Web feature test (Blade)
The remaining snippets show only the test bodies. In the real file, add the header, imports and the same `beforeEach` as above.
```php
it('creates an order from the form', function (): void {
    $this->actingAs($this->user)
        ->post(route('orders.store'), [
            'customer_id' => $this->customer->id,
            'items' => [['product_id' => $this->product->id, 'quantity' => 2]],
        ])
        ->assertRedirect()
        ->assertSessionHasNoErrors()
        ->assertSessionHas('success');
});

it('shows validation errors on the form', function (): void {
    $this->actingAs($this->user)
        ->from(route('orders.create'))
        ->post(route('orders.store'), [])
        ->assertRedirect(route('orders.create'))
        ->assertSessionHasErrors(['customer_id', 'items']);
});

it('flashes the business error when cancelling a shipped order', function (): void {
    $order = Order::factory()->for($this->customer)->shipped()->create();

    $this->actingAs($this->user)
        ->from(route('orders.show', $order))
        ->post(route('orders.cancel', $order))
        ->assertRedirect(route('orders.show', $order))
        ->assertSessionHas('error');
});

it('renders the order page with eager-loaded data', function (): void {
    $order = Order::factory()->for($this->customer)->hasItems(2)->create();

    $this->actingAs($this->user)
        ->get(route('orders.show', $order))
        ->assertOk()
        ->assertViewIs('orders.show')
        ->assertViewHas('order', fn (Order $viewOrder): bool => $viewOrder->relationLoaded('items'));
});
```

## Web feature test (Inertia)
```php
use Inertia\Testing\AssertableInertia as Assert;

it('renders the order page', function (): void {
    $order = Order::factory()->for($this->customer)->hasItems(2)->create();

    $this->actingAs($this->user)
        ->get(route('orders.show', $order))
        ->assertOk()
        ->assertInertia(fn (Assert $page): Assert => $page
            ->component('Orders/Show')
            ->where('order.id', $order->id)
            ->has('order.items', 2)
            ->missing('order.deleted_at'));
});
```
Redirect/flash/validation tests are the same as the Blade ones.

## Jobs, actions & commands
```php
// tests/Unit/Jobs/SendOrderInvoiceJobTest.php
it('delegates to the order service', function (): void {
    $orderService = Mockery::mock(OrderServiceInterface::class);
    $orderService->shouldReceive('sendInvoice')->once()->with(42);

    (new SendOrderInvoiceJob(42))->handle($orderService);
});

it('is configured for retries on the notifications queue', function (): void {
    expect(new SendOrderInvoiceJob(42))
        ->tries->toBe(3)
        ->timeout->toBe(60)
        ->queue->toBe(QueueName::Notifications->value);
});
```

```php
// tests/Feature/Services/SendOrderInvoiceTest.php — idempotency (QUE-2)
it('sends the invoice only once even if the job runs twice', function (): void {
    Notification::fake();
    $order = Order::factory()->create();

    resolve(OrderServiceInterface::class)->sendInvoice($order->id);
    resolve(OrderServiceInterface::class)->sendInvoice($order->id);

    Notification::assertSentTimes(OrderInvoiceNotification::class, 1);
});
```

```php
// tests/Unit/Actions/CalculateOrderTotalActionTest.php
it('calculates the order total in cents', function (): void {
    $productService = Mockery::mock(ProductServiceInterface::class);
    $productService->shouldReceive('getUnitPricesByIds')->with([1, 2])->andReturn([1 => 1500, 2 => 250]);

    $total = (new CalculateOrderTotalAction($productService))->handle([
        new OrderItemDTO(productId: 1, quantity: 2),
        new OrderItemDTO(productId: 2, quantity: 4),
    ]);

    expect($total)->toBe(4000);
});
```

```php
// tests/Feature/Console/CancelStaleOrdersCommandTest.php
it('cancels only stale pending orders', function (): void {
    $staleOrder = Order::factory()->pending()->create(['created_at' => now()->subHours(72)]);
    $freshOrder = Order::factory()->pending()->create();

    $this->artisan('orders:cancel-stale', ['--hours' => 48])->assertSuccessful();

    expect($staleOrder->refresh()->status)->toBe(OrderStatus::Cancelled)
        ->and($freshOrder->refresh()->status)->toBe(OrderStatus::Pending);
});
```

## Test rules
- Names describe behavior in plain English.
- Unit tests mock **interfaces**, never Eloquent classes or final Actions.
- Feature tests use factories (with states like `shipped()`, `pending()`) and `route()` names. Records the acting user works on are created `->for()` that user's records; otherwise the policy answers 403 and the test checks the wrong thing.
- Every endpoint has a test proving another user gets 403 (SEC-1), and every input ID has a test proving another user's ID is rejected (SEC-2).
- Datasets for validation matrices.
- Every `BusinessException` has a feature test per delivery mode (422 JSON for API, redirect + `error` flash for web).
- Every job has a unit test (delegates to the service + retry/queue config); every job-backed service method has an idempotency test.
- Every Action has its own unit test; every command has a feature test via `$this->artisan()`.
- Feature tests that trigger jobs use `Queue::fake()` and assert the queue name.
- Gateway-based features use `Http::fake()`. Every SEC-2 check has a test proving the elevated call is **not** sent (`Http::assertNotSent`); every SEC-3 bound has a validation dataset.
