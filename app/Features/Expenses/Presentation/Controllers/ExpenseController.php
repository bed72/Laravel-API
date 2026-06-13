<?php

namespace App\Features\Expenses\Presentation\Controllers;

use App\Features\Expenses\Application\Services\ExpenseService;
use App\Features\Expenses\Presentation\Requests\StoreExpenseRequest;
use App\Features\Expenses\Presentation\Responses\ExpenseResponse;

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
