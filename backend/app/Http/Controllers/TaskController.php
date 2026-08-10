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

        $tasks = Task::with(['project:id,title', 'assignee:id,name,email'])->get();

        return response()->json(['data' => $tasks]);
    }

    public function indexByProject(Project $project): JsonResponse
    {
        $this->authorize('view', $project);

        return response()->json(['data' => $project->tasks()->with('assignee:id,name,email')->get()]);
    }

    public function show(Task $task): JsonResponse
    {
        $this->authorize('view', $task);

        return response()->json([
            'data' => $task->load(['project:id,title', 'assignee:id,name,email'])
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $this->authorize('create', Task::class);

        $validated = $request->validate([
            'title'       => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'status'      => ['required', 'string', 'in:todo,in_progress,review,completed'],
            'priority'    => ['sometimes', 'string', 'in:low,medium,high'],
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

        $task = Task::create($validated + ['created_by' => $request->user()->id]);

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
            'assigned_to' => ['nullable', 'integer', 'exists:users,id'],
            'due_date'    => ['nullable', 'date'],
        ]);

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

        $task->update(['status' => $validated['status']]);

        return response()->json(['message' => 'Task status updated successfully', 'data' => $task]);
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