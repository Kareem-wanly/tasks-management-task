# External API as data source (Gateway, ARC-15)

> Part of the `laravel-service-pattern` skill. The rules (ARC/SEC/CODE/QUE/CFG) live in `SKILL.md`; the templates below are the canonical shape of each file. Read this instead of the repository part of `shared-layers.md` when the project has no database of its own.

Use this instead of Eloquent repositories when the project's data lives in an external system.

```php
<?php

declare(strict_types=1);

namespace App\DTOs\Bitrix;

final readonly class BitrixContextDTO
{
    public function __construct(
        public string $domain,
        public string $authToken,
        public int $userId,
    ) {}
}
```

```php
<?php

declare(strict_types=1);

namespace App\Http\Requests\Bitrix;

use App\DTOs\Bitrix\BitrixContextDTO;
use Illuminate\Foundation\Http\FormRequest;

abstract class BitrixRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->attributes->has('bitrix_user_id'); // set by the Bitrix auth middleware
    }

    public function context(): BitrixContextDTO
    {
        // Typed getters, not (string)/(int) casts on mixed — Larastan level 9 rejects those.
        return new BitrixContextDTO(
            domain: $this->attributes->getString('bitrix_domain'),
            authToken: (string) $this->header('X-Bitrix-Auth'),
            userId: $this->attributes->getInt('bitrix_user_id'),
        );
    }
}
```

The middleware that fills those attributes (alias it or reference it by class on the Bitrix route group):
```php
<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final class AuthenticateBitrixCaller
{
    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $domain = $request->header('X-Bitrix-Domain');
        $userId = $request->header('X-Bitrix-User-Id');

        // A real app must also verify the token (e.g. `user.current` through the gateway in a service)
        // before trusting these headers.
        if (is_string($domain) && is_string($userId) && ctype_digit($userId)) {
            $request->attributes->set('bitrix_domain', $domain);
            $request->attributes->set('bitrix_user_id', (int) $userId);
        }

        return $next($request);
    }
}
```

```php
<?php

declare(strict_types=1);

namespace App\Integrations\Bitrix\Contracts;

use App\DTOs\Bitrix\BitrixContextDTO;

interface BitrixGatewayInterface
{
    /**
     * Calls a REST method with the caller's own token (caller's permissions).
     *
     * @param  array<string, mixed>  $params
     * @return array<string, mixed>
     */
    public function callAsUser(BitrixContextDTO $context, string $method, array $params = []): array;

    /**
     * Calls a REST method through the portal webhook (elevated permissions). SEC-2 applies to every caller.
     *
     * @param  array<string, mixed>  $params
     * @return array<string, mixed>
     */
    public function callAsWebhook(BitrixContextDTO $context, string $method, array $params = []): array;
}
```

```php
<?php

declare(strict_types=1);

namespace App\Integrations\Bitrix;

use App\DTOs\Bitrix\BitrixContextDTO;
use App\Exceptions\Bitrix\BitrixRequestFailedException;
use App\Exceptions\Bitrix\BitrixUnavailableException;
use App\Exceptions\Bitrix\BitrixWebhookNotConfiguredException;
use App\Integrations\Bitrix\Contracts\BitrixGatewayInterface;
use Illuminate\Contracts\Config\Repository as Config;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Factory as Http;

final readonly class BitrixGateway implements BitrixGatewayInterface
{
    public function __construct(
        private Http $http,
        private Config $config,
    ) {}

    public function callAsUser(BitrixContextDTO $context, string $method, array $params = []): array
    {
        return $this->send("https://{$context->domain}/rest/{$method}", [...$params, 'auth' => $context->authToken]);
    }

    public function callAsWebhook(BitrixContextDTO $context, string $method, array $params = []): array
    {
        $webhookUrl = $this->config->get("services.bitrix.webhooks.{$context->domain}");

        if (! is_string($webhookUrl) || $webhookUrl === '') {
            throw BitrixWebhookNotConfiguredException::forDomain($context->domain);
        }

        return $this->send(rtrim($webhookUrl, '/')."/{$method}", $params);
    }

    /**
     * Writes are never retried automatically (risk of duplicates); add ->retry() only for reads.
     *
     * @param  array<string, mixed>  $params
     * @return array<string, mixed>
     */
    private function send(string $url, array $params): array
    {
        try {
            $response = $this->http->timeout(30)->acceptJson()->post($url, $params);
        } catch (ConnectionException) {
            throw BitrixUnavailableException::connectionFailed();
        }

        if ($response->serverError()) {
            throw BitrixUnavailableException::serverError($response->status());
        }

        /** @var array<string, mixed> $payload */
        $payload = $response->json() ?? [];

        if (isset($payload['error'])) {
            throw BitrixRequestFailedException::fromPayload($payload); // SEC-5: never include the token
        }

        return $payload;
    }
}
```

Provider exceptions (`App\Exceptions\Bitrix`) override `status()`: the problem is upstream, not the caller's input. Messages carry only the provider's error code, never params or tokens (SEC-5).
```php
<?php

declare(strict_types=1);

namespace App\Exceptions\Bitrix;

use App\Exceptions\BusinessException;
use Symfony\Component\HttpFoundation\Response;

final class BitrixRequestFailedException extends BusinessException
{
    /**
     * @param  array<string, mixed>  $payload
     */
    public static function fromPayload(array $payload): self
    {
        $errorCode = is_string($payload['error'] ?? null) ? $payload['error'] : 'UNKNOWN';

        return new self("Bitrix24 request failed: {$errorCode}.");
    }

    public static function unexpectedResponse(string $method): self
    {
        return new self("Bitrix24 returned an unexpected response for {$method}.");
    }

    public function status(): int
    {
        return Response::HTTP_BAD_GATEWAY;
    }
}
```

```php
<?php

declare(strict_types=1);

namespace App\Exceptions\Bitrix;

use App\Exceptions\BusinessException;
use Symfony\Component\HttpFoundation\Response;

final class BitrixUnavailableException extends BusinessException
{
    public static function connectionFailed(): self
    {
        return new self('Bitrix24 could not be reached.');
    }

    public static function serverError(int $status): self
    {
        return new self("Bitrix24 responded with server error {$status}.");
    }

    public function status(): int
    {
        return Response::HTTP_SERVICE_UNAVAILABLE;
    }
}
```

```php
<?php

declare(strict_types=1);

namespace App\Exceptions\Bitrix;

use App\Exceptions\BusinessException;
use Symfony\Component\HttpFoundation\Response;

final class BitrixWebhookNotConfiguredException extends BusinessException
{
    public static function forDomain(string $domain): self
    {
        return new self("No Bitrix24 webhook is configured for {$domain}.");
    }

    public function status(): int
    {
        return Response::HTTP_SERVICE_UNAVAILABLE;
    }
}
```

`config/services.php` holds one webhook URL per portal domain (from `.env`, CODE-8):
```php
'bitrix' => [
    'webhooks' => [
        // 'portal.bitrix24.com' => env('BITRIX_WEBHOOK_URL'),
    ],
],
```

A domain on top of the gateway — DTO, contracts, services — with SEC-2, SEC-4 and SEC-5 applied:
```php
<?php

declare(strict_types=1);

namespace App\DTOs\Deal;

final readonly class CreateDealDTO
{
    public function __construct(
        public int $contactId,
        public string $title,
    ) {}

    /**
     * @param  array{contact_id: int|string, title: string}  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            contactId: (int) $data['contact_id'],
            title: $data['title'],
        );
    }

    /**
     * SEC-4: the only fields ever sent to Bitrix.
     *
     * @return array{CONTACT_ID: int, TITLE: string}
     */
    public function toBitrixFields(): array
    {
        return [
            'CONTACT_ID' => $this->contactId,
            'TITLE' => $this->title,
        ];
    }
}
```

```php
<?php

declare(strict_types=1);

namespace App\Services\Contracts;

use App\DTOs\Bitrix\BitrixContextDTO;
use App\DTOs\Deal\CreateDealDTO;

interface DealServiceInterface
{
    public function create(BitrixContextDTO $context, CreateDealDTO $dto): int;
}
```

```php
<?php

declare(strict_types=1);

namespace App\Services\Contracts;

use App\DTOs\Bitrix\BitrixContextDTO;

interface ContactServiceInterface
{
    public function ensureAccessible(BitrixContextDTO $context, int $contactId): void;
}
```

```php
<?php

declare(strict_types=1);

namespace App\Services;

use App\DTOs\Bitrix\BitrixContextDTO;
use App\DTOs\Deal\CreateDealDTO;
use App\Exceptions\Bitrix\BitrixRequestFailedException;
use App\Integrations\Bitrix\Contracts\BitrixGatewayInterface;
use App\Services\Contracts\ContactServiceInterface;
use App\Services\Contracts\DealServiceInterface;

final readonly class DealService implements DealServiceInterface
{
    public function __construct(
        private BitrixGatewayInterface $bitrix,
        private ContactServiceInterface $contactService,
    ) {}

    public function create(BitrixContextDTO $context, CreateDealDTO $dto): int
    {
        // SEC-2: proven with the caller's own token before the elevated webhook write.
        $this->contactService->ensureAccessible($context, $dto->contactId);

        $result = $this->bitrix->callAsWebhook($context, 'crm.deal.add', [
            'fields' => $dto->toBitrixFields(), // SEC-4: explicit field map
        ]);

        // Never (int) a provider value: a malformed response must fail, not become 0.
        $dealId = filter_var($result['result'] ?? null, FILTER_VALIDATE_INT);

        if ($dealId === false) {
            throw BitrixRequestFailedException::unexpectedResponse('crm.deal.add');
        }

        return $dealId;
    }
}
```

```php
<?php

declare(strict_types=1);

namespace App\Services;

use App\DTOs\Bitrix\BitrixContextDTO;
use App\Exceptions\Bitrix\BitrixRequestFailedException;
use App\Exceptions\Contact\ContactNotAccessibleException;
use App\Integrations\Bitrix\Contracts\BitrixGatewayInterface;
use App\Services\Contracts\ContactServiceInterface;

final readonly class ContactService implements ContactServiceInterface
{
    public function __construct(
        private BitrixGatewayInterface $bitrix,
    ) {}

    public function ensureAccessible(BitrixContextDTO $context, int $contactId): void
    {
        try {
            $this->bitrix->callAsUser($context, 'crm.contact.get', ['id' => $contactId]);
        } catch (BitrixRequestFailedException) {
            throw ContactNotAccessibleException::forId($contactId);
        }
    }
}
```

```php
<?php

declare(strict_types=1);

namespace App\Exceptions\Contact;

use App\Exceptions\BusinessException;
use Symfony\Component\HttpFoundation\Response;

final class ContactNotAccessibleException extends BusinessException
{
    public static function forId(int $contactId): self
    {
        return new self("Contact {$contactId} is not accessible.");
    }

    public function status(): int
    {
        return Response::HTTP_FORBIDDEN;
    }
}
```

Entry point: a FormRequest extending the base provider request, a thin controller, a Resource.
```php
<?php

declare(strict_types=1);

namespace App\Http\Requests\Deal;

use App\DTOs\Deal\CreateDealDTO;
use App\Http\Requests\Bitrix\BitrixRequest;

final class StoreDealRequest extends BitrixRequest
{
    /**
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        return [
            'contact_id' => ['required', 'integer', 'min:1'],
            'title' => ['required', 'string', 'max:255'],
        ];
    }

    public function toDto(): CreateDealDTO
    {
        /** @var array{contact_id: int|string, title: string} $validated */
        $validated = $this->validated();

        return CreateDealDTO::fromArray($validated);
    }
}
```

```php
<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Deal\StoreDealRequest;
use App\Http\Resources\DealResource;
use App\Services\Contracts\DealServiceInterface;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

final class DealController extends Controller
{
    public function __construct(
        private readonly DealServiceInterface $dealService,
    ) {}

    public function store(StoreDealRequest $request): JsonResponse
    {
        $dealId = $this->dealService->create($request->context(), $request->toDto());

        return DealResource::make(['id' => $dealId])
            ->response()
            ->setStatusCode(Response::HTTP_CREATED);
    }
}
```

```php
<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property array{id: int} $resource
 */
final class DealResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->resource['id'],
        ];
    }
}
```

Every list endpoint bounds its fan-out in the FormRequest (SEC-3); its controller and service follow the Deal ones:
```php
<?php

declare(strict_types=1);

namespace App\Http\Requests\Item;

use App\Http\Requests\Bitrix\BitrixRequest;

final class ListItemsRequest extends BitrixRequest
{
    /**
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        return [
            'batchSize' => ['required', 'integer', 'min:1', 'max:50'],
        ];
    }

    public function batchSize(): int
    {
        return $this->integer('batchSize');
    }
}
```

```php
// routes/api.php
Route::prefix('v1')->name('api.v1.')->middleware(AuthenticateBitrixCaller::class)->group(function (): void {
    Route::post('deals', [Api\V1\DealController::class, 'store'])->name('deals.store');
    Route::get('items', [Api\V1\ItemController::class, 'index'])->name('items.index');
});
```

Bindings for this section: `BitrixGatewayInterface::class => BitrixGateway::class` under `// Integrations`, plus every service contract above.

## Tests
```php
<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Http;

it('creates the deal through the webhook once the contact is accessible', function (): void {
    Http::fake([
        '*crm.contact.get*' => Http::response(['result' => ['ID' => 7]]),
        '*crm.deal.add*' => Http::response(['result' => 321]),
    ]);

    $this->withBitrixCaller()
        ->postJson(route('api.v1.deals.store'), ['contact_id' => 7, 'title' => 'New deal'])
        ->assertCreated()
        ->assertJsonPath('data.id', 321);

    Http::assertSent(fn (Illuminate\Http\Client\Request $request): bool => str_contains($request->url(), 'crm.deal.add')
        && $request['fields'] === ['CONTACT_ID' => 7, 'TITLE' => 'New deal']);
});

it('rejects callers without Bitrix auth', function (): void {
    $this->postJson(route('api.v1.deals.store'), ['contact_id' => 7, 'title' => 'New deal'])
        ->assertForbidden();
});

it('refuses to create a deal for a contact the caller cannot see', function (): void {
    Http::fake([
        '*crm.contact.get*' => Http::response(['error' => 'ACCESS_DENIED', 'error_description' => 'Access denied']),
        '*' => Http::response(['result' => 1]),
    ]);

    $this->withBitrixCaller()
        ->postJson(route('api.v1.deals.store'), ['contact_id' => 999, 'title' => 'New deal'])
        ->assertForbidden();

    Http::assertNotSent(fn (Illuminate\Http\Client\Request $request): bool => str_contains($request->url(), 'crm.deal.add'));
});

it('rejects out-of-bounds batch sizes', function (mixed $batchSize): void {
    $this->withBitrixCaller()
        ->getJson(route('api.v1.items.index', ['batchSize' => $batchSize]))
        ->assertUnprocessable()
        ->assertJsonValidationErrors('batchSize');
})->with([0, 51, 'abc', -1]);
```
`withBitrixCaller()` lives on `Tests\TestCase` and sends what `AuthenticateBitrixCaller` reads:
```php
<?php

declare(strict_types=1);

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    public const string BITRIX_DOMAIN = 'portal.bitrix24.com';

    public function withBitrixCaller(int $userId = 1): static
    {
        config()->set('services.bitrix.webhooks.'.self::BITRIX_DOMAIN, 'https://'.self::BITRIX_DOMAIN.'/rest/1/webhook-secret');

        return $this->withHeaders([
            'X-Bitrix-Domain' => self::BITRIX_DOMAIN,
            'X-Bitrix-Auth' => 'user-token',
            'X-Bitrix-User-Id' => (string) $userId,
        ]);
    }
}
```
(`const string` needs PHP 8.3; on 8.2 drop the constant type.)
