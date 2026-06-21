<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class FinanceExpenseHead extends Model
{
    protected $table = 'finance_expense_heads';

    protected $fillable = [
        'chart_account_id',
        'code',
        'name',
        'description',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function chartAccount()
    {
        return $this->belongsTo(FinanceChartAccount::class, 'chart_account_id');
    }

    public function expenseLines()
    {
        return $this->hasMany(FinanceExpenseLine::class, 'expense_head_id');
    }
}
