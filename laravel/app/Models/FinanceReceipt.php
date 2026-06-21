<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class FinanceReceipt extends Model
{
    protected $table = 'finance_receipts';

    protected $fillable = [
        'income_header_id',
        'receipt_number',
        'receipt_date',
        'received_from',
        'member_id',
        'payment_method',
        'total_amount',
        'status',
    ];

    protected $casts = [
        'receipt_date' => 'date',
        'total_amount' => 'decimal:2',
    ];

    public function incomeHeader()
    {
        return $this->belongsTo(FinanceIncomeHeader::class, 'income_header_id');
    }

    public function member()
    {
        return $this->belongsTo(Member::class, 'member_id');
    }

    public function lines()
    {
        return $this->hasMany(FinanceReceiptLine::class, 'receipt_id');
    }
}
