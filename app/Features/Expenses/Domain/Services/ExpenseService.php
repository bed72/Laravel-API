<?php

namespace App\Features\Expenses\Domain\Services;

use App\Features\Expenses\Domain\Contracts\ExpenseRepositoryInterface;
use App\Features\Expenses\Domain\Models\Expense;

class ExpenseService
{
    public function __construct(
        private readonly ExpenseRepositoryInterface $repository,
    ) {
    }

    public function create(
        int $userId,
        array $data,
    ): Expense {
        return $this->repository->create([
            ...$data,
            'user_id' => $userId,
        ]);
    }
}
