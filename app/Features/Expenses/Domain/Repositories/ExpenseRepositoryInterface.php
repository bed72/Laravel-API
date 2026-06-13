<?php

namespace App\Features\Expenses\Domain\Repositories;

use App\Features\Expenses\Domain\Models\Expense;

interface ExpenseRepositoryInterface
{
    public function findById(int $id): ?Expense;

    public function create(int $userId, float $amount, ?string $description): Expense;

    public function delete(Expense $expense): void;
}
