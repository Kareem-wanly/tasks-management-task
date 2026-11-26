<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\AddProjectMemberRequest;
use App\Http\Requests\DeleteProjectRequest;
use App\Http\Requests\ListActivitiesRequest;
use App\Http\Requests\ListProjectActivitiesRequest;
use App\Http\Requests\ListProjectsRequest;
use App\Http\Requests\ProjectOptionsRequest;
use App\Http\Requests\RemoveProjectMemberRequest;
use App\Http\Requests\ShowProjectRequest;
use App\Http\Requests\StoreProjectRequest;
use App\Http\Requests\ToggleProjectArchiveRequest;
use App\Http\Requests\UpdateProjectRequest;
use App\Http\Resources\ActivityLogResource;
use App\Http\Resources\ProjectMemberResource;
use App\Http\Resources\ProjectOptionResource;
use App\Http\Resources\ProjectResource;
use App\Models\Project;
use App\Models\User;
use App\Services\Contracts\ActivityLogServiceInterface;
use App\Services\Contracts\ProjectServiceInterface;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

final class ProjectController extends Controller
{
    public function __construct(
        private readonly ProjectServiceInterface $projects,
        private readonly ActivityLogServiceInterface $activityLog,
    ) {}

    public function index(ListProjectsRequest $request): AnonymousResourceCollection
    {
        return ProjectResource::collection($this->projects->list($request->actor(), $request->toDto(), $request->perPage()));
    }

    public function show(ShowProjectRequest $request, Project $project): ProjectResource
    {
        return new ProjectResource($this->projects->show($project));
    }

    public function store(StoreProjectRequest $request): JsonResponse
    {
        return (new ProjectResource($this->projects->create($request->actor(), $request->toDto())))
            ->response()
            ->setStatusCode(201);
    }

    public function update(UpdateProjectRequest $request, Project $project): ProjectResource
    {
        return new ProjectResource($this->projects->update($request->actor(), $project, $request->toDto()));
    }

    public function destroy(DeleteProjectRequest $request, Project $project): JsonResponse
    {
        $this->projects->delete($request->actor(), $project);

        return response()->json(['success' => true, 'message' => 'Project deleted successfully', 'data' => null]);
    }

    public function members(ShowProjectRequest $request, Project $project): JsonResponse
    {
        return response()->json([
            'success' => true,
            'message' => 'Project members retrieved successfully',
            'data' => ProjectMemberResource::collection($this->projects->members($project)),
        ]);
    }

    public function addMember(AddProjectMemberRequest $request, Project $project): JsonResponse
    {
        $project = $this->projects->addMember($request->actor(), $project, $request->toDto());

        return response()->json(['success' => true, 'message' => 'Member added to project successfully', 'data' => new ProjectResource($project)]);
    }

    public function removeMember(RemoveProjectMemberRequest $request, Project $project, User $user): JsonResponse
    {
        return response()->json([
            'success' => true,
            'message' => 'Member removed from project successfully',
            'data' => new ProjectResource($this->projects->removeMember($request->actor(), $project, $user)),
        ]);
    }

    public function toggleArchive(ToggleProjectArchiveRequest $request, Project $project): JsonResponse
    {
        $project = $this->projects->toggleArchive($request->actor(), $project);

        return response()->json([
            'success' => true,
            'message' => "Project status changed to {$project->status->value} successfully",
            'data' => new ProjectResource($project),
        ]);
    }

    public function activities(ListProjectActivitiesRequest $request, Project $project): JsonResponse
    {
        return $this->paginated($this->activityLog->forProject($project, $request->perPage()), ActivityLogResource::class, 'Project activity logs retrieved successfully');
    }

    public function allActivities(ListActivitiesRequest $request): JsonResponse
    {
        return $this->paginated($this->activityLog->forUser($request->actor(), $request->perPage()), ActivityLogResource::class, 'All activity logs retrieved successfully');
    }

    public function options(ProjectOptionsRequest $request): AnonymousResourceCollection
    {
        return ProjectOptionResource::collection($this->projects->options($request->actor(), $request->includeArchived()));
    }
}
