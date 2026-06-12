<?php

namespace App\Features\Expenses\Infrastructure\Repositories;

use App\Features\Expenses\Domain\Contracts\ExpenseRepositoryInterface;
use App\Features\Expenses\Domain\Models\Expense;

class ExpenseRepository implements ExpenseRepositoryInterface
{
    public function findById(int $id): ?Expense
    {
        return Expense::find($id);
    }

    /** @param array<string, mixed> $data */
    public function create(array $data): Expense
    {
        return Expense::create($data);
    }

    public function delete(Expense $expense): void
    {
        $expense->delete();
    }
}
