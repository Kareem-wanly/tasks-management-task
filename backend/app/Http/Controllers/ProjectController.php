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
use App\Http\Resources\ProjectResource;
use App\Models\Project;
use App\Models\User;
use App\Services\Contracts\ActivityLogServiceInterface;
use App\Services\Contracts\ProjectServiceInterface;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class ProjectController extends Controller
{
    public function __construct(
        private readonly ProjectServiceInterface $projects,
        private readonly ActivityLogServiceInterface $activityLog,
    ) {}

    public function index(ListProjectsRequest $request): AnonymousResourceCollection
    {
        return ProjectResource::collection($this->projects->list($request->user(), $request->toDto(), $request->perPage()));
    }

    public function show(ShowProjectRequest $request, Project $project): ProjectResource
    {
        return new ProjectResource($this->projects->show($project));
    }

    public function store(StoreProjectRequest $request): JsonResponse
    {
        return (new ProjectResource($this->projects->create($request->user(), $request->toDto())))
            ->response()
            ->setStatusCode(201);
    }

    public function update(UpdateProjectRequest $request, Project $project): ProjectResource
    {
        return new ProjectResource($this->projects->update($request->user(), $project, $request->toDto()));
    }

    public function destroy(DeleteProjectRequest $request, Project $project): JsonResponse
    {
        $this->projects->delete($request->user(), $project);

        return response()->json(['success' => true, 'message' => 'Project deleted successfully', 'data' => null]);
    }

    public function members(ShowProjectRequest $request, Project $project): JsonResponse
    {
        return response()->json([
            'success' => true,
            'message' => 'Project members retrieved successfully',
            'data' => $this->projects->members($project),
        ]);
    }

    public function addMember(AddProjectMemberRequest $request, Project $project): JsonResponse
    {
        $project = $this->projects->addMember($request->user(), $project, $request->toDto());

        return response()->json(['success' => true, 'message' => 'Member added to project successfully', 'data' => $project]);
    }

    public function removeMember(RemoveProjectMemberRequest $request, Project $project, User $user): JsonResponse
    {
        return response()->json([
            'success' => true,
            'message' => 'Member removed from project successfully',
            'data' => $this->projects->removeMember($request->user(), $project, $user),
        ]);
    }

    public function toggleArchive(ToggleProjectArchiveRequest $request, Project $project): JsonResponse
    {
        $project = $this->projects->toggleArchive($request->user(), $project);

        return response()->json([
            'success' => true,
            'message' => "Project status changed to {$project->status->value} successfully",
            'data' => $project,
        ]);
    }

    public function activities(ListProjectActivitiesRequest $request, Project $project): JsonResponse
    {
        return $this->paginated($this->activityLog->forProject($project, $request->perPage()), 'Project activity logs retrieved successfully');
    }

    public function allActivities(ListActivitiesRequest $request): JsonResponse
    {
        return $this->paginated($this->activityLog->forUser($request->user(), $request->perPage()), 'All activity logs retrieved successfully');
    }

    public function options(ProjectOptionsRequest $request): JsonResponse
    {
        return response()->json(['data' => $this->projects->options($request->user(), $request->includeArchived())]);
    }
}
