<?php

namespace App\Features\Expenses\Http\Controllers;

use App\Features\Expenses\Domain\Services\ExpenseService;
use App\Features\Expenses\Http\Requests\StoreExpenseRequest;
use App\Features\Expenses\Http\Responses\ExpenseResponse;

class ExpenseController
{
    public function __construct(
        private readonly ExpenseService $service,
    ) {}

    public function store(
        StoreExpenseRequest $request,
    ): ExpenseResponse {
        $expense = $this->service->create(
            1,
            $request->validated(),
        );

        return ExpenseResponse::make($expense);
    }
}
