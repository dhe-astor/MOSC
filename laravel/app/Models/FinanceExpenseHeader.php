<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class FinanceExpenseHeader extends Model
{
    protected $table = 'finance_expense_headers';

    protected $fillable = [
        'church_id',
        'expense_date',
        'money_account_id',
        'voucher_number',
        'reference_no',
        'payee_name',
        'remarks',
        'status',
        'created_by',
    ];

    protected $casts = [
        'expense_date' => 'date',
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
        return $this->hasMany(FinanceExpenseLine::class, 'expense_header_id');
    }

    public function priestPayments()
    {
        return $this->hasMany(FinancePriestPayment::class, 'expense_header_id');
    }
}
