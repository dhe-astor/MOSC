<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class FinanceExpenseLine extends Model
{
    protected $table = 'finance_expense_lines';

    protected $fillable = [
        'expense_header_id',
        'expense_head_id',
        'fund_class_id',
        'programme_account_id',
        'amount',
        'remarks',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
    ];

    public function header()
    {
        return $this->belongsTo(FinanceExpenseHeader::class, 'expense_header_id');
    }

    public function expenseHead()
    {
        return $this->belongsTo(FinanceExpenseHead::class, 'expense_head_id');
    }

    public function fundClass()
    {
        return $this->belongsTo(FinanceFundClass::class, 'fund_class_id');
    }

    public function programmeAccount()
    {
        return $this->belongsTo(FinanceProgrammeAccount::class, 'programme_account_id');
    }
}
