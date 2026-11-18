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
        // unique() only knows Faker's own output, not the base roles already in the DB
        // (jobTitle() can return "Project Manager"), so those names are skipped.
        do {
            $title = fake()->unique()->jobTitle();
        } while (in_array($title, ['Administrator', 'Project Manager', 'Member'], true));

        return [
            'name' => $title,
            'display_name' => $title,
            'description' => fake()->sentence(),
        ];
    }
}
