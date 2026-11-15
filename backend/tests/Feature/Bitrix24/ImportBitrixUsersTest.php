<?php

namespace Tests\Feature\Bitrix24;

use App\Integrations\Bitrix24\BitrixClient;
use App\Integrations\Bitrix24\BitrixException;
use App\Integrations\Bitrix24\BitrixToken;
use App\Integrations\Bitrix24\Contracts\HasBitrixToken;
use App\Jobs\Bitrix\ImportBitrixUsers;
use App\Models\Role;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class ImportBitrixUsersTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['bitrix.portal' => 'portal.test']);
        Role::create(['name' => 'Member', 'display_name' => 'Team Member', 'description' => 'x']);

        app()->instance(BitrixClient::SERVICE_ACCOUNT, new class implements HasBitrixToken
        {
            public function bitrixToken(): ?BitrixToken
            {
                return new BitrixToken('service-access', 'service-refresh', CarbonImmutable::now()->addHour());
            }

            public function storeBitrixToken(BitrixToken $token): void {}

            public function bitrixTokenLockKey(): string
            {
                return 'bitrix-token:test';
            }
        });
    }

    private function row(int $id, array $overrides = []): array
    {
        return array_merge([
            'ID' => (string) $id,
            'ACTIVE' => true,
            'NAME' => "Name{$id}",
            'LAST_NAME' => "Last{$id}",
            'EMAIL' => "user{$id}@portal.test",
            'PERSONAL_PHOTO' => "https://portal.test/{$id}.jpg",
            'WORK_POSITION' => 'Engineer',
            'UF_DEPARTMENT' => [1, 5],
        ], $overrides);
    }

    /**
     * Every user the fake portal holds; tests change it between imports.
     *
     * @var list<array>
     */
    private array $portalRows = [];

    private ?array $batchError = null;

    private bool $portalFaked = false;

    /**
     * @param  list<array>  $rows  All portal users; served 50 per page.
     */
    private function fakePortal(array $rows, ?array $batchError = null): void
    {
        $this->portalRows = $rows;
        $this->batchError = $batchError;

        // Http::fake() stubs can't be replaced later, so register them once.
        if ($this->portalFaked) {
            return;
        }
        $this->portalFaked = true;

        Http::fake([
            'https://portal.test/rest/user.get.json' => fn () => Http::response([
                'result' => array_slice($this->portalRows, 0, 50),
                'total' => count($this->portalRows),
            ]),
            'https://portal.test/rest/batch.json' => function (Request $request) {
                $result = [];
                foreach (array_keys($request['cmd']) as $key) {
                    $result[$key] = array_slice($this->portalRows, (int) substr($key, 5), 50);
                }

                return Http::response(['result' => [
                    'result' => $result,
                    'result_error' => $this->batchError ?? [],
                ]]);
            },
        ]);
    }

    private function import(): void
    {
        ImportBitrixUsers::dispatchSync();
    }

    public function test_every_page_of_portal_users_is_imported(): void
    {
        $this->fakePortal(array_map(fn (int $id) => $this->row($id), range(1, 120)));

        $this->import();

        $this->assertSame(120, User::whereNotNull('bitrix_user_id')->count());
        // One user.get for the first page, one batch for the other two.
        Http::assertSentCount(2);
        Http::assertSent(fn (Request $r) => str_ends_with($r->url(), '/batch.json')
            && array_keys($r['cmd']) === ['page_50', 'page_100']
            && ! str_contains($r['cmd']['page_50'], 'ACTIVE'));

        $user = User::where('bitrix_user_id', 7)->first();
        $this->assertSame('Name7 Last7', $user->name);
        $this->assertSame('user7@portal.test', $user->email);
        $this->assertSame('https://portal.test/7.jpg', $user->avatar_url);
        $this->assertSame('Engineer', $user->position);
        $this->assertSame([1, 5], $user->bitrix_department_ids);
        $this->assertTrue($user->is_active);
        $this->assertNull($user->password);
        $this->assertTrue($user->hasRole('Member'));
    }

    public function test_job_runs_on_the_bitrix_queue(): void
    {
        $this->assertSame('bitrix', (new ImportBitrixUsers)->queue);
    }

    public function test_running_twice_updates_instead_of_duplicating(): void
    {
        $this->fakePortal([$this->row(1), $this->row(2)]);
        $this->import();

        $this->fakePortal([$this->row(1, ['NAME' => 'Renamed', 'WORK_POSITION' => 'Lead']), $this->row(2)]);
        $this->import();

        $this->assertSame(2, User::count());
        $user = User::where('bitrix_user_id', 1)->first();
        $this->assertSame('Renamed Last1', $user->name);
        $this->assertSame('Lead', $user->position);
        $this->assertSame(1, $user->roles()->count());
    }

    public function test_existing_local_account_is_linked_by_email(): void
    {
        $local = User::factory()->create(['email' => 'User3@Portal.test']);
        $this->fakePortal([$this->row(3)]);

        $this->import();

        $this->assertSame(1, User::count());
        $local->refresh();
        $this->assertSame(3, $local->bitrix_user_id);
        $this->assertNotNull($local->password);
    }

    public function test_deactivated_user_is_imported_inactive_and_cannot_log_in(): void
    {
        $this->fakePortal([$this->row(4, ['ACTIVE' => false])]);

        $this->import();

        $user = User::where('bitrix_user_id', 4)->first();
        $this->assertFalse($user->is_active);

        $user->forceFill(['password' => 'secret-password'])->save();
        $this->postJson('/api/login', ['email' => 'user4@portal.test', 'password' => 'secret-password'])
            ->assertForbidden();
    }

    public function test_deactivation_in_bitrix_revokes_open_sessions(): void
    {
        $this->fakePortal([$this->row(5)]);
        $this->import();
        $user = User::where('bitrix_user_id', 5)->first();
        $user->createToken('auth_token');

        $this->fakePortal([$this->row(5, ['ACTIVE' => 'N'])]);
        $this->import();

        $this->assertFalse($user->refresh()->is_active);
        $this->assertSame(0, $user->tokens()->count());
    }

    public function test_missing_or_taken_email_falls_back_to_a_placeholder(): void
    {
        $this->fakePortal([
            $this->row(6, ['EMAIL' => '']),
            $this->row(7, ['EMAIL' => 'shared@portal.test']),
            $this->row(8, ['EMAIL' => 'shared@portal.test']),
        ]);

        $this->import();

        $this->assertSame('bitrix-6@portal.test', User::where('bitrix_user_id', 6)->value('email'));
        $this->assertSame('shared@portal.test', User::where('bitrix_user_id', 7)->value('email'));
        $this->assertSame('bitrix-8@portal.test', User::where('bitrix_user_id', 8)->value('email'));
    }

    public function test_a_failed_page_fails_the_job(): void
    {
        $this->fakePortal(
            array_map(fn (int $id) => $this->row($id), range(1, 60)),
            ['page_50' => ['error' => 'ACCESS_DENIED', 'error_description' => 'denied']],
        );

        $this->expectException(BitrixException::class);

        $this->import();
    }
}
