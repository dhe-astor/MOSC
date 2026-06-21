<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class FinanceBankStatementLine extends Model
{
    protected $table = 'finance_bank_statement_lines';

    protected $fillable = [
        'bank_statement_import_id',
        'booking_date',
        'value_date',
        'partner_name',
        'description',
        'amount',
        'is_matched',
    ];

    protected $casts = [
        'booking_date' => 'date',
        'value_date' => 'date',
        'amount' => 'decimal:2',
        'is_matched' => 'boolean',
    ];

    public function import()
    {
        return $this->belongsTo(FinanceBankStatementImport::class, 'bank_statement_import_id');
    }

    public function matches()
    {
        return $this->hasMany(FinanceBankMatch::class, 'bank_statement_line_id');
    }
}
