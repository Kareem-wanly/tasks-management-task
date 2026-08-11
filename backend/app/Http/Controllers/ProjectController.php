<?php

namespace App\Http\Controllers;

use App\Models\Project;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;

class ProjectController extends Controller
{
    use AuthorizesRequests;

    public function index(Request $request): JsonResponse
{
    $this->authorize('viewAny', Project::class);

    $user = $request->user();
    $query = Project::query();

    if (!$user->hasRole('Administrator')) {
        $query->where(function ($q) use ($user) {
            $q->where('owner_id', $user->id)
              ->orWhereHas('members', fn($m) => $m->where('users.id', $user->id));
        });
    }

    
    if ($request->filled('search')) {
        $search = $request->query('search');
        $query->where(function ($q) use ($search) {
            $q->where('title', 'like', "%{$search}%")
              ->orWhere('description', 'like', "%{$search}%");
        });
    }

    
    if ($request->filled('status')) {
        $query->where('status', $request->query('status'));
    }

    
    if ($request->filled('member_id')) {
        $memberId = $request->query('member_id');
        $query->whereHas('members', fn($q) => $q->where('users.id', $memberId));
    }

    
    $sortBy = $request->query('sort', 'created_at');
    $direction = strtolower($request->query('direction', 'desc')) === 'asc' ? 'asc' : 'desc';
    
    $allowedSorts = ['created_at', 'updated_at', 'start_date', 'due_date', 'title'];
    if (in_array($sortBy, $allowedSorts)) {
        $query->orderBy($sortBy, $direction);
    }

    
    $perPage = (int) $request->query('per_page', 15);
    $projects = $query->with(['owner:id,name,email', 'members:id,name,email'])
                      ->paginate($perPage);

    return response()->json([
        'success' => true,
        'message' => 'Projects retrieved successfully',
        'data'    => $projects->items(),
        'meta'    => [
            'current_page' => $projects->currentPage(),
            'last_page'    => $projects->lastPage(),
            'per_page'     => $projects->perPage(),
            'total'        => $projects->total(),
        ],
        'links'   => [
            'first' => $projects->url(1),
            'last'  => $projects->url($projects->lastPage()),
            'prev'  => $projects->previousPageUrl(),
            'next'  => $projects->nextPageUrl(),
        ]
    ]);
}

    public function show(Project $project): JsonResponse
{
    
    $this->authorize('view', $project);

    
    return response()->json([
        'success' => true,
        'message' => 'Project details retrieved successfully',
        'data'    => $project->load([
            'owner:id,name,email',
            'members:id,name,email'
        ])
    ]);
}

    public function store(Request $request): JsonResponse
{
    
    $this->authorize('create', Project::class);

    
    $validated = $request->validate([
        'title'       => ['required_without:name', 'nullable', 'string', 'max:255'],
        'name'        => ['required_without:title', 'nullable', 'string', 'max:255'],
        'description' => ['nullable', 'string'],
        'start_date'  => ['nullable', 'date'],
        'due_date'    => ['nullable', 'date', 'after_or_equal:start_date'],
    ]);

    $title = $validated['title'] ?? $validated['name'];

    
    $project = Project::create([
        'title'       => $title,
        'description' => $validated['description'] ?? null,
        'start_date'  => $validated['start_date'] ?? null,
        'due_date'    => $validated['due_date'] ?? null,
        'owner_id'    => $request->user()->id,
        'status'      => 'active',
    ]);

    
    $project->members()->attach($request->user()->id);

    
    return response()->json([
        'success' => true,
        'message' => 'Project created successfully',
        'data'    => $project->load(['owner:id,name,email', 'members:id,name,email'])
    ], 201);
}

    public function update(Request $request, Project $project): JsonResponse
{
    
    $this->authorize('update', $project);

    
    $validated = $request->validate([
        'title'       => ['sometimes', 'nullable', 'string', 'max:255'],
        'name'        => ['sometimes', 'nullable', 'string', 'max:255'],
        'description' => ['nullable', 'string'],
        'status'      => ['sometimes', 'string', 'in:active,completed,archived'],
        'start_date'  => ['nullable', 'date'],
        'due_date'    => ['nullable', 'date', 'after_or_equal:start_date'],
    ]);

    if (isset($validated['name']) && !isset($validated['title'])) {
        $validated['title'] = $validated['name'];
        unset($validated['name']);
    }

    
    if (isset($validated['status']) && $validated['status'] === 'completed') {
        $hasUnfinishedTasks = $project->tasks()
            ->where('status', '!=', 'completed')
            ->exists();

        if ($hasUnfinishedTasks) {
            return response()->json([
                'success' => false,
                'message' => 'Cannot complete project with unfinished tasks.',
                'errors'  => [
                    'status' => ['Project has pending or in-progress tasks that must be completed first.']
                ]
            ], 422);
        }
    }

    
    $project->update($validated);

    return response()->json([
        'success' => true,
        'message' => 'Project updated successfully',
        'data'    => $project->fresh(['owner:id,name,email', 'members:id,name,email'])
    ]);
}

    public function destroy(Project $project): JsonResponse
{
    
    $this->authorize('delete', $project);

    
    $project->delete();

    
    return response()->json([
        'success' => true,
        'message' => 'Project deleted successfully',
        'data'    => null
    ]);
}
    public function getMembers(Project $project): JsonResponse
{
    $this->authorize('view', $project);

    return response()->json([
        'success' => true,
        'message' => 'Project members retrieved successfully',
        'data'    => $project->members()->get(['users.id', 'name', 'email'])
    ]);
}

    public function addMember(Request $request, Project $project): JsonResponse
{
    
    $this->authorize('update', $project);

    $validated = $request->validate([
        'user_id' => ['required', 'integer', 'exists:users,id'],
    ]);

    $userId = (int) $validated['user_id'];

    
    if ($project->members()->where('users.id', $userId)->exists()) {
        return response()->json([
            'success' => false,
            'message' => 'User is already a member of this project.',
            'errors'  => [
                'user_id' => ['The specified user is already attached to this project.']
            ]
        ], 422);
    }

    
    $project->members()->attach($userId);

    
    if (class_exists(\App\Models\ActivityLog::class)) {
        \App\Models\ActivityLog::create([
            'user_id'     => $request->user()->id,
            'project_id'  => $project->id,
            'action'      => 'member_added',
            'description' => "User ID {$userId} was added to project '{$project->title}' by User ID {$request->user()->id}.",
        ]);
    }

    return response()->json([
        'success' => true,
        'message' => 'Member added to project successfully',
        'data'    => $project->load(['owner:id,name,email', 'members:id,name,email'])
    ], 200);
}

    public function removeMember(Request $request, Project $project, int $userId): JsonResponse
{
    
    $this->authorize('update', $project);


    if (!$project->members()->where('users.id', $userId)->exists()) {
        return response()->json([
            'success' => false,
            'message' => 'User is not a member of this project.',
            'errors'  => [
                'user_id' => ['The specified user is not attached to this project.']
            ]
        ], 404);
    }

    
    $project->members()->detach($userId);

    
    if (class_exists(\App\Models\ActivityLog::class)) {
        \App\Models\ActivityLog::create([
            'user_id'     => $request->user()->id,
            'project_id'  => $project->id,
            'action'      => 'member_removed',
            'description' => "User ID {$userId} was removed from project '{$project->title}' by User ID {$request->user()->id}.",
        ]);
    }

    return response()->json([
        'success' => true,
        'message' => 'Member removed from project successfully',
        'data'    => $project->load(['owner:id,name,email', 'members:id,name,email'])
    ]);
}

    public function archive(Request $request, Project $project): JsonResponse
{
    
    $this->authorize('update', $project);

    
    $newStatus = $project->status === 'archived' ? 'active' : 'archived';

    $project->update([
        'status' => $newStatus
    ]);

    return response()->json([
        'success' => true,
        'message' => "Project status changed to {$newStatus} successfully",
        'data'    => $project->fresh(['owner:id,name,email', 'members:id,name,email'])
    ]);
}

    public function activities(Project $project): JsonResponse
{
    $this->authorize('view', $project);

    $activities = \App\Models\ActivityLog::where('project_id', $project->id)
        ->with('user:id,name,email')
        ->latest()
        ->get();

    return response()->json([
        'success' => true,
        'message' => 'Project activity logs retrieved successfully',
        'data'    => $activities
    ]);
}

    public function allActivities(Request $request): JsonResponse
{
    $this->authorize('viewAny', Project::class);

    $activities = \App\Models\ActivityLog::with('user:id,name,email')
        ->latest()
        ->paginate((int) $request->query('per_page', 15));

    return response()->json([
        'success' => true,
        'message' => 'All activity logs retrieved successfully',
        'data'    => $activities->items(),
        'meta'    => [
            'current_page' => $activities->currentPage(),
            'last_page'    => $activities->lastPage(),
            'per_page'     => $activities->perPage(),
            'total'        => $activities->total(),
        ]
    ]);
}


}