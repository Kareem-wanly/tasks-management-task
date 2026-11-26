<?php

declare(strict_types=1);

namespace App\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;

/**
 * Step 5.3: one check of the nightly reconciliation for one entity (users,
 * projects or tasks). Stored and queried through BitrixReconciliationRepository.
 *
 * @property int $id
 * @property string $entity
 * @property int $bitrix_count
 * @property int $local_count
 * @property int $missing_here_count In Bitrix but not here.
 * @property int $extra_here_count Here but not in Bitrix.
 * @property list<int> $missing_here The first Bitrix IDs of those missing here.
 * @property list<int> $extra_here The first Bitrix IDs of those extra here.
 * @property CarbonImmutable $checked_at
 */
class BitrixReconciliation extends Model
{
    protected $fillable = [
        'entity', 'bitrix_count', 'local_count', 'missing_here_count', 'extra_here_count',
        'missing_here', 'extra_here', 'checked_at',
    ];

    /** Both sides hold the same records. */
    public function hasNoDifference(): bool
    {
        return $this->missing_here_count === 0 && $this->extra_here_count === 0;
    }

    protected function casts(): array
    {
        return [
            'bitrix_count' => 'integer',
            'local_count' => 'integer',
            'missing_here_count' => 'integer',
            'extra_here_count' => 'integer',
            'missing_here' => 'array',
            'extra_here' => 'array',
            'checked_at' => 'datetime',
        ];
    }
}
