<?php

use App\Features\Expenses\Domain\Contracts\ExpenseRepositoryInterface;
use App\Features\Expenses\Domain\Models\Expense;
use App\Features\Expenses\Domain\Services\ExpenseService;

afterEach(fn () => Mockery::close());

it('merges the user id and delegates to the repository', function () {
    $persisted = new Expense();

    $repository = Mockery::mock(ExpenseRepositoryInterface::class);
    $repository->shouldReceive('create')
        ->once()
        ->with([
            'amount' => 10.50,
            'description' => 'Almoco',
            'user_id' => 7,
        ])
        ->andReturn($persisted);

    $result = (new ExpenseService($repository))->create(7, [
        'amount' => 10.50,
        'description' => 'Almoco',
    ]);

    expect($result)->toBe($persisted);
});
