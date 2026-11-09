<?php

namespace Database\Factories;

use App\Models\Role;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Role>
 */
class RoleFactory extends Factory
{
    public function definition(): array
    {
        $title = fake()->unique()->jobTitle();

        return [
            'name' => $title,
            'display_name' => $title,
            'description' => fake()->sentence(),
        ];
    }
}
