<?php

declare(strict_types=1);

namespace App\DTOs\Bitrix;

use App\Enums\BitrixInstallOutcome;

/** How an install call ended, and for a refused install page, why. */
final readonly class BitrixInstallResultDTO
{
    public function __construct(
        public BitrixInstallOutcome $outcome,
        public ?string $message = null,
        public ?int $status = null,
    ) {}

    /**
     * @param  array{outcome: BitrixInstallOutcome, message?: ?string, status?: ?int}  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(outcome: $data['outcome'], message: $data['message'] ?? null, status: $data['status'] ?? null);
    }
}
