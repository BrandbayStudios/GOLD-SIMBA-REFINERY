<?php

namespace Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<User> */
class UserFactory extends Factory
{
    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'phone' => '26097'.fake()->unique()->numerify('#######'),
            'role' => 'customer',
            'status' => 'active',
            'password' => 'Password123',
        ];
    }

    public function admin(): static
    {
        return $this->state(['role' => 'admin']);
    }

    public function provider(): static
    {
        return $this->state(['role' => 'provider']);
    }
}
