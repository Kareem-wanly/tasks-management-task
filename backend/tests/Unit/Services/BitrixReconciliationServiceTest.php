<?php

declare(strict_types=1);

use App\Models\BitrixReconciliation;
use App\Repositories\Contracts\BitrixReconciliationRepositoryInterface;
use App\Services\BitrixReconciliationService;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Log;

beforeEach(function (): void {
    CarbonImmutable::setTestNow('2026-11-26 03:00:00');
    $this->repository = Mockery::mock(BitrixReconciliationRepositoryInterface::class);
    $this->service = new BitrixReconciliationService($this->repository);
});

afterEach(fn () => CarbonImmutable::setTestNow());

/** A stored check, $daysAgo days back (at 03:00, or later the same day), with the given differences. */
function check(int $daysAgo = 0, int $missing = 0, int $extra = 0, int $hour = 3): BitrixReconciliation
{
    return (new BitrixReconciliation)->forceFill([
        'entity' => 'tasks', 'bitrix_count' => 10, 'local_count' => 10, 'missing_here_count' => $missing, 'extra_here_count' => $extra,
        'missing_here' => [], 'extra_here' => [], 'checked_at' => CarbonImmutable::today()->subDays($daysAgo)->setHour($hour),
    ]);
}

it('stores both counts and the IDs on each side, sorted and without repeats', function (): void {
    Log::spy();
    $this->repository->shouldReceive('create')->once()->with([
        'entity' => 'tasks', 'bitrix_count' => 4, 'local_count' => 3,
        'missing_here_count' => 2, 'extra_here_count' => 1,
        'missing_here' => [7, 9], 'extra_here' => [50],
        'checked_at' => CarbonImmutable::now(),
    ])->andReturn(new BitrixReconciliation);
    $this->repository->shouldReceive('pruneBefore')->once()->withArgs(fn (CarbonImmutable $before): bool => $before->equalTo(CarbonImmutable::now()->subDays(90)));

    $this->service->record('tasks', [9, 1, 7, 2, 9], [1, 2, 50, 50]);

    Log::shouldHaveReceived('warning')->once()->withArgs(fn (string $message, array $context): bool => $context['missing_here'] === [7, 9] && $context['extra_here'] === [50]);
});

it('keeps only the first IDs of a large difference, but counts them all', function (): void {
    $this->repository->shouldReceive('create')->once()->withArgs(fn (array $row): bool => $row['missing_here_count'] === 500
        && $row['missing_here'] === range(1, BitrixReconciliationService::SAMPLE_SIZE))
        ->andReturn(new BitrixReconciliation);
    $this->repository->shouldReceive('pruneBefore')->once();

    $this->service->record('users', range(1, 500), []);
});

it('logs nothing when both sides match', function (): void {
    Log::spy();
    $this->repository->shouldReceive('create')->once()->andReturn(new BitrixReconciliation);
    $this->repository->shouldReceive('pruneBefore')->once();

    $this->service->record('projects', [3, 1], [1, 3]);

    Log::shouldNotHaveReceived('warning');
});

/** @param  list<BitrixReconciliation>  $checks  Newest first, as the repository returns them. */
function checksSince(string $entity, array $checks): void
{
    test()->repository->shouldReceive('since')
        ->withArgs(fn (string $asked, CarbonImmutable $since): bool => $asked === $entity
            && $since->equalTo(CarbonImmutable::today()->subDays(BitrixReconciliationService::STREAK_DAYS)))
        ->andReturn(collect($checks));
}

/** @return list<string> */
function nightStates(App\DTOs\Bitrix\BitrixReconciliationSummaryDTO $summary): array
{
    return array_map(fn (array $night): string => $night['state']->value, $summary->nights);
}

it('counts clean nights, not checks: seven imports in one day are one night', function (): void {
    checksSince('users', array_map(fn (int $hour): BitrixReconciliation => check(hour: $hour), range(17, 11)));
    checksSince('projects', []);
    checksSince('tasks', []);

    [$users] = $this->service->summary();

    expect($users->cleanNights)->toBe(1)
        ->and(nightStates($users))->toBe(['none', 'none', 'none', 'none', 'none', 'none', 'clean']);
});

it('makes a day dirty when any of its checks found a difference, and a day without a check ends the streak', function (): void {
    // Today: clean then dirty. Before: a clean run of three, a gap, then older clean days.
    checksSince('users', [check(0, hour: 15), check(0, missing: 1), check(1), check(2), check(3), check(5), check(6)]);
    // Not checked yet today: the streak counts from yesterday.
    checksSince('projects', [check(1), check(2), check(3, extra: 2), check(4)]);
    checksSince('tasks', []);

    [$users, $projects, $tasks] = $this->service->summary();

    expect($users)->cleanNights->toBe(0)->latest->checked_at->hour->toBe(15)
        ->and(nightStates($users))->toBe(['clean', 'clean', 'none', 'clean', 'clean', 'clean', 'differences'])
        ->and($projects->cleanNights)->toBe(2)
        ->and(nightStates($projects))->toBe(['none', 'none', 'clean', 'differences', 'clean', 'clean', 'none'])
        ->and($tasks)->latest->toBeNull()->cleanNights->toBe(0)
        ->and(array_column($tasks->nights, 'date'))->toBe(array_map(fn (int $d): string => CarbonImmutable::today()->subDays($d)->toDateString(), range(6, 0)));
});

it('reaches a clean week after seven clean nights in a row', function (): void {
    checksSince('users', array_map(fn (int $daysAgo): BitrixReconciliation => check($daysAgo), range(0, 9)));
    checksSince('projects', []);
    checksSince('tasks', []);

    expect($this->service->summary()[0]->cleanNights)->toBe(10);
});
