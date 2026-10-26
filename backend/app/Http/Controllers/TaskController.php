<?php

namespace App\Http\Controllers;

use App\Enums\TaskStatus;
use App\Http\Requests\StoreTaskRequest;
use App\Http\Requests\UpdateTaskRequest;
use App\Http\Resources\TaskResource;
use App\Models\Project;
use App\Models\Task;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use App\Http\Requests\UpdateTaskStatusRequest;


class TaskController extends Controller
{
    use AuthorizesRequests;

    public function index(Request $request)
    {
        $this->authorize('viewAny', Task::class);

        $query = Task::with(['project:id,title', 'assignee:id,name,email']);

        if ($request->filled('search')) {
            $search = str_replace(['\\', '%', '_'], ['\\\\', '\%', '\_'], $request->query('search'));
            $query->where('title', 'like', "%{$search}%");
        }

        if ($request->filled('status')) {
            $query->where('status', $request->query('status'));
        }

        if ($request->filled('priority')) {
            $query->where('priority', $request->query('priority'));
        }

        $user = $request->user();

        if (! $user->hasRole('Administrator')) {
            $query->whereHas('project', function ($q) use ($user) {
                $q->where('owner_id', $user->id)
                    ->orWhereHas('members', fn ($m) => $m->where('users.id', $user->id));
            });
        }

        if ($request->filled('assigned_to')) {
            $query->where('assigned_to', $request->query('assigned_to'));
        }

        if ($request->filled('project_id')) {
            $query->where('project_id', $request->query('project_id'));
        }

        if ($request->filled('due_date_from')) {
            $query->whereDate('due_date', '>=', $request->query('due_date_from'));
        }

        if ($request->filled('due_date_to')) {
            $query->whereDate('due_date', '<=', $request->query('due_date_to'));
        }

        if ($request->boolean('overdue') || $request->query('overdue') === 'true') {
            $query->where('status', '!=', 'completed')
                ->whereNotNull('due_date')
                ->whereDate('due_date', '<', now()->toDateString());
        }

        if ($request->has('per_page') && ! is_numeric($request->query('per_page'))) {
            return response()->json([
                'message' => 'The per_page parameter must be an integer.',
            ], 422);
        }

        $sortBy = $request->query('sort_by', 'created_at');
        $sortOrder = strtolower($request->query('sort_order', 'desc'));

        $allowedSorts = ['created_at', 'due_date', 'priority', 'title', 'status'];
        $sortBy = in_array($sortBy, $allowedSorts) ? $sortBy : 'created_at';
        $sortOrder = in_array($sortOrder, ['asc', 'desc']) ? $sortOrder : 'desc';

        $query->orderBy($sortBy, $sortOrder);

        $perPage = min(max((int) $request->query('per_page', 15), 1), 100);
        $tasks = $query->paginate($perPage);

        return TaskResource::collection($tasks);
    }

    public function indexByProject(Request $request, Project $project): JsonResponse
    {
        $this->authorize('view', $project);

        $perPage = min(max((int) $request->query('per_page', 15), 1), 100);

        $tasks = $project->tasks()
            ->with('assignee:id,name,email')
            ->latest()
            ->paginate($perPage);

        return response()->json($tasks);
    }

    public function show(Request $request, Task $task): TaskResource
    {
        $this->authorize('view', $task);

        $relations = ['project:id,title', 'assignee:id,name,email'];

        if ($request->user()->hasPermission('comments.view')) {
            $relations[] = 'comments.user:id,name,email';
        }

        return new TaskResource($task->load($relations));
    }

    public function storeByProject(StoreTaskRequest $request, Project $project): TaskResource
    {
        $this->authorize('create', [Task::class, $project]);

        if ($request->filled('assigned_to')) {
            $this->authorize('assign', [new Task(['project_id' => $project->id]), $project]);
        }

        $validated = $request->validated();
        $validated['project_id'] = $project->id;
        $validated['created_by'] = $request->user()->id;

        $task = Task::create($validated);

        return new TaskResource($task->load(['project:id,title', 'assignee:id,name,email']));
    }

    public function update(UpdateTaskRequest $request, Task $task): TaskResource
    {
        $this->authorize('update', $task);

        if ($request->has('assigned_to') && $request->validated('assigned_to') !== $task->assigned_to) {
            $this->authorize('assign', $task);
        }

        $task->update($request->validated());

        return new TaskResource($task->fresh(['project:id,title', 'assignee:id,name,email']));
    }

    public function updateStatus(UpdateTaskStatusRequest $request, Task $task): TaskResource
{
    $this->authorize('updateStatus', $task);

    $task->update([
        'status' => $request->validated('status'),
    ]);

    return new TaskResource($task->fresh(['project:id,title', 'assignee:id,name,email']));
}

    public function destroy(Task $task): JsonResponse
    {
        $this->authorize('delete', $task);

        $task->delete();

        return response()->json([
            'message' => 'Task deleted successfully',
        ]);
    }
}
