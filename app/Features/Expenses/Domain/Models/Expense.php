<?php

namespace App\Features\Expenses\Domain\Models;

use App\Features\Users\Domain\Models\User;
use Database\Factories\ExpenseFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'amount',
    'description',
    'user_id',
])]
class Expense extends Model
{
    use HasFactory;

    protected static function newFactory(): ExpenseFactory
    {
        return ExpenseFactory::new();
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
        ];
    }
}
