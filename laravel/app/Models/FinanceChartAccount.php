<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class FinanceChartAccount extends Model
{
    protected $table = 'finance_chart_accounts';

    protected $fillable = [
        'code',
        'name',
        'type',
        'description',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function incomeHeads()
    {
        return $this->hasMany(FinanceIncomeHead::class, 'chart_account_id');
    }

    public function expenseHeads()
    {
        return $this->hasMany(FinanceExpenseHead::class, 'chart_account_id');
    }

    public function ledgerEntries()
    {
        return $this->hasMany(FinanceLedgerEntry::class, 'chart_account_id');
    }
}
