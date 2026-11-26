<?php

declare(strict_types=1);

namespace App\Integrations\Bitrix24\Repositories;

use App\Integrations\Bitrix24\BitrixPayload;
use App\Integrations\Bitrix24\Contracts\BitrixAppRepositoryInterface;
use App\Integrations\Bitrix24\Contracts\BitrixGatewayInterface;
use App\Integrations\Bitrix24\Contracts\HasBitrixToken;

final readonly class BitrixAppRepository implements BitrixAppRepositoryInterface
{
    /** department.get returns this many per page. */
    private const DEPARTMENTS_PAGE_SIZE = 50;

    public function __construct(private BitrixGatewayInterface $bitrix) {}

    public function currentUser(string $accessToken): array
    {
        $profile = $this->bitrix->callWithToken($accessToken, 'user.current')['result'] ?? [];

        return is_array($profile) ? $profile : [];
    }

    public function appInfo(string $accessToken): array
    {
        $app = $this->bitrix->callWithToken($accessToken, 'app.info')['result'] ?? [];

        return is_array($app) ? $app : [];
    }

    public function isAdmin(string $accessToken): bool
    {
        return (bool) ($this->bitrix->callWithToken($accessToken, 'user.admin')['result'] ?? false);
    }

    public function portalDetails(HasBitrixToken $portal): array
    {
        return $this->bitrix->batch($portal, [
            'app' => ['app.info'],
            'scope' => ['scope'],
            'me' => ['user.current'],
            'admin' => ['user.admin'],
            'departments' => ['department.get'],
        ]);
    }

    public function departments(HasBitrixToken $portal, array $firstPage, int $total): array
    {
        $departments = $firstPage;

        for ($start = self::DEPARTMENTS_PAGE_SIZE; $start < $total; $start += self::DEPARTMENTS_PAGE_SIZE) {
            $page = BitrixPayload::of($this->bitrix->callAsUser($portal, 'department.get', ['START' => $start]), 'department.get');
            $departments = [...$departments, ...$page->rows('result')];
        }

        return $departments;
    }

    public function bindEvents(HasBitrixToken $portal, array $events, string $handlerUrl): array
    {
        $commands = [];
        foreach ($events as $event) {
            $commands[$event] = ['event.bind', ['event' => $event, 'handler' => $handlerUrl]];
        }

        return $this->bitrix->batch($portal, $commands);
    }
}
