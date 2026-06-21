<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class FinanceIncomeHeader extends Model
{
    protected $table = 'finance_income_headers';

    protected $fillable = [
        'church_id',
        'income_date',
        'money_account_id',
        'reference_no',
        'remarks',
        'status',
        'created_by',
    ];

    protected $casts = [
        'income_date' => 'date',
    ];

    public function church()
    {
        return $this->belongsTo(Church::class, 'church_id');
    }

    public function moneyAccount()
    {
        return $this->belongsTo(FinanceMoneyAccount::class, 'money_account_id');
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function lines()
    {
        return $this->hasMany(FinanceIncomeLine::class, 'income_header_id');
    }

    public function receipts()
    {
        return $this->hasMany(FinanceReceipt::class, 'income_header_id');
    }
}
