<?php

declare(strict_types=1);

namespace Tests\Feature\Bitrix24;

use App\Integrations\Bitrix24\BitrixToken;
use App\Integrations\Bitrix24\Contracts\BitrixGatewayInterface;
use App\Jobs\Bitrix\ImportBitrixChat;
use App\Jobs\Bitrix\SyncBitrixTaskChat;
use App\Models\Permission;
use App\Models\Project;
use App\Models\Role;
use App\Models\Task;
use App\Models\TaskChatMessage;
use App\Models\User;
use Carbon\CarbonImmutable;
use Closure;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Testing\TestResponse;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Step 4.7: the task chat (replaced comments).
 */
class BitrixTaskChatTest extends TestCase
{
    use RefreshDatabase;

    private const CHAT = 103;

    /** Whose token is whose in the fake Bitrix: access token => Bitrix user ID. */
    private const TOKENS = ['actor-access' => 5, 'colleague-access' => 6];

    private User $actor;

    private User $colleague;

    private Task $task;

    /** Messages of chat 103 in the fake Bitrix, by message ID. */
    private array $messages = [];

    /** Files the chat knows (im.dialog.messages.get "files"). */
    private array $files = [];

    private bool $unreachable = false;

    /** Runs as Bitrix creates a message, e.g. a page poll storing it before our request does. */
    private ?Closure $beforeAdd = null;

    protected function setUp(): void
    {
        parent::setUp();
        config(['bitrix.portal' => 'portal.test']);
        $this->seed(RolesAndPermissionsSeeder::class);

        $this->actor = $this->user(5, 'actor', Role::where('name', 'Administrator')->first());
        $member = Role::create(['name' => 'Chatter', 'display_name' => 'Chatter', 'description' => 'x']);
        $member->permissions()->sync(Permission::whereIn('name', ['tasks.view', 'comments.view', 'comments.create'])->pluck('id'));
        $this->colleague = $this->user(6, 'colleague', $member);
        app()->instance(BitrixGatewayInterface::SERVICE_ACCOUNT, $this->actor);

        $project = Project::forceCreate(['title' => 'P', 'status' => 'active', 'owner_id' => $this->actor->id, 'bitrix_group_id' => 3]);
        $project->members()->attach($this->colleague->id, ['role' => 'member']);
        $this->task = Task::forceCreate([
            'title' => 'T', 'status' => 'todo', 'priority' => 'medium', 'project_id' => $project->id,
            'created_by' => $this->actor->id, 'assigned_to' => $this->colleague->id, 'bitrix_task_id' => 15,
        ]);

        Http::fake(function (Request $request) {
            if ($this->unreachable) {
                throw new ConnectionException('timed out');
            }
            if (str_starts_with($request->url(), 'https://portal.test/download/')) {
                return Http::response('file-bytes', 200, ['Content-Type' => 'application/octet-stream']);
            }

            return Http::response(['result' => $this->bitrix(basename(parse_url($request->url(), PHP_URL_PATH), '.json'), $request->data())]);
        });
        Sanctum::actingAs($this->actor);
    }

    public function test_opening_the_task_brings_in_its_bitrix_chat(): void
    {
        $this->say(6, 'Hello from Bitrix');
        $this->say(0, '[USER=5]Actor[/USER] changed the deadline.');
        $this->files = [['id' => 202, 'name' => 'plan.png', 'size' => 2048, 'type' => 'image']];
        $this->say(5, '', ['FILE_ID' => [202]]);

        $this->chat()->assertOk()
            ->assertJsonCount(3, 'data')
            ->assertJsonPath('data.0.text', 'Hello from Bitrix')
            ->assertJsonPath('data.0.author.id', $this->colleague->id)
            ->assertJsonPath('data.0.can_edit', false)
            ->assertJsonPath('data.1.is_system', true)
            ->assertJsonPath('data.1.author', null)
            ->assertJsonPath('data.2.files.0.name', 'plan.png')
            ->assertJsonPath('meta.stale', false)
            ->assertJsonPath('meta.can_post', true);

        $this->assertSame(self::CHAT, $this->task->fresh()->bitrix_chat_id);
    }

    public function test_a_message_is_sent_as_the_employee_and_its_echo_does_not_double(): void
    {
        Sanctum::actingAs($this->colleague);

        $this->postJson("/api/v1/tasks/{$this->task->id}/chat", ['text' => 'On it'])
            ->assertOk()
            ->assertJsonPath('data.0.text', 'On it')
            ->assertJsonPath('data.0.can_edit', true);

        Http::assertSent(fn (Request $r): bool => str_ends_with($r->url(), 'im.message.add.json')
            && $r['auth'] === 'colleague-access' && $r['DIALOG_ID'] === 'chat'.self::CHAT && $r['MESSAGE'] === 'On it');
        $this->assertSame(1001, TaskChatMessage::sole()->bitrix_message_id);

        // Bitrix then sends OnTaskCommentAdd for our own message.
        app()->call([new SyncBitrixTaskChat(15), 'handle']);
        $this->assertSame(1, TaskChatMessage::count());
    }

    /** Review C2: a refresh that stores the new message first must not make sending fail or double it. */
    public function test_a_message_a_refresh_stored_first_is_not_saved_twice(): void
    {
        Sanctum::actingAs($this->colleague);
        $this->beforeAdd = function (int $bitrixId): void {
            TaskChatMessage::create(['task_id' => $this->task->id, 'bitrix_message_id' => $bitrixId, 'author_id' => $this->colleague->id, 'text' => 'On it']);
        };

        $this->postJson("/api/v1/tasks/{$this->task->id}/chat", ['text' => 'On it'])->assertOk()->assertJsonCount(1, 'data');

        $this->assertSame(1, TaskChatMessage::count());
    }

    public function test_the_author_edits_and_deletes_their_message_in_bitrix(): void
    {
        Sanctum::actingAs($this->colleague);
        $this->postJson("/api/v1/tasks/{$this->task->id}/chat", ['text' => 'Draft']);
        $message = TaskChatMessage::sole();

        $this->putJson("/api/v1/task-chat-messages/{$message->id}", ['text' => 'Final'])->assertOk()->assertJsonPath('data.0.text', 'Final');
        Http::assertSent(fn (Request $r): bool => str_ends_with($r->url(), 'im.message.update.json') && $r['auth'] === 'colleague-access' && $r['ID'] === 1001);

        $this->deleteJson("/api/v1/task-chat-messages/{$message->id}")->assertOk()->assertJsonCount(0, 'data');
        Http::assertSent(fn (Request $r): bool => str_ends_with($r->url(), 'im.message.delete.json') && $r['ID'] === 1001);
        $this->assertSame([], $this->messages);
    }

    public function test_others_messages_and_system_lines_cannot_be_changed(): void
    {
        $this->say(5, 'From the actor');
        $this->say(0, 'Status changed.');
        $this->chat();
        [$theirs, $system] = TaskChatMessage::orderBy('id')->get()->all();

        Sanctum::actingAs($this->colleague);
        $this->putJson("/api/v1/task-chat-messages/{$theirs->id}", ['text' => 'x'])->assertForbidden();
        $this->deleteJson("/api/v1/task-chat-messages/{$theirs->id}")->assertForbidden();

        // Not even an administrator rewrites Bitrix's own lines.
        Sanctum::actingAs($this->actor);
        $this->putJson("/api/v1/task-chat-messages/{$system->id}", ['text' => 'x'])->assertForbidden();
        $this->deleteJson("/api/v1/task-chat-messages/{$system->id}")->assertForbidden();

        Http::assertNotSent(fn (Request $r): bool => str_contains($r->url(), 'im.message.update') || str_contains($r->url(), 'im.message.delete'));
    }

    public function test_edits_and_deletes_made_in_bitrix_show_on_the_next_open(): void
    {
        $kept = $this->say(6, 'First');
        $gone = $this->say(6, 'Second');
        $this->chat()->assertJsonCount(2, 'data');

        $this->messages[$kept]['text'] = 'First (edited)';
        unset($this->messages[$gone]);
        $this->travel(11)->seconds();

        $this->chat()->assertJsonCount(1, 'data')->assertJsonPath('data.0.text', 'First (edited)');
    }

    public function test_the_page_polling_asks_bitrix_at_most_every_ten_seconds(): void
    {
        $this->chat();
        $this->chat();

        Http::assertSentCount(2); // tasks.task.get (chat ID, once) + one page of messages
    }

    public function test_bitrix_unreachable_shows_our_last_copy(): void
    {
        $this->say(6, 'Hello');
        $this->chat();
        $this->unreachable = true;
        $this->travel(11)->seconds();

        $this->chat()->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('meta.stale', true);
    }

    /** Review C5: whoever reads the chat downloads its files, through the service account. */
    public function test_a_file_from_the_chat_downloads_through_the_service_account(): void
    {
        $this->files = [['id' => 202, 'name' => 'plan.png', 'size' => 2048, 'type' => 'image']];
        $this->say(6, '', ['FILE_ID' => [202]]);
        $this->chat();
        $message = TaskChatMessage::sole();
        // Not signed in with Bitrix24: downloading must still work.
        $this->colleague->forceFill(['bitrix_access_token' => null, 'bitrix_refresh_token' => null])->save();
        Sanctum::actingAs($this->colleague->fresh());

        $response = $this->get("/api/v1/task-chat-messages/{$message->id}/files/202/download");

        $response->assertOk()->assertHeader('content-disposition', 'attachment; filename=plan.png');
        $this->assertSame('file-bytes', $response->streamedContent());
        Http::assertSent(fn (Request $r): bool => str_ends_with($r->url(), 'disk.file.get.json') && $r['auth'] === 'actor-access');

        // Only files of that message.
        $this->get("/api/v1/task-chat-messages/{$message->id}/files/999/download")->assertForbidden();
    }

    public function test_a_task_outside_bitrix_keeps_its_chat_here(): void
    {
        $this->task->project->forceFill(['bitrix_group_id' => null])->save();
        $this->task->forceFill(['bitrix_task_id' => null])->save();

        $this->postJson("/api/v1/tasks/{$this->task->id}/chat", ['text' => 'Local only'])->assertOk();
        $message = TaskChatMessage::sole();
        $this->putJson("/api/v1/task-chat-messages/{$message->id}", ['text' => 'Local edit'])->assertJsonPath('data.0.text', 'Local edit');
        $this->deleteJson("/api/v1/task-chat-messages/{$message->id}")->assertJsonCount(0, 'data');

        Http::assertNothingSent();
    }

    public function test_the_nightly_pass_reads_every_chat_to_the_start(): void
    {
        for ($i = 0; $i < 60; $i++) {
            $this->say(6, "Message {$i}");
        }
        $this->chat()->assertJsonCount(50, 'data');

        // An old message deleted in Bitrix is outside the newest page: only the full pass sees it.
        unset($this->messages[1001]);
        app()->call([new ImportBitrixChat, 'handle']);

        $this->assertSame(59, TaskChatMessage::count());
        $this->assertNull(TaskChatMessage::where('bitrix_message_id', 1001)->first());
    }

    private function user(int $bitrixId, string $name, Role $role): User
    {
        $user = User::factory()->create(['bitrix_user_id' => $bitrixId, 'name' => ucfirst($name)]);
        $user->roles()->attach($role);
        $user->storeBitrixToken(new BitrixToken("{$name}-access", "{$name}-refresh", CarbonImmutable::now()->addHour()));

        return $user;
    }

    private function bitrix(string $method, array $data): mixed
    {
        return match ($method) {
            'tasks.task.get' => ['item' => ['id' => 15, 'chatId' => self::CHAT]],
            'im.dialog.messages.get' => $this->page($data),
            'im.message.add' => $this->say(self::TOKENS[$data['auth']], $data['MESSAGE']),
            'im.message.update' => (bool) ($this->messages[(int) $data['ID']]['text'] = $data['MESSAGE']),
            'im.message.delete' => (function () use ($data): true {
                unset($this->messages[(int) $data['ID']]);

                return true;
            })(),
            'disk.file.get' => ['ID' => $data['id'], 'DOWNLOAD_URL' => "https://portal.test/download/disk/{$data['id']}?auth=secret"],
            default => true,
        };
    }

    /** im.dialog.messages.get: newest first, older than LAST_ID, LIMIT of them. */
    private function page(array $data): array
    {
        $rows = collect($this->messages)->sortKeysDesc()
            ->filter(fn ($row, $id): bool => ! isset($data['LAST_ID']) || $id < (int) $data['LAST_ID'])
            ->take((int) ($data['LIMIT'] ?? 20))
            ->values()->all();

        return ['chat_id' => self::CHAT, 'messages' => $rows, 'users' => [], 'files' => $this->files];
    }

    private function say(int $authorBitrixId, string $text, array $params = []): int
    {
        $id = ($this->messages === [] ? 1000 : max(array_keys($this->messages))) + 1;
        $this->messages[$id] = ['id' => $id, 'chat_id' => self::CHAT, 'author_id' => $authorBitrixId,
            'date' => '2026-11-17T09:00:00+00:00', 'text' => $text, 'params' => $params];
        if ($this->beforeAdd instanceof Closure) {
            ($this->beforeAdd)($id);
        }

        return $id;
    }

    private function chat(): TestResponse
    {
        return $this->getJson("/api/v1/tasks/{$this->task->id}/chat");
    }
}
