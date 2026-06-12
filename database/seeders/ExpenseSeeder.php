<?php

namespace Database\Seeders;

use App\Features\Expenses\Domain\Models\Expense;
use App\Features\Users\Domain\Models\User;
use Illuminate\Database\Seeder;

class ExpenseSeeder extends Seeder
{
    public function run(): void
    {
        User::all()->each(
            fn (User $user) => Expense::factory()
                ->count(15)
                ->create(['user_id' => $user->id]),
        );
    }
}
