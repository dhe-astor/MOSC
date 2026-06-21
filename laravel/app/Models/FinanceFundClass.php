<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class FinanceFundClass extends Model
{
    protected $table = 'finance_fund_classes';

    protected $fillable = [
        'code',
        'name',
        'description',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function incomeLines()
    {
        return $this->hasMany(FinanceIncomeLine::class, 'fund_class_id');
    }

    public function expenseLines()
    {
        return $this->hasMany(FinanceExpenseLine::class, 'fund_class_id');
    }

    public function ledgerEntries()
    {
        return $this->hasMany(FinanceLedgerEntry::class, 'fund_class_id');
    }
}
