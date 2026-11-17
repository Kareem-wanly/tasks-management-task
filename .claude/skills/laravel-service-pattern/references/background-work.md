# Actions, jobs, listeners, commands & scheduling

> Part of the `laravel-service-pattern` skill. The rules (ARC/SEC/CODE/QUE/CFG) live in `SKILL.md`; the templates below are the canonical shape of each file. QUE-1…QUE-7 and ARC-12…ARC-14 apply to everything here.

## Actions (reusable single-purpose steps)

Create an Action **only** when a step is (a) reused by two or more services, (b) big enough (~30+ lines or its own dependencies) to hide the service's flow, or (c) worth testing on its own. Otherwise keep it as a private service method.

```php
<?php

declare(strict_types=1);

namespace App\Actions\Order;

use App\DTOs\Order\OrderItemDTO;
use App\Services\Contracts\ProductServiceInterface;

final readonly class CalculateOrderTotalAction
{
    public function __construct(
        private ProductServiceInterface $productService,
    ) {}

    /**
     * @param  list<OrderItemDTO>  $items
     * @return int Total in minor units (cents)
     */
    public function handle(array $items): int
    {
        $unitPrices = $this->productService->getUnitPricesByIds(
            array_map(static fn (OrderItemDTO $item): int => $item->productId, $items),
        );

        return array_sum(array_map(
            static fn (OrderItemDTO $item): int => $unitPrices[$item->productId] * $item->quantity,
            $items,
        ));
    }
}
```

Actions are `final`, so they are **not mocked**: service tests build the real Action with mocked interfaces, and each Action has its own unit test.

## Jobs & queues

```php
<?php

declare(strict_types=1);

namespace App\Jobs\Order;

use App\Enums\QueueName;
use App\Services\Contracts\OrderServiceInterface;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Support\Facades\Log;
use Throwable;

final class SendOrderInvoiceJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $timeout = 60;

    /**
     * @var list<int>
     */
    public array $backoff = [10, 60, 300];

    public function __construct(
        public readonly int $orderId,
    ) {
        $this->onQueue(QueueName::Notifications->value);
    }

    /**
     * @return list<object>
     */
    public function middleware(): array
    {
        return [(new WithoutOverlapping((string) $this->orderId))->dontRelease()];
    }

    public function handle(OrderServiceInterface $orderService): void
    {
        $orderService->sendInvoice($this->orderId);
    }

    public function failed(Throwable $exception): void
    {
        Log::error('Sending order invoice failed.', [
            'order_id' => $this->orderId,
            'exception' => $exception->getMessage(),
        ]);
    }
}
```

- Dispatched **only from the service**, with `->afterCommit()` when inside a transaction (see `OrderService::create()`).
- The service method it calls (`sendInvoice`) is idempotent: it checks `invoice_sent_at` before acting.
- Workers consume by priority: `php artisan queue:work --queue=high,default,notifications,low` (or matching Horizon supervisors).
- Third-party API jobs add `new RateLimited('provider-name')` or `new ThrottlesExceptions(...)` to `middleware()`.
- Multi-step workflows use `Bus::chain([...])` or `Bus::batch([...])` built inside a service method.

## Event listeners

Use a queued listener when **reacting** to a domain event; use a job when the service **explicitly schedules** work. Pick one per use case, never both.
```php
<?php

declare(strict_types=1);

namespace App\Listeners\Order;

use App\Enums\QueueName;
use App\Events\OrderCreated;
use App\Services\Contracts\CustomerServiceInterface;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Log;
use Throwable;

final class UpdateCustomerStatsListener implements ShouldQueue
{
    public int $tries = 3;

    public int $timeout = 60;

    /**
     * @var list<int>
     */
    public array $backoff = [10, 60, 300];

    public function __construct(
        private readonly CustomerServiceInterface $customerService,
    ) {}

    public function viaQueue(): string
    {
        return QueueName::Low->value;
    }

    public function handle(OrderCreated $event): void
    {
        $this->customerService->refreshStats($event->order->customer_id);
    }

    public function failed(OrderCreated $event, Throwable $exception): void
    {
        Log::error('Updating customer stats failed.', [
            'order_id' => $event->order->id,
            'exception' => $exception->getMessage(),
        ]);
    }
}
```

## Console commands & scheduling

```php
<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\Contracts\OrderServiceInterface;
use Illuminate\Console\Command;

final class CancelStaleOrdersCommand extends Command
{
    protected $signature = 'orders:cancel-stale {--hours=48 : Age in hours after which pending orders are cancelled}';

    protected $description = 'Cancel pending orders older than the given number of hours.';

    public function handle(OrderServiceInterface $orderService): int
    {
        $cancelledCount = $orderService->cancelStale((int) $this->option('hours'));

        $this->components->info("Cancelled {$cancelledCount} stale orders.");

        return self::SUCCESS;
    }
}
```

```php
// routes/console.php
Schedule::command(CancelStaleOrdersCommand::class)
    ->hourly()
    ->withoutOverlapping()
    ->onOneServer();
```
