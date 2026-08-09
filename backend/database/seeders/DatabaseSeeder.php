<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\User;
use App\Models\Role;
use App\Models\Permission;
use App\Models\Project;
use App\Models\Task;
use App\Models\Comment;
use App\Models\ActivityLog;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $permissionsByGroup = [
            'User Management' => [
                'users.view', 'users.create', 'users.update', 'users.delete'
            ],
            'Role Management' => [
                'roles.view', 'roles.create', 'roles.update', 'roles.delete', 'roles.assign'
            ],
            'Permission Management' => [
                'permissions.view', 'permissions.assign'
            ],
            'Project Management' => [
                'projects.view', 'projects.create', 'projects.update', 'projects.delete', 'projects.archive', 'projects.manage_members'
            ],
            'Task Management' => [
                'tasks.view', 'tasks.create', 'tasks.update', 'tasks.delete', 'tasks.assign', 'tasks.change_status'
            ],
            'Comment Management' => [
                'comments.view', 'comments.create', 'comments.update', 'comments.delete', 'comments.manage_all'
            ],
            'Activity Management' => [
                'activities.view'
            ],
        ];

        $allPermissionIds = [];

        foreach ($permissionsByGroup as $group => $perms) {
            foreach ($perms as $permName) {
                $permission = Permission::firstOrCreate([
                    'name' => $permName,
                ], [
                    'display_name' => ucwords(str_replace('.', ' ', $permName)),
                    'description' => "Allows user to " . str_replace('.', ' ', $permName),
                ]);
                $allPermissionIds[] = $permission->id;
            }
        }

        $adminRole = Role::firstOrCreate(['name' => 'Administrator'], [
            'display_name' => 'System Administrator',
            'description' => 'Has full access to all system resources'
        ]);
        $adminRole->permissions()->sync($allPermissionIds);

        $pmPermissions = Permission::whereIn('name', [
            'projects.view', 'projects.create', 'projects.update', 'projects.manage_members',
            'tasks.view', 'tasks.create', 'tasks.update', 'tasks.delete', 'tasks.assign', 'tasks.change_status',
            'comments.view', 'comments.create', 'comments.update', 'comments.delete',
            'activities.view'
        ])->pluck('id');

        $pmRole = Role::firstOrCreate(['name' => 'Project Manager'], [
            'display_name' => 'Project Manager',
            'description' => 'Can manage assigned projects and tasks'
        ]);
        $pmRole->permissions()->sync($pmPermissions);

        $memberPermissions = Permission::whereIn('name', [
            'projects.view',
            'tasks.view', 'tasks.change_status',
            'comments.view', 'comments.create', 'comments.update', 'comments.delete'
        ])->pluck('id');

        $memberRole = Role::firstOrCreate(['name' => 'Member'], [
            'display_name' => 'Team Member',
            'description' => 'Can view projects and work on assigned tasks'
        ]);
        $memberRole->permissions()->sync($memberPermissions);

        $adminUser = User::factory()->create([
            'name' => 'Admin User',
            'email' => 'admin@example.com',
            'password' => bcrypt('password123'),
        ]);
        $adminUser->roles()->attach($adminRole);

        $project = Project::create([
            'title' => 'Task Management System',
            'description' => 'Full-Stack application development using Laravel & React',
            'status' => 'active',
            'owner_id' => $adminUser->id,
            'start_date' => now(),
            'due_date' => now()->addMonth(),
        ]);
        $project->members()->attach($adminUser->id, ['role' => 'owner']);

        $task = Task::create([
            'title' => 'Setup Laravel Backend Structure',
            'description' => 'Build database migrations, models and authorization system',
            'status' => 'in_progress',
            'priority' => 'high',
            'due_date' => now()->addDays(5),
            'project_id' => $project->id,
            'assigned_to' => $adminUser->id,
            'created_by' => $adminUser->id,
        ]);

        Comment::create([
            'body' => 'Initial models and migrations are completed!',
            'task_id' => $task->id,
            'user_id' => $adminUser->id,
        ]);

        ActivityLog::create([
            'user_id' => $adminUser->id,
            'action' => 'created',
            'description' => 'Created project: Task Management System',
            'metadata' => ['project_title' => $project->title],
            'project_id' => $project->id,
        ]);

        $this->call(RolesAndPermissionsSeeder::class);
    }
}