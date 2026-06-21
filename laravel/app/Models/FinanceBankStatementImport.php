<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class FinanceBankStatementImport extends Model
{
    protected $table = 'finance_bank_statement_imports';

    protected $fillable = [
        'money_account_id',
        'import_date',
        'file_name',
        'imported_by',
    ];

    protected $casts = [
        'import_date' => 'date',
    ];

    public function moneyAccount()
    {
        return $this->belongsTo(FinanceMoneyAccount::class, 'money_account_id');
    }

    public function importer()
    {
        return $this->belongsTo(User::class, 'imported_by');
    }

    public function lines()
    {
        return $this->hasMany(FinanceBankStatementLine::class, 'bank_statement_import_id');
    }
}
