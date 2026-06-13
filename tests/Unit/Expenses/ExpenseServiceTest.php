<?php

use App\Features\Expenses\Domain\Repositories\ExpenseRepositoryInterface;
use App\Features\Expenses\Domain\Models\Expense;
use App\Features\Expenses\Application\Services\ExpenseService;

afterEach(fn () => Mockery::close());

it('merges the user id and delegates to the repository', function () {
    $persisted = new Expense;

    $repository = Mockery::mock(ExpenseRepositoryInterface::class);
    $repository->shouldReceive('create')
        ->once()
        ->with(7, 10.50, 'Almoco')
        ->andReturn($persisted);

    $result = (new ExpenseService($repository))->create(7, 10.50, 'Almoco');

    expect($result)->toBe($persisted);
});
