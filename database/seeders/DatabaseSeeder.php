<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // Ordem importa: usuários antes das despesas (FK).
        $this->call([
            UserSeeder::class,
            ExpenseSeeder::class,
        ]);
    }
}
