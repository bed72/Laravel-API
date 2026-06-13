<?php

namespace App\Features\Expenses\Domain\Repositories;

use App\Features\Expenses\Application\Data\CreateExpenseData;
use App\Features\Expenses\Domain\Models\Expense;

interface ExpenseRepositoryInterface
{
    public function findById(int $id): ?Expense;

    public function create(CreateExpenseData $data): Expense;

    public function delete(Expense $expense): void;
}
