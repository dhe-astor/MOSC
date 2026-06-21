<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class FinanceProgrammeAccount extends Model
{
    protected $table = 'finance_programme_accounts';

    protected $fillable = [
        'church_id',
        'code',
        'name',
        'description',
        'start_date',
        'end_date',
        'is_active',
    ];

    protected $casts = [
        'start_date' => 'date',
        'end_date' => 'date',
        'is_active' => 'boolean',
    ];

    public function church()
    {
        return $this->belongsTo(Church::class, 'church_id');
    }

    public function incomeLines()
    {
        return $this->hasMany(FinanceIncomeLine::class, 'programme_account_id');
    }

    public function expenseLines()
    {
        return $this->hasMany(FinanceExpenseLine::class, 'programme_account_id');
    }

    public function ledgerEntries()
    {
        return $this->hasMany(FinanceLedgerEntry::class, 'programme_account_id');
    }
}
