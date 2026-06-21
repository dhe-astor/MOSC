<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class FinanceBankMatch extends Model
{
    protected $table = 'finance_bank_matches';

    protected $fillable = [
        'bank_statement_line_id',
        'matchable_type',
        'matchable_id',
        'matched_by',
    ];

    public function bankStatementLine()
    {
        return $this->belongsTo(FinanceBankStatementLine::class, 'bank_statement_line_id');
    }

    public function matcher()
    {
        return $this->belongsTo(User::class, 'matched_by');
    }

    public function matchable()
    {
        return $this->morphTo();
    }
}
