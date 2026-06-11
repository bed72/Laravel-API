<?php

namespace App\Features\Expenses\Domain\Contracts;

use App\Features\Expenses\Domain\Models\Expense;

interface ExpenseRepositoryInterface
{
    public function findById(int $id): ?Expense;
    public function create(array $data): Expense;
    public function delete(Expense $expense): void;
}
