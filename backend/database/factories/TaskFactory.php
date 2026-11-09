<?php

namespace Database\Factories;

use App\Enums\TaskStatus;
use App\Models\Project;
use App\Models\Task;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Task>
 */
class TaskFactory extends Factory
{
    public function definition(): array
    {
        return [
            'title' => ucfirst(fake()->words(fake()->numberBetween(3, 6), true)),
            'description' => fake()->optional(0.8)->paragraph(),
            'status' => fake()->randomElement(TaskStatus::cases())->value,
            'priority' => fake()->randomElement(['low', 'medium', 'medium', 'high', 'urgent']),
            'due_date' => fake()->optional(0.85)->dateTimeBetween('-1 month', '+2 months'),
            'project_id' => Project::factory(),
            'assigned_to' => User::factory(),
            'created_by' => User::factory(),
        ];
    }
}
