<?php

namespace Database\Seeders;

use App\Models\ActivityLog;
use App\Models\Comment;
use App\Models\Permission;
use App\Models\Project;
use App\Models\Role;
use App\Models\Task;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

/**
 * Fills the database with demo data: at least 50 users, roles, projects,
 * tasks, comments and activity logs. All demo users log in with Password123!
 * Safe to re-run: each section only tops up to the target count.
 */
class DemoDataSeeder extends Seeder
{
    private const TARGET = 50;

    public function run(): void
    {
        DB::transaction(function (): void {
            $this->seedRoles();
            $users = $this->seedUsers();
            $projects = $this->seedProjects($users);
            $tasks = $this->seedTasks($projects);
            $this->seedComments($tasks);
        });
    }

    private function seedRoles(): void
    {
        $missing = self::TARGET - Role::count();
        if ($missing <= 0) {
            return;
        }

        $permissionIds = Permission::whereIn('name', [
            'projects.view', 'tasks.view', 'tasks.create', 'tasks.update', 'tasks.change_status',
            'comments.view', 'comments.create', 'comments.update', 'activities.view', 'users.view',
        ])->pluck('id');

        Role::factory($missing)->create()->each(
            fn (Role $role) => $role->permissions()->sync($permissionIds->random(fake()->numberBetween(3, 8)))
        );
    }

    /** @return Collection<int, User> */
    private function seedUsers(): Collection
    {
        $missing = self::TARGET - User::count();
        if ($missing > 0) {
            $password = Hash::make('Password123!');
            $pm = Role::where('name', 'Project Manager')->firstOrFail();
            $member = Role::where('name', 'Member')->firstOrFail();
            $customRoles = Role::whereNotIn('name', ['Administrator', 'Project Manager', 'Member'])->pluck('id');

            User::factory($missing)->create(['password' => $password])
                ->each(function (User $user, int $i) use ($pm, $member, $customRoles): void {
                    // About 1 in 5 is a project manager; the rest are members, some with an extra custom role.
                    $roles = [$i % 5 === 0 ? $pm->id : $member->id];
                    if ($customRoles->isNotEmpty() && fake()->boolean(40)) {
                        $roles[] = $customRoles->random();
                    }
                    $user->roles()->sync($roles);
                });
        }

        return User::all();
    }

    /**
     * @param  Collection<int, User>  $users
     * @return Collection<int, Project>
     */
    private function seedProjects(Collection $users): Collection
    {
        $missing = self::TARGET - Project::count();
        $owners = User::whereHas('roles', fn ($q) => $q->whereIn('name', ['Administrator', 'Project Manager']))->get();

        for ($i = 0; $i < $missing; $i++) {
            $owner = $owners->random();
            $project = Project::factory()->create(['owner_id' => $owner->id]);
            $project->members()->attach($owner->id, ['role' => 'owner']);

            $users->where('id', '!=', $owner->id)
                ->random(fake()->numberBetween(3, 8))
                ->each(function (User $member) use ($project, $owner): void {
                    $project->members()->attach($member->id, [
                        'role' => fake()->randomElement(['member', 'member', 'member', 'lead', 'manager']),
                    ]);
                    ActivityLog::create([
                        'user_id' => $owner->id,
                        'project_id' => $project->id,
                        'action' => 'member_added',
                        'description' => "{$member->name} was added to project '{$project->title}'.",
                        'metadata' => ['member_id' => $member->id],
                    ]);
                });
        }

        return Project::with('members')->get();
    }

    /**
     * @param  Collection<int, Project>  $projects
     * @return Collection<int, Task>
     */
    private function seedTasks(Collection $projects): Collection
    {
        if (Task::count() < self::TARGET) {
            foreach ($projects as $project) {
                $memberIds = $project->members->pluck('id');
                if ($memberIds->isEmpty()) {
                    continue;
                }

                // Pass the foreign keys up front (a callback state per task), otherwise the
                // factory's default Project/User factories would create orphan rows.
                Task::factory(fake()->numberBetween(2, 6))
                    ->state(fn () => [
                        'project_id' => $project->id,
                        'assigned_to' => fake()->boolean(85) ? $memberIds->random() : null,
                        'created_by' => $project->owner_id,
                    ])
                    ->create();
            }
        }

        return Task::with('project.members')->get();
    }

    /** @param  Collection<int, Task>  $tasks */
    private function seedComments(Collection $tasks): void
    {
        if (Comment::count() >= self::TARGET) {
            return;
        }

        foreach ($tasks as $task) {
            $memberIds = $task->project->members->pluck('id');

            for ($i = 0, $n = fake()->numberBetween(0, 3); $i < $n; $i++) {
                Comment::factory()->create(['task_id' => $task->id, 'user_id' => $memberIds->random()]);
            }
        }
    }
}
