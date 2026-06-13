<?php

namespace App\Features\Expenses\Application\Services;

use App\Features\Expenses\Domain\Repositories\ExpenseRepositoryInterface;
use App\Features\Expenses\Domain\Models\Expense;

class ExpenseService
{
    public function __construct(
        private readonly ExpenseRepositoryInterface $repository,
    ) {}

    /** @param array<string, mixed> $data */
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
