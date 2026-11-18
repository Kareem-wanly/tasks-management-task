<?php

declare(strict_types=1);

namespace Tests\Feature\Bitrix24;

use App\Integrations\Bitrix24\BitrixClient;
use App\Integrations\Bitrix24\BitrixException;
use App\Integrations\Bitrix24\BitrixToken;
use App\Integrations\Bitrix24\Contracts\HasBitrixToken;
use App\Jobs\Bitrix\ImportBitrixProjects;
use App\Models\ActivityLog;
use App\Models\Project;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class ImportBitrixProjectsTest extends TestCase
{
    use RefreshDatabase;

    /** @var list<array> Every workgroup the fake portal holds. */
    private array $groups = [];

    /** @var array<int, list<array>> group ID => members */
    private array $members = [];

    private bool $serviceIsAdmin = true;

    private ?array $batchError = null;

    protected function setUp(): void
    {
        parent::setUp();
        config(['bitrix.portal' => 'portal.test']);

        app()->instance(BitrixClient::SERVICE_ACCOUNT, new class implements HasBitrixToken
        {
            public function bitrixToken(): BitrixToken
            {
                return new BitrixToken('service-access', 'service-refresh', CarbonImmutable::now()->addHour());
            }

            public function storeBitrixToken(BitrixToken $token): void {}

            public function bitrixTokenLockKey(): string
            {
                return 'bitrix-token:test';
            }
        });

        foreach ([1, 2, 3, 4] as $bitrixId) {
            User::factory()->create(['bitrix_user_id' => $bitrixId]);
        }

        // Stubs can't be replaced later, so they read the properties above.
        Http::fake([
            'https://portal.test/rest/socialnetwork.api.workgroup.list.json' => function (Request $request) {
                // One group by ID (BitrixRecordFetcher::group) or the first page of the listing.
                $groups = isset($request['filter']['ID'])
                    ? array_values(array_filter($this->groups, fn (array $g): bool => (int) $g['id'] === (int) $request['filter']['ID']))
                    : array_slice($this->groups, 0, 50);

                return Http::response(['result' => ['workgroups' => $groups], 'total' => count($this->groups)]);
            },
            'https://portal.test/rest/user.admin.json' => fn () => Http::response(['result' => $this->serviceIsAdmin]),
            'https://portal.test/rest/batch.json' => function (Request $request) {
                $result = [];
                foreach ($request['cmd'] as $key => $command) {
                    $result[$key] = str_starts_with($key, 'page_')
                        ? ['workgroups' => array_slice($this->groups, (int) substr($key, 5), 50)]
                        : $this->members[(int) substr($key, 6)] ?? [];
                }

                return Http::response(['result' => ['result' => $result, 'result_error' => $this->batchError ?? []]]);
            },
        ]);
    }

    public function test_group_is_imported_with_owner_dates_and_member_roles(): void
    {
        $this->groups = [$this->group(10, [
            // As Bitrix returns them: midnight at +03:00, in UTC.
            'projectDateStart' => '2026-10-31T21:00:00+00:00',
            'projectDateFinish' => '2026-12-30T21:00:00+00:00',
        ])];
        $this->members = [10 => [
            ['USER_ID' => '1', 'ROLE' => 'A'],
            ['USER_ID' => '2', 'ROLE' => 'E'],
            ['USER_ID' => '3', 'ROLE' => 'K'],
            ['USER_ID' => '4', 'ROLE' => 'Z'],
        ]];

        $this->import();

        $project = Project::where('bitrix_group_id', 10)->sole();
        $this->assertSame('Group 10', $project->title);
        $this->assertSame('About it', $project->description);
        $this->assertSame($this->user(1)->id, $project->owner_id);
        $this->assertSame('active', $project->status);
        $this->assertSame('2026-11-01', $project->start_date->toDateString());
        $this->assertSame('2026-12-31', $project->due_date->toDateString());
        $this->assertNotNull($project->bitrix_synced_at);

        $roles = $project->members()->get()->mapWithKeys(fn (User $u): array => [$u->bitrix_user_id => $u->pivot->role])->all();
        // A pending request (Z) is not a member.
        $this->assertSame([1 => 'owner', 2 => 'moderator', 3 => 'member'], $roles);

        Http::assertSent(fn (Request $r): bool => str_contains($r->url(), 'workgroup.list') && $r['params']['IS_ADMIN'] === 'Y');
    }

    public function test_job_runs_on_the_bitrix_queue(): void
    {
        $this->assertSame('bitrix', (new ImportBitrixProjects)->queue);
    }

    public function test_every_page_of_groups_is_imported(): void
    {
        $this->groups = array_map(fn (int $id): array => $this->group($id), range(1, 120));

        $this->import();

        $this->assertSame(120, Project::whereNotNull('bitrix_group_id')->count());
    }

    public function test_running_twice_updates_without_duplicates_or_log_noise(): void
    {
        $this->groups = [$this->group(10)];
        $this->members = [10 => [['USER_ID' => '1', 'ROLE' => 'A'], ['USER_ID' => '2', 'ROLE' => 'K']]];
        $this->import();
        $logs = ActivityLog::count();

        $this->import();
        $this->assertSame($logs, ActivityLog::count());

        $this->groups = [$this->group(10, ['name' => 'Renamed'])];
        $this->members = [10 => [['USER_ID' => '1', 'ROLE' => 'A'], ['USER_ID' => '3', 'ROLE' => 'E']]];
        $this->import();

        $project = Project::sole();
        $this->assertSame('Renamed', $project->title);
        // Member 2 left the group in Bitrix, member 3 joined.
        $this->assertEqualsCanonicalizing(
            [$this->user(1)->id, $this->user(3)->id],
            $project->members()->pluck('users.id')->all()
        );
    }

    public function test_closed_group_is_archived_and_reopened_one_is_active(): void
    {
        $this->groups = [$this->group(10, ['closed' => 'Y'])];
        $this->import();
        $this->assertSame('archived', Project::sole()->status);

        $this->groups = [$this->group(10)];
        $this->import();
        $this->assertSame('active', Project::sole()->status);
    }

    public function test_local_status_is_kept_while_the_group_is_open(): void
    {
        $this->groups = [$this->group(10)];
        $this->import();
        Project::sole()->update(['status' => 'on_hold']);

        $this->import();

        $this->assertSame('on_hold', Project::sole()->status);
    }

    public function test_group_whose_owner_is_not_imported_is_skipped(): void
    {
        $this->groups = [$this->group(10, ['ownerId' => '99']), $this->group(11)];
        $this->members = [11 => [['USER_ID' => '99', 'ROLE' => 'K'], ['USER_ID' => '1', 'ROLE' => 'A']]];

        $this->import();

        $this->assertSame([11], Project::pluck('bitrix_group_id')->all());
        $this->assertSame(1, Project::sole()->members()->count());
    }

    public function test_project_deleted_here_is_restored_while_it_exists_in_bitrix(): void
    {
        $this->groups = [$this->group(10)];
        $this->import();
        Project::sole()->delete();

        $this->import();

        $this->assertSame(1, Project::count());
    }

    public function test_project_deleted_here_stays_deleted_while_its_group_is_archived(): void
    {
        $this->groups = [$this->group(10)];
        $this->import();
        Project::sole()->delete();

        // Our delete archived the Bitrix group.
        $this->groups = [$this->group(10, ['closed' => 'Y'])];
        $this->import();

        $this->assertSame(0, Project::count());
    }

    public function test_failed_member_lookup_fails_the_job(): void
    {
        $this->groups = [$this->group(10)];
        $this->batchError = ['group_10' => ['error' => 'ACCESS_DENIED', 'error_description' => 'denied']];

        $this->expectException(BitrixException::class);

        $this->import();
    }

    public function test_a_group_gone_from_bitrix_is_deleted_here(): void
    {
        $this->groups = [$this->group(10), $this->group(11)];
        $this->import();

        // 11 was deleted in Bitrix while its event was lost.
        $this->groups = [$this->group(10)];
        $this->import();

        $this->assertSame([10], Project::pluck('bitrix_group_id')->all());
    }

    /** Review A4: a non-admin service account doesn't see every group, so nothing is deleted. */
    public function test_no_group_is_deleted_when_the_service_account_is_not_an_admin(): void
    {
        $this->groups = [$this->group(10), $this->group(11)];
        $this->import();

        $this->groups = [$this->group(10)];
        $this->serviceIsAdmin = false;
        $this->import();

        $this->assertSame(2, Project::count());
    }

    public function test_re_importing_an_unchanged_group_keeps_its_updated_at(): void
    {
        $this->groups = [$this->group(10)];
        $this->import();
        $updatedAt = Project::sole()->updated_at->toDateTimeString();

        $this->travel(1)->hours();
        $this->import();

        $this->assertSame($updatedAt, Project::sole()->updated_at->toDateTimeString());
    }

    private function group(int $id, array $overrides = []): array
    {
        return array_merge([
            'id' => (string) $id,
            'name' => "Group {$id}",
            'description' => 'About it',
            'ownerId' => '1',
            'closed' => 'N',
            'projectDateStart' => null,
            'projectDateFinish' => null,
        ], $overrides);
    }

    private function user(int $bitrixId): User
    {
        return User::where('bitrix_user_id', $bitrixId)->first();
    }

    private function import(): void
    {
        dispatch_sync(new ImportBitrixProjects);
    }
}
