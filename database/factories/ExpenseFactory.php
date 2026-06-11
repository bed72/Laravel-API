<?php

namespace Database\Factories;

use App\Features\Expenses\Domain\Models\Expense;
use App\Features\Users\Domain\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;


class ExpenseFactory extends Factory
{
    protected $model = Expense::class;


    public function definition(): array
    {
        return [
            'amount' => fake()->randomFloat(2, 1, 1000),
            'description' => fake()->optional()->word(),
            'user_id' => User::factory(),
        ];
    }

   
    public function withoutDescription(): static
    {
        return $this->state(fn (array $attributes) => [
            'description' => null,
        ]);
    }
}
