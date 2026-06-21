<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class FinanceCashBatch extends Model
{
    protected $table = 'finance_cash_batches';

    protected $fillable = [
        'church_id',
        'money_account_id',
        'opened_at',
        'closed_at',
        'opened_by',
        'closed_by',
        'status',
        'counting_details',
        'declared_amount',
        'system_amount',
        'difference',
    ];

    protected $casts = [
        'opened_at' => 'datetime',
        'closed_at' => 'datetime',
        'counting_details' => 'array',
        'declared_amount' => 'decimal:2',
        'system_amount' => 'decimal:2',
        'difference' => 'decimal:2',
    ];

    public function church()
    {
        return $this->belongsTo(Church::class, 'church_id');
    }

    public function moneyAccount()
    {
        return $this->belongsTo(FinanceMoneyAccount::class, 'money_account_id');
    }

    public function opener()
    {
        return $this->belongsTo(User::class, 'opened_by');
    }

    public function closer()
    {
        return $this->belongsTo(User::class, 'closed_by');
    }
}
