<?php

namespace App\Http\Controllers;

use App\Enums\TaskStatus;
use App\Http\Requests\StoreTaskRequest;
use App\Http\Requests\UpdateTaskRequest;
use App\Http\Resources\TaskResource;
use App\Models\Project;
use App\Models\Task;
use App\Models\User;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

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

        if ($request->filled('due_date')) {
            $query->whereDate('due_date', $request->query('due_date'));
        }

        if ($request->filled('due_date_to')) {
            $query->whereDate('due_date', '<=', $request->query('due_date_to'));
        }

        if ($request->boolean('overdue') || $request->query('overdue') === 'true') {
            $query->where('status', '!=', 'completed')
                ->whereNotNull('due_date')
                ->whereDate('due_date', '<', now()->toDateString());
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

    public function store(Request $request): JsonResponse
    {
        $this->authorize('create', Task::class);

        $validated = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'status' => ['required', Rule::enum(TaskStatus::class)],
            'priority' => ['sometimes', 'string', 'in:low,medium,high,urgent'],
            'project_id' => ['required', 'integer', 'exists:projects,id'],
            'assigned_to' => ['nullable', 'integer', 'exists:users,id'],
            'due_date' => ['nullable', 'date'],
        ]);

        $project = Project::findOrFail($validated['project_id']);

        if (! $request->user()->hasRole('Administrator') &&
            $project->owner_id !== $request->user()->id &&
            ! $project->members()->where('users.id', $request->user()->id)->exists()) {
            return response()->json(['message' => 'You are not a member of this project.'], 403);
        }

        if (! empty($validated['assigned_to'])) {
            $assignee = User::find($validated['assigned_to']);
            $isAssigneeValid = ($assignee && $assignee->hasRole('Administrator')) ||
                               $project->owner_id === (int) $validated['assigned_to'] ||
                               $project->members()->where('users.id', $validated['assigned_to'])->exists();

            if (! $isAssigneeValid) {
                return response()->json([
                    'message' => 'The given data was invalid.',
                    'errors' => ['assigned_to' => ['The assigned user must be a member of the project.']],
                ], 422);
            }
        }

        if (! empty($validated['due_date']) && ! empty($project->due_date)) {
            if (strtotime($validated['due_date']) > strtotime($project->due_date)) {
                return response()->json([
                    'message' => 'The given data was invalid.',
                    'errors' => ['due_date' => ['Task due date cannot exceed project due date.']],
                ], 422);
            }
        }

        $completedAt = ($validated['status'] === 'completed') ? now() : null;
        $task = Task::create($validated + [
            'created_by' => $request->user()->id,
            'completed_at' => $completedAt,
        ]);

        // $task = Task::create($validated + ['created_by' => $request->user()->id]);

        return response()->json([
            'message' => 'Task created successfully',
            'data' => $task,
        ], 201);
    }

    public function storeByProject(StoreTaskRequest $request, Project $project): TaskResource
    {
        $this->authorize('update', $project);

        $validated = $request->validated();
        $validated['project_id'] = $project->id;
        $validated['created_by'] = $request->user()->id;

        $task = Task::create($validated);

        return new TaskResource($task->load(['project:id,title', 'assignee:id,name,email']));
    }

    public function update(UpdateTaskRequest $request, Task $task): TaskResource
    {
        $this->authorize('update', $task);

        $task->update($request->validated());

        return new TaskResource($task->fresh(['project:id,title', 'assignee:id,name,email']));
    }

    public function updateStatus(Request $request, Task $task): JsonResponse
    {
        // fix: Check if the user is authorized to update the status of the task
        $this->authorize('updateStatus', $task);

        $validated = $request->validate([
            'status' => ['required', Rule::enum(TaskStatus::class)],
        ]);

        $completedAt = $task->completed_at;

        if ($validated['status'] === 'completed' && $task->status !== 'completed') {

            $completedAt = now();
        } elseif ($validated['status'] !== 'completed' && $task->status === 'completed') {

            $completedAt = null;
        }

        $task->update([
            'status' => $validated['status'],
            'completed_at' => $completedAt,
        ]);

        return response()->json([
            'message' => 'Task status updated successfully',
            'data' => $task,
        ]);
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
