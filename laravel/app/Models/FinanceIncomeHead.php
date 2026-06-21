<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class FinanceIncomeHead extends Model
{
    protected $table = 'finance_income_heads';

    protected $fillable = [
        'chart_account_id',
        'code',
        'name',
        'description',
        'member_default',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'member_default' => 'boolean',
    ];

    public function chartAccount()
    {
        return $this->belongsTo(FinanceChartAccount::class, 'chart_account_id');
    }

    public function incomeLines()
    {
        return $this->hasMany(FinanceIncomeLine::class, 'income_head_id');
    }

    public function receiptLines()
    {
        return $this->hasMany(FinanceReceiptLine::class, 'income_head_id');
    }
}
