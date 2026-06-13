<?php

namespace App\Features\Expenses\Application\Data;

/**
 * Input for the create-expense use case.
 */
final readonly class CreateExpenseData
{
    public function __construct(
        public int $userId,
        public float $amount,
        public ?string $description,
    ) {}
}
