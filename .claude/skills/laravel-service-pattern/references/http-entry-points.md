# HTTP entry points: API, Blade, Inertia, Livewire

> Part of the `laravel-service-pattern` skill. The rules (ARC/SEC/CODE/QUE/CFG) live in `SKILL.md`; the templates below are the canonical shape of each file. Pick the template for the delivery mode(s) detected in section 0 of `SKILL.md`.

## API controller (JSON)

```php
<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\Order;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Order
 */
final class OrderResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'customer_id' => $this->customer_id,
            'status' => $this->status->value,
            'total' => $this->total,
            'notes' => $this->notes,
            'items' => OrderItemResource::collection($this->whenLoaded('items')),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
```

```php
<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Order\CancelOrderRequest;
use App\Http\Requests\Order\ShowOrderRequest;
use App\Http\Requests\Order\StoreOrderRequest;
use App\Http\Resources\OrderResource;
use App\Models\Order;
use App\Services\Contracts\OrderServiceInterface;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

final class OrderController extends Controller
{
    public function __construct(
        private readonly OrderServiceInterface $orderService,
    ) {}

    public function store(StoreOrderRequest $request): JsonResponse
    {
        $order = $this->orderService->create($request->toDto());

        return OrderResource::make($order)
            ->response()
            ->setStatusCode(Response::HTTP_CREATED);
    }

    public function show(ShowOrderRequest $request, Order $order): OrderResource
    {
        return OrderResource::make($this->orderService->getDetails($order));
    }

    public function cancel(CancelOrderRequest $request, Order $order): OrderResource
    {
        return OrderResource::make($this->orderService->cancel($order));
    }
}
```

```php
// routes/api.php
Route::prefix('v1')->name('api.v1.')->middleware('auth:sanctum')->group(function (): void {
    Route::apiResource('orders', Api\V1\OrderController::class)->only(['store', 'show']);
    Route::post('orders/{order}/cancel', [Api\V1\OrderController::class, 'cancel'])->name('orders.cancel');
});
```

## Web controller — Blade

```php
<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Http\Requests\Order\CancelOrderRequest;
use App\Http\Requests\Order\ShowOrderRequest;
use App\Http\Requests\Order\StoreOrderRequest;
use App\Models\Order;
use App\Services\Contracts\OrderServiceInterface;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;

final class OrderController extends Controller
{
    public function __construct(
        private readonly OrderServiceInterface $orderService,
    ) {}

    public function create(): View
    {
        return view('orders.create');
    }

    public function store(StoreOrderRequest $request): RedirectResponse
    {
        $order = $this->orderService->create($request->toDto());

        return to_route('orders.show', $order)
            ->with('success', __('orders.created'));
    }

    public function show(ShowOrderRequest $request, Order $order): View
    {
        return view('orders.show', [
            'order' => $this->orderService->getDetails($order),
        ]);
    }

    public function cancel(CancelOrderRequest $request, Order $order): RedirectResponse
    {
        $this->orderService->cancel($order);

        return back()->with('success', __('orders.cancelled'));
    }
}
```

Blade rules: views only render; use `@error`, `old()`, and a shared flash partial (`components/flash.blade.php`) reading `session('success')` / `session('error')`.

## Web controller — Inertia

A project uses **either** the Blade or the Inertia template for `Web\{Domain}Controller`; both share the class name on purpose.

```php
<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Http\Requests\Order\CancelOrderRequest;
use App\Http\Requests\Order\ShowOrderRequest;
use App\Http\Requests\Order\StoreOrderRequest;
use App\Http\Resources\OrderResource;
use App\Models\Order;
use App\Services\Contracts\OrderServiceInterface;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

final class OrderController extends Controller
{
    public function __construct(
        private readonly OrderServiceInterface $orderService,
    ) {}

    public function create(): Response
    {
        return Inertia::render('Orders/Create');
    }

    public function store(StoreOrderRequest $request): RedirectResponse
    {
        $order = $this->orderService->create($request->toDto());

        return to_route('orders.show', $order)
            ->with('success', __('orders.created'));
    }

    public function show(ShowOrderRequest $request, Order $order): Response
    {
        return Inertia::render('Orders/Show', [
            'order' => OrderResource::make($this->orderService->getDetails($order)),
        ]);
    }

    public function cancel(CancelOrderRequest $request, Order $order): RedirectResponse
    {
        $this->orderService->cancel($order);

        return back()->with('success', __('orders.cancelled'));
    }
}
```

Share flash messages once in `HandleInertiaRequests::share()`:
```php
'flash' => [
    'success' => fn (): ?string => $request->session()->get('success'),
    'error' => fn (): ?string => $request->session()->get('error'),
],
```

```php
// routes/web.php (Blade or Inertia)
Route::middleware('auth')->group(function (): void {
    Route::resource('orders', Web\OrderController::class)->only(['create', 'store', 'show']);
    Route::post('orders/{order}/cancel', [Web\OrderController::class, 'cancel'])->name('orders.cancel');
});
```

## Livewire (if present)
A Livewire component is a controller: it injects the service interface in `boot()` or method parameters, validates through a Form object or rules mapped to a DTO, calls one service method, and never queries. ARC-1, ARC-3, ARC-5 apply unchanged.
