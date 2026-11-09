<?php

namespace Database\Factories;

use App\Models\Project;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Project>
 */
class ProjectFactory extends Factory
{
    public function definition(): array
    {
        $start = fake()->dateTimeBetween('-6 months', '+1 month');

        return [
            'title' => ucfirst(fake()->unique()->words(3, true)),
            'description' => fake()->paragraph(),
            'status' => fake()->randomElement(['active', 'active', 'in_progress', 'on_hold', 'completed']),
            'owner_id' => User::factory(),
            'start_date' => $start,
            'due_date' => fake()->dateTimeBetween($start, '+6 months'),
        ];
    }
}
