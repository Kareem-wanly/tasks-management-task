<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\User;
use App\Models\Project;
use App\Models\Task;
use App\Models\Comment;
use App\Models\ActivityLog;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call(RolesAndPermissionsSeeder::class);

        $adminUser = User::where('email', 'admin@example.com')->first();

        $project = Project::firstOrCreate(
            ['title' => 'Task Management System'],
            [
                'description' => 'Full-Stack application development using Laravel & React',
                'status'      => 'active',
                'owner_id'    => $adminUser->id,
                'start_date'  => now(),
                'due_date'    => now()->addMonth(),
            ]
        );

        if (!$project->members()->where('users.id', $adminUser->id)->exists()) {
            $project->members()->attach($adminUser->id, ['role' => 'owner']);
        }

        $task = Task::firstOrCreate(
            [
                'title'      => 'Setup Laravel Backend Structure',
                'project_id' => $project->id,
            ],
            [
                'description' => 'Build database migrations, models and authorization system',
                'status'      => 'in_progress',
                'priority'    => 'high',
                'due_date'    => now()->addDays(5),
                'assigned_to' => $adminUser->id,
                'created_by'  => $adminUser->id,
            ]
        );

        Comment::firstOrCreate(
            [
                'task_id' => $task->id,
                'user_id' => $adminUser->id,
                'body'    => 'Initial models and migrations are completed!',
            ]
        );

        ActivityLog::firstOrCreate(
            [
                'project_id'  => $project->id,
                'action'      => 'created',
                'description' => 'Created project: Task Management System',
            ],
            [
                'user_id'  => $adminUser->id,
                'metadata' => ['project_title' => $project->title],
            ]
        );
    }
}