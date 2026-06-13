<?php

namespace App\Features\Expenses\Infrastructure\Repositories;

use App\Features\Expenses\Domain\Repositories\ExpenseRepositoryInterface;
use App\Features\Expenses\Domain\Models\Expense;

class ExpenseRepository implements ExpenseRepositoryInterface
{
    public function __construct(
        private readonly Expense $model,
    ) {}

    public function findById(int $id): ?Expense
    {
        return $this->model->newQuery()->find($id);
    }

    /** @param array<string, mixed> $data */
    public function create(array $data): Expense
    {
        /** @var Expense */
        return $this->model->newQuery()->create($data);
    }

    public function delete(Expense $expense): void
    {
        $expense->delete();
    }
}
