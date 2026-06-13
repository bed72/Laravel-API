<?php

use App\Features\Expenses\Domain\Repositories\ExpenseRepositoryInterface;
use App\Features\Expenses\Domain\Models\Expense;
use App\Features\Expenses\Application\Data\CreateExpenseData;
use App\Features\Expenses\Application\Services\ExpenseService;

afterEach(fn () => Mockery::close());

it('delegates the create to the repository', function () {
    $persisted = new Expense;
    $data = new CreateExpenseData(userId: 7, amount: 10.50, description: 'Almoco');

    $repository = Mockery::mock(ExpenseRepositoryInterface::class);
    $repository->shouldReceive('create')
        ->once()
        ->with($data)
        ->andReturn($persisted);

    $result = (new ExpenseService($repository))->create($data);

    expect($result)->toBe($persisted);
});
