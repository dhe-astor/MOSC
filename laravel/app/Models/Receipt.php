<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;

class Receipt extends Model
{
    protected $table = 'legacy_receipts';

    protected $fillable = [
        'diocese_id',
        'church_id',
        'receipt_number',
        'receipt_type',
        'receiptable_type',
        'receiptable_id',
        'payer_name',
        'payer_email',
        'payer_phone',
        'family_id',
        'member_id',
        'amount',
        'currency',
        'payment_method',
        'payment_reference',
        'receipt_date',
        'description',
        'pdf_path',
        'issued_by',
        'status',
        'cancellation_reason',
        'cancelled_by',
        'cancelled_at',
        'metadata',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'receipt_date' => 'date',
        'cancelled_at' => 'datetime',
        'metadata' => 'array',
    ];

    public function diocese(): BelongsTo
    {
        return $this->belongsTo(Diocese::class);
    }

    public function church(): BelongsTo
    {
        return $this->belongsTo(Church::class);
    }

    public function family(): BelongsTo
    {
        return $this->belongsTo(Family::class);
    }

    public function member(): BelongsTo
    {
        return $this->belongsTo(Member::class);
    }

    public function receiptable(): MorphTo
    {
        return $this->morphTo();
    }

    public function approvals(): MorphMany
    {
        return $this->morphMany(FinanceApproval::class, 'approvable');
    }

    public function issuer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'issued_by');
    }

    public function cancelledBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'cancelled_by');
    }
}
