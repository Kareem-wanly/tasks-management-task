<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * @extends Factory<User>
 */
class UserFactory extends Factory
{
    /**
     * The current password being used by the factory.
     */
    protected static ?string $password;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'email_verified_at' => now(),
            'password' => static::$password ??= Hash::make('password'),
            'remember_token' => Str::random(10),
            // Every column the app reads, as the database defaults them: Sanctum::actingAs()
            // clears wasRecentlyCreated, so shouldBeStrict() would reject any column left unset.
            'bitrix_user_id' => null,
            'bitrix_access_token' => null,
            'bitrix_refresh_token' => null,
            'bitrix_token_expires_at' => null,
            'bitrix_department_ids' => null,
            'bitrix_synced_at' => null,
            'avatar_url' => null,
            'position' => null,
            'is_active' => true,
        ];
    }

    /**
     * Indicate that the model's email address should be unverified.
     */
    public function unverified(): static
    {
        return $this->state(fn (array $attributes) => [
            'email_verified_at' => null,
        ]);
    }
}
