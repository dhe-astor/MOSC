<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class FinanceLedgerEntry extends Model
{
    protected $table = 'finance_ledger_entries';

    protected $fillable = [
        'journal_batch_id',
        'chart_account_id',
        'fund_class_id',
        'programme_account_id',
        'entry_date',
        'debit',
        'credit',
        'description',
    ];

    protected $casts = [
        'entry_date' => 'date',
        'debit' => 'decimal:2',
        'credit' => 'decimal:2',
    ];

    public function journalBatch()
    {
        return $this->belongsTo(FinanceJournalBatch::class, 'journal_batch_id');
    }

    public function chartAccount()
    {
        return $this->belongsTo(FinanceChartAccount::class, 'chart_account_id');
    }

    public function fundClass()
    {
        return $this->belongsTo(FinanceFundClass::class, 'fund_class_id');
    }

    public function programmeAccount()
    {
        return $this->belongsTo(FinanceProgrammeAccount::class, 'programme_account_id');
    }

    protected static function booted()
    {
        static::updating(function ($entry) {
            if ($entry->journalBatch && $entry->journalBatch->status === 'posted') {
                throw new \Exception("Posted ledger entries must never be edited.");
            }
        });

        static::deleting(function ($entry) {
            if ($entry->journalBatch && $entry->journalBatch->status === 'posted') {
                throw new \Exception("Posted ledger entries must never be deleted.");
            }
        });
    }
}
