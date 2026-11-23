<?php

declare(strict_types=1);

namespace Tests\Feature\Bitrix24;

use App\Integrations\Bitrix24\BitrixToken;
use App\Jobs\Bitrix\ImportBitrixFilesJob;
use App\Models\Permission;
use App\Models\Project;
use App\Models\Role;
use App\Models\Task;
use App\Models\TaskFile;
use App\Models\User;
use Carbon\CarbonImmutable;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Log\Events\MessageLogged;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Sleep;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;
use Throwable;

/**
 * Step 4.5: files attached to a task.
 */
class BitrixTaskFilesTest extends TestCase
{
    use RefreshDatabase;

    private User $actor;

    private Task $task;

    /** @var array<int, array<string, mixed>> Attachments of task 15 in the fake Bitrix, by attached object ID. */
    private array $attached = [];

    /** Children of the employee's Drive root (e.g. the "Uploaded files" folder). */
    private array $driveChildren = [];

    private bool $unreachable = false;

    /** Only the download host fails, with Guzzle quoting the tokenised link. */
    private bool $downloadUnreachable = false;

    /** The download host answers with this error status instead of the file. */
    private ?int $downloadStatus = null;

    protected function setUp(): void
    {
        parent::setUp();
        config(['bitrix.portal' => 'portal.test']);
        $this->seed(RolesAndPermissionsSeeder::class);

        $this->actor = $this->user(5, 'actor', Role::where('name', 'Administrator')->first());
        $this->useBitrixServiceAccount($this->actor);

        $project = Project::forceCreate(['title' => 'P', 'status' => 'active', 'owner_id' => $this->actor->id, 'bitrix_group_id' => 3]);
        $this->task = Task::forceCreate([
            'title' => 'T', 'status' => 'todo', 'priority' => 'medium', 'project_id' => $project->id,
            'created_by' => $this->actor->id, 'assigned_to' => $this->actor->id, 'bitrix_task_id' => 15,
        ]);

        Http::fake(function (Request $request) {
            if ($this->unreachable) {
                throw new ConnectionException('timed out');
            }
            if (str_starts_with($request->url(), 'https://portal.test/download/')) {
                if ($this->downloadUnreachable) {
                    throw new ConnectionException("cURL error 28: Operation timed out for {$request->url()}");
                }
                if ($this->downloadStatus !== null) {
                    return Http::response('<html>Error page</html>', $this->downloadStatus, ['Content-Type' => 'text/html']);
                }

                return Http::response('file-bytes', 200, ['Content-Type' => 'application/octet-stream']);
            }
            // Second upload step: the file itself, as multipart, to the URL Bitrix gave.
            if (str_starts_with($request->url(), 'https://portal.test/rest/upload.json')) {
                return Http::response(['result' => ['ID' => 197, 'NAME' => collect($request->data())->firstWhere('name', 'file')['filename'] ?? '']]);
            }

            return Http::response(['result' => $this->bitrix(basename(parse_url($request->url(), PHP_URL_PATH), '.json'), $request->data())]);
        });
        Sanctum::actingAs($this->actor);
    }

    public function test_opening_the_task_brings_in_its_bitrix_files(): void
    {
        $this->attach(24, 197, 'plan.pdf', 5);

        $this->getJson("/api/v1/tasks/{$this->task->id}/files")
            ->assertOk()
            ->assertJsonPath('data.0.name', 'plan.pdf')
            ->assertJsonPath('data.0.size', 2048)
            ->assertJsonPath('data.0.mime', 'application/pdf')
            ->assertJsonPath('data.0.uploader.id', $this->actor->id)
            ->assertJsonPath('meta.can_manage', true)
            ->assertJsonMissing(['auth' => 'secret']);
    }

    public function test_a_file_goes_to_the_employees_uploaded_files_folder_then_onto_the_task(): void
    {
        $this->driveChildren = [['ID' => '41', 'CODE' => 'FOR_UPLOADED_FILES']];

        $this->post("/api/v1/tasks/{$this->task->id}/files", ['file' => UploadedFile::fake()->create('report.pdf', 100)], ['Accept' => 'application/json'])
            ->assertOk()
            ->assertJsonPath('data.0.name', 'report.pdf');

        Http::assertSent(fn (Request $r): bool => str_ends_with($r->url(), 'disk.folder.uploadfile.json')
            && $r['auth'] === 'actor-access' && $r['id'] === 41 && ! isset($r['fileContent']) && $r['generateUniqueName'] === true);
        // Review C4: the file goes as multipart streamed from disk, not as base64 in JSON.
        Http::assertSent(fn (Request $r): bool => str_starts_with($r->url(), 'https://portal.test/rest/upload.json')
            && $r->isMultipart() && collect($r->data())->firstWhere('name', 'file')['filename'] === 'report.pdf');
        Http::assertSent(fn (Request $r): bool => str_ends_with($r->url(), 'tasks.task.files.attach.json') && $r['taskId'] === 15 && $r['fileId'] === 197);
    }

    public function test_without_an_uploaded_files_folder_the_file_goes_to_the_drive_root(): void
    {
        $this->post("/api/v1/tasks/{$this->task->id}/files", ['file' => UploadedFile::fake()->create('a.txt', 1)], ['Accept' => 'application/json'])->assertOk();

        Http::assertSent(fn (Request $r): bool => str_ends_with($r->url(), 'disk.folder.uploadfile.json') && $r['id'] === 38);
    }

    public function test_files_are_limited_to_20_mb(): void
    {
        $this->post("/api/v1/tasks/{$this->task->id}/files", ['file' => UploadedFile::fake()->create('big.zip', 20481)], ['Accept' => 'application/json'])
            ->assertJsonValidationErrors('file');

        Http::assertNothingSent();
    }

    public function test_removing_detaches_the_file_but_keeps_it_on_the_drive(): void
    {
        $this->attach(24, 197, 'a.pdf', 5);
        $this->attach(25, 198, 'b.pdf', 5);
        $this->getJson("/api/v1/tasks/{$this->task->id}/files");

        $file = TaskFile::where('attached_object_id', 24)->sole();
        $this->deleteJson("/api/v1/task-files/{$file->id}")->assertOk()->assertJsonCount(1, 'data');

        Http::assertSent(fn (Request $r): bool => str_ends_with($r->url(), 'tasks.task.update.json') && $r['fields'] === ['UF_TASK_WEBDAV_FILES' => [25]]);
        Http::assertNotSent(fn (Request $r): bool => str_contains($r->url(), 'disk.file.delete'));
    }

    /** Review C6: the list is read and written whole, so two changes to a task's files take turns. */
    public function test_a_removal_waits_for_another_change_to_the_files_and_then_gives_up(): void
    {
        Sleep::fake(syncWithCarbon: true);
        $this->attach(24, 197, 'a.pdf', 5);
        $this->getJson("/api/v1/tasks/{$this->task->id}/files");
        Cache::lock("bitrix-task-files:{$this->task->id}", 60)->get();

        $this->deleteJson('/api/v1/task-files/'.TaskFile::sole()->id)->assertUnprocessable();

        Http::assertNotSent(fn (Request $r): bool => str_ends_with($r->url(), 'tasks.task.update.json'));
        $this->assertSame(1, TaskFile::count());
    }

    /** Review C5: the link is asked for with the service account, after our own visibility check. */
    public function test_a_download_streams_the_file_through_the_service_account(): void
    {
        $this->attach(24, 197, 'plan.pdf', 5);
        $this->getJson("/api/v1/tasks/{$this->task->id}/files");
        $file = TaskFile::sole();

        $response = $this->get("/api/v1/task-files/{$file->id}/download");

        $response->assertOk()->assertHeader('content-disposition', 'attachment; filename=plan.pdf');
        $this->assertSame('file-bytes', $response->streamedContent());
        Http::assertSent(fn (Request $r): bool => str_ends_with($r->url(), 'disk.attachedObject.get.json') && $r['auth'] === 'actor-access');
    }

    public function test_a_failed_download_never_leaks_the_token_in_the_link(): void
    {
        $logged = [];
        Event::listen(MessageLogged::class, function (MessageLogged $log) use (&$logged): void {
            $logged[] = $log->message;
            foreach ($log->context as $value) {
                for ($e = $value instanceof Throwable ? $value : null; $e; $e = $e->getPrevious()) {
                    $logged[] = $e->getMessage();
                }
            }
        });
        $this->attach(24, 197, 'plan.pdf', 5);
        $this->getJson("/api/v1/tasks/{$this->task->id}/files");
        $this->downloadUnreachable = true;

        // The download is opened before the response starts, so the failure is a normal 503.
        $response = $this->getJson('/api/v1/task-files/'.TaskFile::sole()->id.'/download');

        $response->assertStatus(503);
        $this->assertStringNotContainsString('secret', (string) $response->getContent());
        $this->assertNotEmpty($logged);
        foreach ($logged as $line) {
            $this->assertStringNotContainsString('secret', $line);
        }
    }

    /** Review C1: an error page from Bitrix is reported as an error, never saved as the file. */
    public function test_an_error_from_the_download_host_is_an_error_not_the_file(): void
    {
        $this->attach(24, 197, 'plan.pdf', 5);
        $this->getJson("/api/v1/tasks/{$this->task->id}/files");
        $this->downloadStatus = 404;

        $response = $this->getJson('/api/v1/task-files/'.TaskFile::sole()->id.'/download');

        $response->assertUnprocessable()->assertJsonPath('message', 'Bitrix24 did not return the file (HTTP 404).');
        $this->assertStringNotContainsString('Error page', (string) $response->getContent());
    }

    public function test_auditors_download_but_only_the_people_on_the_task_attach(): void
    {
        $viewOnly = Role::create(['name' => 'Viewer', 'display_name' => 'Viewer', 'description' => 'x']);
        $viewOnly->permissions()->sync(Permission::where('name', 'tasks.view')->pluck('id'));
        $auditor = $this->user(8, 'auditor', $viewOnly);
        $this->task->auditors()->sync([$auditor->id]);
        $this->attach(24, 197, 'plan.pdf', 5);
        $this->getJson("/api/v1/tasks/{$this->task->id}/files");
        Sanctum::actingAs($auditor);

        $this->getJson("/api/v1/tasks/{$this->task->id}/files")->assertJsonPath('meta.can_manage', false);
        $this->get('/api/v1/task-files/'.TaskFile::sole()->id.'/download')->assertOk();
        $this->post("/api/v1/tasks/{$this->task->id}/files", ['file' => UploadedFile::fake()->create('x.txt', 1)], ['Accept' => 'application/json'])->assertForbidden();
    }

    public function test_bitrix_unreachable_shows_our_last_copy(): void
    {
        $this->attach(24, 197, 'plan.pdf', 5);
        $this->getJson("/api/v1/tasks/{$this->task->id}/files");
        $this->unreachable = true;

        $this->getJson("/api/v1/tasks/{$this->task->id}/files")->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('meta.stale', true);
    }

    public function test_a_task_outside_bitrix_keeps_its_files_here(): void
    {
        Storage::fake('local');
        $local = Project::forceCreate(['title' => 'Local', 'status' => 'active', 'owner_id' => $this->actor->id]);
        $this->task->forceFill(['project_id' => $local->id, 'bitrix_task_id' => null])->save();

        $this->post("/api/v1/tasks/{$this->task->id}/files", ['file' => UploadedFile::fake()->createWithContent('notes.txt', 'hello')], ['Accept' => 'application/json'])->assertOk();
        $file = TaskFile::sole();
        Storage::disk('local')->assertExists($file->path);
        $this->assertSame('hello', $this->get("/api/v1/task-files/{$file->id}/download")->streamedContent());

        $this->deleteJson("/api/v1/task-files/{$file->id}")->assertOk()->assertJsonCount(0, 'data');
        Storage::disk('local')->assertMissing($file->path);
        Http::assertNothingSent();
    }

    public function test_the_nightly_pass_brings_in_every_tasks_files(): void
    {
        $this->attach(24, 197, 'from-bitrix.docx', 5);

        dispatch_sync(new ImportBitrixFilesJob);

        $this->assertSame(['from-bitrix.docx'], TaskFile::pluck('name')->all());
    }

    private function user(int $bitrixId, string $name, Role $role): User
    {
        $user = User::factory()->create(['bitrix_user_id' => $bitrixId]);
        $user->roles()->attach($role);
        $this->giveBitrixToken($user, new BitrixToken("{$name}-access", "{$name}-refresh", CarbonImmutable::now()->addHour()));

        return $user;
    }

    private function bitrix(string $method, array $data): mixed
    {
        return match ($method) {
            'tasks.task.get' => ['task' => ['id' => '15', 'ufTaskWebdavFiles' => array_keys($this->attached)]],
            'disk.attachedObject.get' => $this->attached[(int) $data['id']] ?? null,
            'disk.storage.getlist' => [['ID' => '9', 'ROOT_OBJECT_ID' => '38']],
            'disk.storage.getchildren' => $this->driveChildren,
            // Without fileContent Bitrix answers where to send the file.
            'disk.folder.uploadfile' => ['field' => 'file', 'uploadUrl' => 'https://portal.test/rest/upload.json?auth=upload-auth&token=t1'],
            'tasks.task.files.attach' => ['attachmentId' => $this->attach(24, 197, 'report.pdf', 5)],
            'tasks.task.update' => (function () use ($data): true {
                $this->attached = array_intersect_key($this->attached, array_flip($data['fields']['UF_TASK_WEBDAV_FILES']));

                return true;
            })(),
            // Batch commands arrive as "method?query".
            'batch' => ['result' => array_map(function (string $cmd) {
                parse_str((string) parse_url($cmd, PHP_URL_QUERY), $params);

                return $this->bitrix((string) parse_url($cmd, PHP_URL_PATH), $params);
            }, $data['cmd']), 'result_error' => []],
            default => true,
        };
    }

    private function attach(int $attachedId, int $diskFileId, string $name, int $createdBy): int
    {
        $this->attached[$attachedId] = ['ID' => (string) $attachedId, 'OBJECT_ID' => (string) $diskFileId, 'NAME' => $name, 'SIZE' => '2048',
            'CREATED_BY' => (string) $createdBy, 'CREATE_TIME' => '2026-11-16T13:27:33+00:00', 'DOWNLOAD_URL' => "https://portal.test/download/{$attachedId}?auth=secret"];

        return $attachedId;
    }
}
