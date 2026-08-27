<?php

namespace App\Http\Controllers;

use App\Models\Task;
use App\Models\Project;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;

class TaskController extends Controller
{
    use AuthorizesRequests;
    
    public function index(Request $request): JsonResponse
{
    $this->authorize('viewAny', Task::class);

    $query = Task::with(['project:id,title', 'assignee:id,name,email']);

    if ($request->filled('search')) {
        $query->where('title', 'like', '%' . $request->query('search') . '%');
    }

    if ($request->filled('status')) {
        $query->where('status', $request->query('status'));
    }

    if ($request->filled('priority')) {
        $query->where('priority', $request->query('priority'));
    }

    $user = $request->user();

    $isAdmin = $user->role === 'admin' 
        || (isset($user->is_admin) && $user->is_admin) 
        || (is_object($user->role) && $user->role->name === 'admin');

    if (!$isAdmin) {
        $query->where('assigned_to', $user->id);
    } elseif ($request->filled('assigned_to')) {
        $query->where('assigned_to', $request->query('assigned_to'));
    }

    if ($request->filled('project_id')) {
        $query->where('project_id', $request->query('project_id'));
    }

    if ($request->filled('due_date')) {
        $query->whereDate('due_date', $request->query('due_date'));
    }

    if ($request->filled('due_date_from')) {
        $query->whereDate('due_date', '>=', $request->query('due_date_from'));
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

    $perPage = (int) $request->query('per_page', 15);
    $tasks = $query->paginate($perPage);

    $tasks->getCollection()->transform(function ($task) {
        $task->is_overdue = $task->due_date && $task->status !== 'completed' && $task->due_date < now();
        return $task;
    });

    return response()->json($tasks);
}

    public function indexByProject(Project $project): JsonResponse
    {
        $this->authorize('view', $project);

        return response()->json(['data' => $project->tasks()->with('assignee:id,name,email')->get()]);
    }

    public function show(Task $task): JsonResponse
{
    $this->authorize('view', $task);

    $taskData = $task->load(['project:id,title', 'assignee:id,name,email', 'comments.user:id,name,email'])->toArray();
    
    
    $taskData['is_overdue'] = $task->due_date && $task->status !== 'completed' && $task->due_date < now();

    return response()->json([
        'data' => $taskData
    ]);
}

    public function store(Request $request): JsonResponse
{
    $this->authorize('create', Task::class);

    $validated = $request->validate([
        'title'       => ['required', 'string', 'max:255'],
        'description' => ['nullable', 'string'],
        'status'      => ['required', 'string', 'in:todo,in_progress,review,completed'],
        'priority'    => ['sometimes', 'string', 'in:low,medium,high,urgent'],
        'project_id'  => ['required', 'integer', 'exists:projects,id'],
        'assigned_to' => ['nullable', 'integer', 'exists:users,id'],
        'due_date'    => ['nullable', 'date'],
    ]);

    $project = Project::findOrFail($validated['project_id']);

    if (!$request->user()->hasRole('Administrator') && 
        $project->owner_id !== $request->user()->id && 
        !$project->members()->where('users.id', $request->user()->id)->exists()) {
        return response()->json(['message' => 'You are not a member of this project.'], 403);
    }

    if (!empty($validated['assigned_to'])) {
        $assignee = \App\Models\User::find($validated['assigned_to']);
        $isAssigneeValid = ($assignee && $assignee->hasRole('Administrator')) ||
                           $project->owner_id === (int) $validated['assigned_to'] || 
                           $project->members()->where('users.id', $validated['assigned_to'])->exists();

        if (!$isAssigneeValid) {
            return response()->json([
                'message' => 'The given data was invalid.',
                'errors'  => ['assigned_to' => ['The assigned user must be a member of the project.']]
            ], 422);
        }
    }

    if (!empty($validated['due_date']) && !empty($project->due_date)) {
    if (strtotime($validated['due_date']) > strtotime($project->due_date)) {
        return response()->json([
            'message' => 'The given data was invalid.',
            'errors'  => ['due_date' => ['Task due date cannot exceed project due date.']]
        ], 422);
    }
    }

    $completedAt = ($validated['status'] === 'completed') ? now() : null;
        $task = Task::create($validated + [
        'created_by'   => $request->user()->id,
        'completed_at' => $completedAt,
        ]);

    //$task = Task::create($validated + ['created_by' => $request->user()->id]);

    return response()->json([
        'message' => 'Task created successfully',
        'data'    => $task
    ], 201);
}

    public function storeByProject(Request $request, Project $project): JsonResponse
    {
        $request->merge(['project_id' => $project->id]);
        return $this->store($request);
    }

    public function update(Request $request, Task $task): JsonResponse
{
    $this->authorize('update', $task);

    $validated = $request->validate([
        'title'       => ['sometimes', 'string', 'max:255'],
        'description' => ['nullable', 'string'],
        'status'      => ['sometimes', 'string', 'in:todo,in_progress,review,completed'],
        'priority'    => ['sometimes', 'string', 'in:low,medium,high,urgent'],
        'assigned_to' => ['nullable', 'integer', 'exists:users,id'],
        'due_date'    => ['nullable', 'date'],
    ]);

    if (array_key_exists('assigned_to', $validated) && !is_null($validated['assigned_to'])) {
        $project = $task->project;
        $assignee = \App\Models\User::find($validated['assigned_to']);
        $isAssigneeValid = ($assignee && $assignee->hasRole('Administrator')) ||
                           $project->owner_id === (int) $validated['assigned_to'] || 
                           $project->members()->where('users.id', $validated['assigned_to'])->exists();

        if (!$isAssigneeValid) {
            return response()->json([
                'message' => 'The given data was invalid.',
                'errors'  => ['assigned_to' => ['The assigned user must be a member of the project.']]
            ], 422);
        }
    }

    if (array_key_exists('due_date', $validated) && !is_null($validated['due_date'])) {
    $project = $task->project;
    if (!empty($project->due_date) && strtotime($validated['due_date']) > strtotime($project->due_date)) {
        return response()->json([
            'message' => 'The given data was invalid.',
            'errors'  => ['due_date' => ['Task due date cannot exceed project due date.']]
        ], 422);
    }
    }

    if (array_key_exists('status', $validated)) {
    if ($validated['status'] === 'completed' && $task->status !== 'completed') {
        $validated['completed_at'] = now();
    } elseif ($validated['status'] !== 'completed' && $task->status === 'completed') {
        $validated['completed_at'] = null;
    }
    }

    $task->update($validated);

    return response()->json([
        'message' => 'Task updated successfully',
        'data'    => $task
    ]);
}

    public function updateStatus(Request $request, Task $task): JsonResponse
{
    $this->authorize('update', $task);

    $validated = $request->validate([
        'status' => ['required', 'string', 'in:todo,in_progress,review,completed'],
    ]);


    $completedAt = $task->completed_at;

    if ($validated['status'] === 'completed' && $task->status !== 'completed') {

        $completedAt = now();
    } elseif ($validated['status'] !== 'completed' && $task->status === 'completed') {

        $completedAt = null;
    }

    $task->update([
        'status'       => $validated['status'],
        'completed_at' => $completedAt,
    ]);

    return response()->json([
        'message' => 'Task status updated successfully',
        'data'    => $task
    ]);
}

    public function destroy(Task $task): JsonResponse
    {
        $this->authorize('delete', $task);

        $task->delete();

        return response()->json([
            'message' => 'Task deleted successfully'
        ]);
    }
}