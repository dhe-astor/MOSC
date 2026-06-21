<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class FinanceReceiptLine extends Model
{
    protected $table = 'finance_receipt_lines';

    protected $fillable = [
        'receipt_id',
        'income_line_id',
        'income_head_id',
        'amount',
        'description',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
    ];

    public function receipt()
    {
        return $this->belongsTo(FinanceReceipt::class, 'receipt_id');
    }

    public function incomeLine()
    {
        return $this->belongsTo(FinanceIncomeLine::class, 'income_line_id');
    }

    public function incomeHead()
    {
        return $this->belongsTo(FinanceIncomeHead::class, 'income_head_id');
    }
}
