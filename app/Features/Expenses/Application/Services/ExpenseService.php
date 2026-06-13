<?php

namespace App\Features\Expenses\Application\Services;

use App\Features\Expenses\Domain\Repositories\ExpenseRepositoryInterface;
use App\Features\Expenses\Domain\Models\Expense;

class ExpenseService
{
    public function __construct(
        private readonly ExpenseRepositoryInterface $repository,
    ) {}

    public function create(int $userId, float $amount, ?string $description): Expense
    {
        return $this->repository->create($userId, $amount, $description);
    }
}
