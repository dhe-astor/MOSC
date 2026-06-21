<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class FinanceJournalBatch extends Model
{
    protected $table = 'finance_journal_batches';

    protected $fillable = [
        'diocese_id',
        'church_id',
        'batch_date',
        'reference',
        'source',
        'source_id',
        'status',
        'created_by',
    ];

    protected $casts = [
        'batch_date' => 'date',
    ];

    public function diocese()
    {
        return $this->belongsTo(Diocese::class, 'diocese_id');
    }

    public function church()
    {
        return $this->belongsTo(Church::class, 'church_id');
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function ledgerEntries()
    {
        return $this->hasMany(FinanceLedgerEntry::class, 'journal_batch_id');
    }

    protected static function booted()
    {
        static::updating(function ($batch) {
            if ($batch->getOriginal('status') === 'posted') {
                throw new \Exception("Posted journal batches must never be edited.");
            }
        });

        static::deleting(function ($batch) {
            if ($batch->getOriginal('status') === 'posted') {
                throw new \Exception("Posted journal batches must never be deleted.");
            }
        });
    }
}
