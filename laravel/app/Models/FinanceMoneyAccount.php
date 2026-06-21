<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class FinanceMoneyAccount extends Model
{
    protected $table = 'finance_money_accounts';

    protected $fillable = [
        'church_id',
        'code',
        'name',
        'type',
        'bank_name',
        'account_number',
        'iban',
        'bic',
        'currency',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function church()
    {
        return $this->belongsTo(Church::class, 'church_id');
    }

    public function incomeHeaders()
    {
        return $this->hasMany(FinanceIncomeHeader::class, 'money_account_id');
    }

    public function expenseHeaders()
    {
        return $this->hasMany(FinanceExpenseHeader::class, 'money_account_id');
    }

    public function transfersFrom()
    {
        return $this->hasMany(FinanceTransfer::class, 'from_account_id');
    }

    public function transfersTo()
    {
        return $this->hasMany(FinanceTransfer::class, 'to_account_id');
    }

    public function bankStatementImports()
    {
        return $this->hasMany(FinanceBankStatementImport::class, 'money_account_id');
    }

    public function cashBatches()
    {
        return $this->hasMany(FinanceCashBatch::class, 'money_account_id');
    }
}
