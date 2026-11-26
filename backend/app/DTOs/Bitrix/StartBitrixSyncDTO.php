<?php

declare(strict_types=1);

namespace App\DTOs\Bitrix;

use App\Enums\BitrixSyncMode;

final readonly class StartBitrixSyncDTO
{
    public function __construct(
        public BitrixSyncMode $mode,
    ) {}

    /**
     * @param  array{mode: string}  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(mode: BitrixSyncMode::from($data['mode']));
    }
}
