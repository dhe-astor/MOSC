<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class FinanceIncomeLine extends Model
{
    protected $table = 'finance_income_lines';

    protected $fillable = [
        'income_header_id',
        'income_head_id',
        'fund_class_id',
        'programme_account_id',
        'member_id',
        'donor_name',
        'amount',
        'remarks',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
    ];

    public function header()
    {
        return $this->belongsTo(FinanceIncomeHeader::class, 'income_header_id');
    }

    public function incomeHead()
    {
        return $this->belongsTo(FinanceIncomeHead::class, 'income_head_id');
    }

    public function fundClass()
    {
        return $this->belongsTo(FinanceFundClass::class, 'fund_class_id');
    }

    public function programmeAccount()
    {
        return $this->belongsTo(FinanceProgrammeAccount::class, 'programme_account_id');
    }

    public function member()
    {
        return $this->belongsTo(Member::class, 'member_id');
    }

    public function receiptLines()
    {
        return $this->hasMany(FinanceReceiptLine::class, 'income_line_id');
    }
}
