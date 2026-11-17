# Shared layers (Eloquent-backed domains)

> Part of the `laravel-service-pattern` skill. The rules (ARC/SEC/CODE/QUE/CFG) live in `SKILL.md`; the templates below are the canonical shape of each file. Identical for every delivery mode (API, Blade, Inertia, Livewire, jobs, commands).

## DTO
```php
<?php

declare(strict_types=1);

namespace App\DTOs\Order;

final readonly class CreateOrderDTO
{
    /**
     * @param  list<OrderItemDTO>  $items
     */
    public function __construct(
        public int $customerId,
        public array $items,
        public ?string $notes = null,
    ) {}

    /**
     * @param  array{customer_id: int|string, items: list<array{product_id: int|string, quantity: int|string}>, notes?: string|null}  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            customerId: (int) $data['customer_id'],
            items: array_map(OrderItemDTO::fromArray(...), $data['items']),
            notes: $data['notes'] ?? null,
        );
    }
}
```

```php
<?php

declare(strict_types=1);

namespace App\DTOs\Order;

final readonly class OrderItemDTO
{
    public function __construct(
        public int $productId,
        public int $quantity,
    ) {}

    /**
     * @param  array{product_id: int|string, quantity: int|string}  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            productId: (int) $data['product_id'],
            quantity: (int) $data['quantity'],
        );
    }

    /**
     * @return array{product_id: int, quantity: int}
     */
    public function toArray(): array
    {
        return [
            'product_id' => $this->productId,
            'quantity' => $this->quantity,
        ];
    }
}
```

## FormRequest (shared by API and web)
```php
<?php

declare(strict_types=1);

namespace App\Http\Requests\Order;

use App\DTOs\Order\CreateOrderDTO;
use App\Models\Order;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class StoreOrderRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', Order::class) ?? false;
    }

    /**
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        return [
            // SEC-2: a bare exists:customers,id would accept any customer, including other users' ones.
            'customer_id' => ['required', 'integer', Rule::exists('customers', 'id')->where('user_id', $this->user()?->id)],
            'items' => ['required', 'array', 'min:1'],
            'items.*.product_id' => ['required', 'integer', 'exists:products,id'],
            'items.*.quantity' => ['required', 'integer', 'min:1'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ];
    }

    public function toDto(): CreateOrderDTO
    {
        /** @var array{customer_id: int|string, items: list<array{product_id: int|string, quantity: int|string}>, notes?: string|null} $validated */
        $validated = $this->validated();

        return CreateOrderDTO::fromArray($validated);
    }
}
```

## Model, migration, factory
Models are **not** `final` (factories, Larastan and packages extend them). Every column and relation is documented with `@property` / `@property-read`, so Larastan level 9 knows a `BelongsTo` that is always set is not nullable.
```php
<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\OrderStatus;
use Carbon\CarbonImmutable;
use Database\Factories\OrderFactory;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property int $customer_id
 * @property OrderStatus $status
 * @property int $total
 * @property string|null $notes
 * @property CarbonImmutable|null $invoice_sent_at
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 * @property-read Customer $customer
 * @property-read Collection<int, OrderItem> $items
 */
class Order extends Model
{
    /** @use HasFactory<OrderFactory> */
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = ['customer_id', 'status', 'total', 'notes', 'invoice_sent_at'];

    /**
     * The $casts property, not the casts() method: some Larastan setups don't read casts(),
     * and enum methods like $order->status->isCancellable() then fail analysis.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'status' => OrderStatus::class,
        'total' => 'integer',
        'invoice_sent_at' => 'immutable_datetime',
    ];

    /**
     * @return BelongsTo<Customer, $this>
     */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    /**
     * @return HasMany<OrderItem, $this>
     */
    public function items(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }
}
```
`Customer` uses the `Notifiable` trait (it receives `OrderInvoiceNotification`) and has a `user_id` column (used by the policy and by `StoreOrderRequest`); its factory sets `'user_id' => User::factory()`.

```php
<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('orders', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('customer_id')->constrained()->restrictOnDelete();
            $table->string('status', 20)->index();
            $table->unsignedBigInteger('total');
            $table->text('notes')->nullable();
            $table->timestamp('invoice_sent_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('orders');
    }
};
```

```php
<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\OrderStatus;
use App\Models\Customer;
use App\Models\Order;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Order>
 */
final class OrderFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'customer_id' => Customer::factory(),
            'status' => OrderStatus::Pending,
            'total' => fake()->numberBetween(100, 100_000),
            'notes' => null,
        ];
    }

    public function pending(): static
    {
        return $this->state(['status' => OrderStatus::Pending]);
    }

    public function shipped(): static
    {
        return $this->state(['status' => OrderStatus::Shipped]);
    }
}
```
Every state a test uses (`shipped()`, `pending()`, `hasItems()`) exists in the factory; `OrderItem`, `Customer` and `Product` get the same model + factory treatment.

## Repository contract + Eloquent implementation
```php
<?php

declare(strict_types=1);

namespace App\Repositories\Contracts;

use App\Models\Order;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\LazyCollection;

interface OrderRepositoryInterface
{
    public function findById(int $id): ?Order;

    public function findOrFail(int $id): Order;

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function create(array $attributes): Order;

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function update(Order $order, array $attributes): Order;

    /**
     * @param  list<array{product_id: int, quantity: int}>  $items
     */
    public function createItems(Order $order, array $items): void;

    public function loadDetails(Order $order): Order;

    /**
     * @return LengthAwarePaginator<int, Order>
     */
    public function paginateForCustomer(int $customerId, int $perPage = 15): LengthAwarePaginator;

    /**
     * @return LazyCollection<int, Order>
     */
    public function lazyPendingOlderThan(int $hours): LazyCollection;
}
```

```php
<?php

declare(strict_types=1);

namespace App\Repositories\Eloquent;

use App\Enums\OrderStatus;
use App\Models\Order;
use App\Repositories\Contracts\OrderRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\LazyCollection;

final class EloquentOrderRepository implements OrderRepositoryInterface
{
    public function findById(int $id): ?Order
    {
        return Order::query()->find($id);
    }

    public function findOrFail(int $id): Order
    {
        return Order::query()->findOrFail($id);
    }

    public function create(array $attributes): Order
    {
        return Order::query()->create($attributes);
    }

    public function update(Order $order, array $attributes): Order
    {
        $order->update($attributes);

        return $order;
    }

    public function createItems(Order $order, array $items): void
    {
        $order->items()->createMany($items);
    }

    public function loadDetails(Order $order): Order
    {
        return $order->load(['items.product', 'customer']);
    }

    public function paginateForCustomer(int $customerId, int $perPage = 15): LengthAwarePaginator
    {
        return Order::query()
            ->where('customer_id', $customerId)
            ->with('items')
            ->latest()
            ->paginate($perPage);
    }

    public function lazyPendingOlderThan(int $hours): LazyCollection
    {
        return Order::query()
            ->where('status', OrderStatus::Pending)
            ->where('created_at', '<', now()->subHours($hours))
            ->lazyById();
    }
}
```

## Service contract + implementation
```php
<?php

declare(strict_types=1);

namespace App\Services\Contracts;

use App\DTOs\Order\CreateOrderDTO;
use App\Models\Order;

interface OrderServiceInterface
{
    public function create(CreateOrderDTO $dto): Order;

    public function getDetails(Order $order): Order;

    public function cancel(Order $order): Order;

    public function sendInvoice(int $orderId): void;

    public function cancelStale(int $olderThanHours): int;
}
```

```php
<?php

declare(strict_types=1);

namespace App\Services;

use App\Actions\Order\CalculateOrderTotalAction;
use App\DTOs\Order\CreateOrderDTO;
use App\DTOs\Order\OrderItemDTO;
use App\Enums\OrderStatus;
use App\Events\OrderCreated;
use App\Exceptions\Order\OrderCannotBeCancelledException;
use App\Jobs\Order\SendOrderInvoiceJob;
use App\Models\Order;
use App\Notifications\OrderInvoiceNotification;
use App\Repositories\Contracts\OrderRepositoryInterface;
use App\Services\Contracts\OrderServiceInterface;
use Illuminate\Database\ConnectionInterface;

final readonly class OrderService implements OrderServiceInterface
{
    public function __construct(
        private OrderRepositoryInterface $orderRepository,
        private CalculateOrderTotalAction $calculateOrderTotal,
        private ConnectionInterface $database,
    ) {}

    public function create(CreateOrderDTO $dto): Order
    {
        /** @var Order $order ConnectionInterface::transaction() is untyped (returns mixed). */
        $order = $this->database->transaction(function () use ($dto): Order {
            $order = $this->orderRepository->create([
                'customer_id' => $dto->customerId,
                'status' => OrderStatus::Pending,
                'total' => $this->calculateOrderTotal->handle($dto->items),
                'notes' => $dto->notes,
            ]);

            $this->orderRepository->createItems(
                $order,
                array_map(static fn (OrderItemDTO $item): array => $item->toArray(), $dto->items),
            );

            event(new OrderCreated($order));
            dispatch(new SendOrderInvoiceJob($order->id))->afterCommit();

            return $this->orderRepository->loadDetails($order);
        });

        return $order;
    }

    public function getDetails(Order $order): Order
    {
        return $this->orderRepository->loadDetails($order);
    }

    public function cancel(Order $order): Order
    {
        if (! $order->status->isCancellable()) {
            throw OrderCannotBeCancelledException::forStatus($order->status);
        }

        return $this->orderRepository->update($order, [
            'status' => OrderStatus::Cancelled,
        ]);
    }

    public function sendInvoice(int $orderId): void
    {
        $order = $this->orderRepository->loadDetails($this->orderRepository->findOrFail($orderId));

        if ($order->invoice_sent_at !== null) {
            return;
        }

        $order->customer->notify(new OrderInvoiceNotification($order));

        $this->orderRepository->update($order, ['invoice_sent_at' => now()]);
    }

    public function cancelStale(int $olderThanHours): int
    {
        $cancelledCount = 0;

        foreach ($this->orderRepository->lazyPendingOlderThan($olderThanHours) as $order) {
            $this->cancel($order);
            $cancelledCount++;
        }

        return $cancelledCount;
    }
}
```

## Enums
```php
<?php

declare(strict_types=1);

namespace App\Enums;

enum OrderStatus: string
{
    case Pending = 'pending';
    case Paid = 'paid';
    case Shipped = 'shipped';
    case Cancelled = 'cancelled';

    public function isCancellable(): bool
    {
        return in_array($this, [self::Pending, self::Paid], true);
    }
}
```

```php
<?php

declare(strict_types=1);

namespace App\Enums;

enum QueueName: string
{
    case High = 'high';
    case Default = 'default';
    case Notifications = 'notifications';
    case Low = 'low';
}
```

## Exceptions
```php
<?php

declare(strict_types=1);

namespace App\Exceptions;

use RuntimeException;
use Symfony\Component\HttpFoundation\Response;

abstract class BusinessException extends RuntimeException
{
    public function status(): int
    {
        return Response::HTTP_UNPROCESSABLE_ENTITY;
    }
}
```

```php
<?php

declare(strict_types=1);

namespace App\Exceptions\Order;

use App\Enums\OrderStatus;
use App\Exceptions\BusinessException;

final class OrderCannotBeCancelledException extends BusinessException
{
    public static function forStatus(OrderStatus $status): self
    {
        return new self(__('orders.errors.cannot_cancel', ['status' => $status->value]));
    }
}
```

Rendered once, centrally, for **all** modes (Laravel 11+ `bootstrap/app.php`):
```php
->withExceptions(function (Exceptions $exceptions): void {
    $exceptions->render(function (BusinessException $exception, Request $request): SymfonyResponse {
        if ($request->expectsJson()) {
            return response()->json(['message' => $exception->getMessage()], $exception->status());
        }

        return back()->withInput()->with('error', $exception->getMessage());
    });
})
```
Inertia requests don't expect JSON, so they get the redirect + `error` flash automatically. A `BusinessException` thrown inside a job or command simply fails that job/command and is logged by `failed()`.

## Supporting classes referenced by the templates
Generated code never references a class, method, route, policy ability or translation key that doesn't exist. Create each one in the same task.

```php
<?php

declare(strict_types=1);

namespace App\Events;

use App\Models\Order;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Queue\SerializesModels;

final readonly class OrderCreated implements ShouldDispatchAfterCommit
{
    use SerializesModels;

    public function __construct(
        public Order $order,
    ) {}
}
```
Events have no mutable parent or trait state, so CODE-5 makes them `final readonly class` (works with `SerializesModels`).

```php
<?php

declare(strict_types=1);

namespace App\Http\Requests\Order;

use App\Models\Order;
use Illuminate\Foundation\Http\FormRequest;

final class CancelOrderRequest extends FormRequest
{
    public function authorize(): bool
    {
        $order = $this->route('order');

        return $order instanceof Order && ($this->user()?->can('cancel', $order) ?? false);
    }

    /**
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        return [];
    }
}
```

Every endpoint that takes a route-bound model gets its own FormRequest, even without input (SEC-1) — `show` included:
```php
<?php

declare(strict_types=1);

namespace App\Http\Requests\Order;

use App\Models\Order;
use Illuminate\Foundation\Http\FormRequest;

final class ShowOrderRequest extends FormRequest
{
    public function authorize(): bool
    {
        $order = $this->route('order');

        return $order instanceof Order && ($this->user()?->can('view', $order) ?? false);
    }

    /**
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        return [];
    }
}
```

```php
<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Order;
use App\Models\User;

final class OrderPolicy
{
    public function create(User $user): bool
    {
        return $user->hasVerifiedEmail();
    }

    public function view(User $user, Order $order): bool
    {
        return $order->customer->user_id === $user->id;
    }

    public function cancel(User $user, Order $order): bool
    {
        return $this->view($user, $order);
    }
}
```

```php
<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\OrderItem;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin OrderItem
 */
final class OrderItemResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'product_id' => $this->product_id,
            'quantity' => $this->quantity,
        ];
    }
}
```

```php
<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Models\Order;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

final class OrderInvoiceNotification extends Notification
{
    public function __construct(
        private readonly Order $order,
    ) {}

    /**
     * @return list<string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject(__('orders.invoice.subject', ['id' => $this->order->id]))
            ->line(__('orders.invoice.total', ['total' => number_format($this->order->total / 100, 2)]));
    }
}
```
The notification is sent synchronously because it already runs inside `SendOrderInvoiceJob`.

Contracts of other domains used by the templates:
```php
<?php

declare(strict_types=1);

namespace App\Services\Contracts;

interface ProductServiceInterface
{
    /**
     * @param  list<int>  $productIds
     * @return array<int, int> Unit price in cents, keyed by product ID
     */
    public function getUnitPricesByIds(array $productIds): array;
}
```

```php
<?php

declare(strict_types=1);

namespace App\Services\Contracts;

interface CustomerServiceInterface
{
    public function refreshStats(int $customerId): void;
}
```

Translation keys (`lang/en/orders.php`, plus every other locale the project ships, e.g. `lang/ar/orders.php`):
```php
<?php

declare(strict_types=1);

return [
    'created' => 'Order created.',
    'cancelled' => 'Order cancelled.',
    'errors' => [
        'cannot_cancel' => 'An order with status :status cannot be cancelled.',
    ],
    'invoice' => [
        'subject' => 'Invoice for order #:id',
        'total' => 'Total: :total',
    ],
];
```

## Bindings
```php
<?php

declare(strict_types=1);

namespace App\Providers;

use App\Repositories\Contracts\OrderRepositoryInterface;
use App\Repositories\Eloquent\EloquentOrderRepository;
use App\Services\Contracts\OrderServiceInterface;
use App\Services\OrderService;
use Illuminate\Support\ServiceProvider;

final class BindingServiceProvider extends ServiceProvider
{
    /**
     * @var array<class-string, class-string>
     */
    public array $bindings = [
        // Repositories
        OrderRepositoryInterface::class => EloquentOrderRepository::class,

        // Services
        OrderServiceInterface::class => OrderService::class,

        // Integrations
        // PaymentGatewayInterface::class => StripePaymentGateway::class,
    ];
}
```
Actions are concrete classes resolved by autowiring — no binding needed.

## AppServiceProvider (CFG-1)
```php
public function boot(): void
{
    Date::use(CarbonImmutable::class);

    // Only when the project uses a database:
    Model::shouldBeStrict(! $this->app->isProduction());
    DB::prohibitDestructiveCommands($this->app->isProduction());
}
```
