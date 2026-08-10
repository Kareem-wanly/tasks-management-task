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
        $status = $request->query('status', 'active'); 

        $query = Project::query();

        if (!$user->hasRole('Administrator')) {
            $query->where(function ($q) use ($user) {
                $q->where('owner_id', $user->id)
                  ->orWhereHas('members', fn($m) => $m->where('users.id', $user->id));
            });
        }

        if ($request->has('status')) {
            $query->where('status', $status);
        }

        $projects = $query->with(['owner:id,name,email', 'members:id,name,email'])->get();

        return response()->json([
            'data' => $projects
        ]);
    }

    public function show(Project $project): JsonResponse
    {
        $this->authorize('view', $project);

        return response()->json([
            'data' => $project->load(['owner:id,name,email', 'members:id,name,email'])
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $this->authorize('create', Project::class);

        $validated = $request->validate([
            'title'       => ['required_without:name', 'nullable', 'string', 'max:255'],
            'name'        => ['required_without:title', 'nullable', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'start_date'  => 'nullable|date',
        'due_date'    => 'nullable|date|after_or_equal:start_date',
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
            'message' => 'Project created successfully',
            'data'    => $project->load('members:id,name,email')
        ], 201);
    }

    public function update(Request $request, Project $project): JsonResponse
    {
        $this->authorize('update', $project);

        $validated = $request->validate([
            'title'       => ['sometimes', 'string', 'max:255'],
            'name'        => ['sometimes', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'status'      => ['sometimes', 'string', 'in:active,completed,archived'],
        ]);

        if (isset($validated['name']) && !isset($validated['title'])) {
            $validated['title'] = $validated['name'];
            unset($validated['name']);
        }

        $project->update($validated);

        return response()->json([
            'message' => 'Project updated successfully',
            'data'    => $project
        ]);
    }

    public function destroy(Project $project): JsonResponse
    {
        $this->authorize('delete', $project);

        $project->delete();

        return response()->json([
            'message' => 'Project deleted successfully'
        ]);
    }

    public function getMembers(Project $project): JsonResponse
    {
        $this->authorize('view', $project);

        return response()->json([
            'data' => $project->members()->get(['users.id', 'name', 'email'])
        ]);
    }

    public function addMember(Request $request, Project $project): JsonResponse
    {
        $this->authorize('update', $project);

        $validated = $request->validate([
            'user_id' => ['required', 'integer', 'exists:users,id'],
        ]);

        $project->members()->syncWithoutDetaching([$validated['user_id']]);

        return response()->json([
            'message' => 'Member added to project successfully',
            'data'    => $project->load('members:id,name,email')
        ]);
    }

    public function removeMember(Project $project, int $userId): JsonResponse
    {
        $this->authorize('update', $project);

        $project->members()->detach($userId);

        return response()->json([
            'message' => 'Member removed from project successfully'
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
            'message' => "Project status changed to {$newStatus} successfully",
            'data'    => $project
        ]);
    }
}