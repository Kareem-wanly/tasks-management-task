<?php

declare(strict_types=1);

namespace App\Services;

use App\DTOs\Bitrix\BitrixReconciliationSummaryDTO;
use App\Enums\ReconciliationNightState;
use App\Models\BitrixReconciliation;
use App\Repositories\Contracts\BitrixReconciliationRepositoryInterface;
use App\Services\Contracts\BitrixReconciliationServiceInterface;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;

final readonly class BitrixReconciliationService implements BitrixReconciliationServiceInterface
{
    /** IDs kept per side, to look them up; the counts hold the rest. */
    public const SAMPLE_SIZE = 20;

    /** How far back the clean streak is counted, in days. */
    public const STREAK_DAYS = 31;

    /** The days shown as the recent trend, ending today. */
    public const NIGHTS_SHOWN = 7;

    public const KEEP_DAYS = 90;

    public function __construct(private BitrixReconciliationRepositoryInterface $reconciliations) {}

    public function record(string $entity, array $bitrixIds, array $localIds): void
    {
        $bitrix = array_values(array_unique($bitrixIds));
        $local = array_values(array_unique($localIds));
        // Compared in PHP: a NOT IN with every Bitrix ID outgrows the placeholder limit.
        $missingHere = array_values(array_diff($bitrix, $local));
        $extraHere = array_values(array_diff($local, $bitrix));
        sort($missingHere);
        sort($extraHere);

        $now = CarbonImmutable::now();
        $this->reconciliations->create([
            'entity' => $entity,
            'bitrix_count' => count($bitrix),
            'local_count' => count($local),
            'missing_here_count' => count($missingHere),
            'extra_here_count' => count($extraHere),
            'missing_here' => array_slice($missingHere, 0, self::SAMPLE_SIZE),
            'extra_here' => array_slice($extraHere, 0, self::SAMPLE_SIZE),
            'checked_at' => $now,
        ]);
        $this->reconciliations->pruneBefore($now->subDays(self::KEEP_DAYS));

        if ($missingHere !== [] || $extraHere !== []) {
            Log::warning('Bitrix24 reconciliation found differences.', [
                'entity' => $entity,
                'missing_here' => array_slice($missingHere, 0, self::SAMPLE_SIZE),
                'extra_here' => array_slice($extraHere, 0, self::SAMPLE_SIZE),
            ]);
        }
    }

    public function summary(): array
    {
        $today = CarbonImmutable::today();

        return array_map(function (string $entity) use ($today): BitrixReconciliationSummaryDTO {
            $checks = $this->reconciliations->since($entity, $today->subDays(self::STREAK_DAYS));
            $nights = $this->nightStates($checks);

            $nightly = [];
            for ($daysAgo = self::NIGHTS_SHOWN - 1; $daysAgo >= 0; $daysAgo--) {
                $date = $today->subDays($daysAgo)->toDateString();
                $nightly[] = ['date' => $date, 'state' => $nights[$date] ?? ReconciliationNightState::NoCheck];
            }

            return new BitrixReconciliationSummaryDTO(
                entity: $entity,
                latest: $checks->first(),
                cleanNights: $this->cleanNightsInARow($nights, $today),
                nights: $nightly,
            );
        }, self::ENTITIES);
    }

    /**
     * Each day with a check: clean only when every check that day matched
     * (seven manual imports in one day are still one night).
     *
     * @param  Collection<int, BitrixReconciliation>  $checks
     * @return array<string, ReconciliationNightState> By date.
     */
    private function nightStates(Collection $checks): array
    {
        $nights = [];
        foreach ($checks as $check) {
            $date = $check->checked_at->toDateString();
            $nights[$date] = ! $check->hasNoDifference() || ($nights[$date] ?? null) === ReconciliationNightState::Differences
                ? ReconciliationNightState::Differences
                : ReconciliationNightState::Clean;
        }

        return $nights;
    }

    /**
     * Clean days in a row, back from today (or from yesterday while tonight's
     * import hasn't run yet). A day without a check ends the streak.
     *
     * @param  array<string, ReconciliationNightState>  $nights
     */
    private function cleanNightsInARow(array $nights, CarbonImmutable $today): int
    {
        $day = isset($nights[$today->toDateString()]) ? $today : $today->subDay();
        $streak = 0;
        while (($nights[$day->toDateString()] ?? null) === ReconciliationNightState::Clean) {
            $streak++;
            $day = $day->subDay();
        }

        return $streak;
    }
}
