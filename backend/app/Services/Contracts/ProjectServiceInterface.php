<?php

declare(strict_types=1);

namespace App\Services\Contracts;

use App\DTOs\Project\AddProjectMemberDTO;
use App\DTOs\Project\CreateProjectDTO;
use App\DTOs\Project\ProjectFiltersDTO;
use App\DTOs\Project\UpdateProjectDTO;
use App\Models\Project;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

interface ProjectServiceInterface
{
    public function list(User $user, ProjectFiltersDTO $filters, int $perPage): LengthAwarePaginator;

    public function show(Project $project): Project;

    /** Forms offer active projects only; list filters ask for archived ones too. */
    public function options(User $user, bool $includeArchived): Collection;

    /**
     * A new project is a Bitrix project; its creator owns it.
     */
    public function create(User $actor, CreateProjectDTO $data): Project;

    public function update(User $actor, Project $project, UpdateProjectDTO $data): Project;

    /** Deleting here only archives the Bitrix group. */
    public function delete(User $actor, Project $project): void;

    /** Archives an active project, or reopens an archived one. */
    public function toggleArchive(User $actor, Project $project): Project;

    public function members(Project $project): Collection;

    public function addMember(User $actor, Project $project, AddProjectMemberDTO $member): Project;

    public function removeMember(User $actor, Project $project, User $member): Project;
}
