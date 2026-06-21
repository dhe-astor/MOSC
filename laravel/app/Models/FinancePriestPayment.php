<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class FinancePriestPayment extends Model
{
    protected $table = 'finance_priest_payments';

    protected $fillable = [
        'church_id',
        'priest_id',
        'priest_profile_id',
        'expense_header_id',
        'payment_date',
        'type',
        'amount',
        'travel_distance_km',
        'travel_rate_per_km',
        'description',
        'status',
    ];

    public function newEloquentBuilder($query)
    {
        return (new \App\Models\Builders\CompatibilityBuilder($query))->setMappings([
            'priest_id' => 'priest_profile_id',
        ]);
    }

    protected static function booted()
    {
        static::saving(function ($payment) {
            if (isset($payment->attributes['priest_id'])) {
                if (!isset($payment->attributes['priest_profile_id'])) {
                    $payment->attributes['priest_profile_id'] = $payment->attributes['priest_id'];
                }
                unset($payment->attributes['priest_id']);
            }
        });
    }

    public function getPriestIdAttribute()
    {
        return $this->priest_profile_id;
    }

    public function setPriestIdAttribute($value)
    {
        $this->attributes['priest_profile_id'] = $value;
    }

    protected $casts = [
        'payment_date' => 'date',
        'amount' => 'decimal:2',
        'travel_distance_km' => 'decimal:2',
        'travel_rate_per_km' => 'decimal:4',
    ];

    public function church()
    {
        return $this->belongsTo(Church::class, 'church_id');
    }

    public function priestProfile()
    {
        return $this->belongsTo(PriestProfile::class, 'priest_profile_id');
    }

    public function priest()
    {
        return $this->belongsTo(PriestProfile::class, 'priest_profile_id');
    }

    public function expenseHeader()
    {
        return $this->belongsTo(FinanceExpenseHeader::class, 'expense_header_id');
    }
}
