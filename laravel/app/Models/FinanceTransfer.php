<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class FinanceTransfer extends Model
{
    protected $table = 'finance_transfers';

    protected $fillable = [
        'church_id',
        'transfer_date',
        'from_account_id',
        'to_account_id',
        'amount',
        'reference',
        'status',
        'created_by',
    ];

    protected $casts = [
        'transfer_date' => 'date',
        'amount' => 'decimal:2',
    ];

    public function church()
    {
        return $this->belongsTo(Church::class, 'church_id');
    }

    public function fromAccount()
    {
        return $this->belongsTo(FinanceMoneyAccount::class, 'from_account_id');
    }

    public function toAccount()
    {
        return $this->belongsTo(FinanceMoneyAccount::class, 'to_account_id');
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
