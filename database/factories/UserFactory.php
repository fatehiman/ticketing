<?php

namespace Database\Factories;

use App\Enums\Role;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\User>
 */
class UserFactory extends Factory
{
    protected static ?string $password;

    public function definition(): array
    {
        return [
            'first_name' => fake()->firstName(),
            'last_name' => fake()->lastName(),
            'email' => fake()->unique()->safeEmail(),
            'mobile' => '09'.fake()->unique()->numerify('#########'),
            'password' => static::$password ??= Hash::make('password'),
            'role' => Role::Customer->value,
            'locale' => 'fa',
            'calendar' => 'jalali',
            'is_active' => true,
            'remember_token' => Str::random(10),
        ];
    }

    public function admin(): static
    {
        return $this->state(fn () => ['role' => Role::Admin->value]);
    }

    public function developer(): static
    {
        return $this->state(fn () => ['role' => Role::Developer->value]);
    }

    public function customer(): static
    {
        return $this->state(fn () => ['role' => Role::Customer->value]);
    }
}
