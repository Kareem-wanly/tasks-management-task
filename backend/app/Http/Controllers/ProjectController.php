<?php

namespace App\Http\Controllers;

use App\Http\Requests\AddProjectMemberRequest;
use App\Http\Requests\StoreProjectRequest;
use App\Http\Requests\UpdateProjectRequest;
use App\Http\Resources\ProjectResource;
use App\Models\ActivityLog;
use App\Models\Project;
use App\Models\User;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ProjectController extends Controller
{
    use AuthorizesRequests;

    public function index(Request $request)
    {
        $this->authorize('viewAny', Project::class);

        $user = $request->user();
        $query = Project::query();

        $query->accessibleBy($user);

        if ($request->filled('search')) {
            $search = str_replace(['\\', '%', '_'], ['\\\\', '\%', '\_'], $request->query('search'));
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
            $query->whereHas('members', fn ($q) => $q->where('users.id', $memberId));
        }

        $sortBy = $request->query('sort', 'created_at');
        $direction = strtolower($request->query('direction', 'desc')) === 'asc' ? 'asc' : 'desc';

        $allowedSorts = ['created_at', 'updated_at', 'start_date', 'due_date', 'title'];
        if (in_array($sortBy, $allowedSorts)) {
            $query->orderBy($sortBy, $direction);
        }

        if ($request->has('per_page') && ! is_numeric($request->query('per_page'))) {
            return response()->json([
                'message' => 'The per_page parameter must be an integer.',
            ], 422);
        }

        $perPage = min(max((int) $request->query('per_page', 15), 1), 100);
        $projects = $query->with('owner:id,name,email')
            ->withCount(['members', 'tasks'])
            ->paginate($perPage);

        return ProjectResource::collection($projects);
    }

    public function show(Project $project): ProjectResource
    {
        $this->authorize('view', $project);

        return new ProjectResource($project->load(['owner:id,name,email', 'members:id,name,email']));
    }

    public function store(StoreProjectRequest $request): JsonResponse
    {
        $this->authorize('create', Project::class);

        $validated = $request->validated();
        $title = $validated['title'] ?? null;

        $project = DB::transaction(function () use ($request, $validated, $title) {
            $project = Project::create([
                'title' => $title,
                'description' => $validated['description'] ?? null,
                'start_date' => $validated['start_date'] ?? null,
                'due_date' => $validated['due_date'] ?? null,
                'owner_id' => $request->user()->id,
                'status' => $validated['status'] ?? 'active',
            ]);

            $project->members()->attach($request->user()->id);

            return $project;
        });

        return (new ProjectResource($project->load(['owner:id,name,email', 'members:id,name,email'])))
            ->response()
            ->setStatusCode(201);
    }

    public function update(UpdateProjectRequest $request, Project $project): ProjectResource|JsonResponse
    {
        $this->authorize('update', $project);

        $validated = $request->validated();

        if (isset($validated['status']) && $validated['status'] === 'completed') {
            $hasUnfinishedTasks = $project->tasks()
                ->where('status', '!=', 'completed')
                ->exists();

            if ($hasUnfinishedTasks) {
                return response()->json([
                    'message' => 'Cannot complete project with unfinished tasks.',
                    'errors' => [
                        'status' => ['Project has pending or in-progress tasks that must be completed first.'],
                    ],
                ], 422);
            }
        }

        $project->update($validated);

        return new ProjectResource($project->fresh(['owner:id,name,email', 'members:id,name,email']));
    }

    public function destroy(Project $project): JsonResponse
    {
        $this->authorize('delete', $project);

        $project->delete();

        return response()->json([
            'success' => true,
            'message' => 'Project deleted successfully',
            'data' => null,
        ]);
    }

    public function members(Project $project): JsonResponse
    {
        $this->authorize('view', $project);

        $members = $project->members()->get(['users.id', 'name', 'email']);

        return response()->json([
            'success' => true,
            'message' => 'Project members retrieved successfully',
            'data' => $members,
        ]);
    }

    public function addMember(AddProjectMemberRequest $request, Project $project): JsonResponse
    {
        $validated = $request->validated();

        $userId = (int) $validated['user_id'];
        $role = $validated['role'] ?? 'member';

        if ($project->members()->where('users.id', $userId)->exists()) {
            return response()->json([
                'success' => false,
                'message' => 'User is already a member of this project.',
                'errors' => [
                    'user_id' => ['The specified user is already attached to this project.'],
                ],
            ], 422);
        }

        $project->members()->attach($userId, ['role' => $role]);

        ActivityLog::create([
            'user_id' => $request->user()->id,
            'project_id' => $project->id,
            'action' => 'member_added',
            'description' => "User ID {$userId} was added to project '{$project->title}'.",
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Member added to project successfully',
            'data' => $project->load(['owner:id,name,email', 'members:id,name,email']),
        ], 200);
    }

    public function removeMember(Request $request, Project $project, User $user): JsonResponse
    {
        $this->authorize('manageMembers', $project);

        if (! $project->members()->where('users.id', $user->id)->exists()) {
            return response()->json([
                'success' => false,
                'message' => 'User is not a member of this project.',
                'errors' => [
                    'user_id' => ['The specified user is not attached to this project.'],
                ],
            ], 404);
        }

        $project->members()->detach($user->id);

        ActivityLog::create([
            'user_id' => $request->user()->id,
            'project_id' => $project->id,
            'action' => 'member_removed',
            'description' => "{$user->name} was removed from project '{$project->title}'.",
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Member removed from project successfully',
            'data' => $project->load(['owner:id,name,email', 'members:id,name,email']),
        ]);
    }

    public function toggleArchive(Request $request, Project $project): JsonResponse
    {
        $this->authorize('archive', $project);

        $newStatus = $project->status === 'archived' ? 'active' : 'archived';

        $project->update([
            'status' => $newStatus,
        ]);

        return response()->json([
            'success' => true,
            'message' => "Project status changed to {$newStatus} successfully",
            'data' => $project->fresh(['owner:id,name,email', 'members:id,name,email']),
        ]);
    }

    public function activities(Request $request, Project $project): JsonResponse
    {
        $this->authorize('view', $project);

        $perPage = min(max((int) $request->query('per_page', 15), 1), 100);

        $activities = ActivityLog::where('project_id', $project->id)
            ->with('user:id,name,email')
            ->latest()
            ->paginate($perPage);

        return response()->json([
            'success' => true,
            'message' => 'Project activity logs retrieved successfully',
            'data' => $activities->items(),
            'meta' => [
                'current_page' => $activities->currentPage(),
                'last_page' => $activities->lastPage(),
                'per_page' => $activities->perPage(),
                'total' => $activities->total(),
            ],
        ]);
    }

    public function allActivities(Request $request): JsonResponse
    {
        $this->authorize('viewAny', ActivityLog::class);

        $query = ActivityLog::with('user:id,name,email')->latest();

        if (! $request->user()->hasRole('Administrator')) {
            $query->whereHas('project', function ($q) use ($request) {
                $q->where('owner_id', $request->user()->id)
                    ->orWhereHas('members', fn ($m) => $m->where('users.id', $request->user()->id));
            });
        }

        $perPage = min(max((int) $request->query('per_page', 15), 1), 100);
        $activities = $query->paginate($perPage);

        return response()->json([
            'success' => true,
            'message' => 'All activity logs retrieved successfully',
            'data' => $activities->items(),
            'meta' => [
                'current_page' => $activities->currentPage(),
                'last_page' => $activities->lastPage(),
                'per_page' => $activities->perPage(),
                'total' => $activities->total(),
            ],
        ]);
    }

    public function options(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Project::class);

        $query = Project::query();

        $query->accessibleBy($request->user());

        $projects = $query->where('status', '!=', 'archived')
            ->orderBy('title')
            ->limit(1000)
            ->get(['id', 'title', 'status']);

        return response()->json([
            'data' => $projects,
        ]);
    }
}
