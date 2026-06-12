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
    /** @use HasFactory<ExpenseFactory> */
    use HasFactory;

    protected static function newFactory(): ExpenseFactory
    {
        return ExpenseFactory::new();
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
        ];
    }
}
