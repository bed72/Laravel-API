<?php

namespace App\Features\Expenses\Domain\Repositories;

use App\Features\Expenses\Domain\Models\Expense;

interface ExpenseRepositoryInterface
{
    public function findById(int $id): ?Expense;

    /** @param array<string, mixed> $data */
    public function create(array $data): Expense;

    public function delete(Expense $expense): void;
}
