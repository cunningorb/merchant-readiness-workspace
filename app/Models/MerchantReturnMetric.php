<?php

namespace App\Models;

use Database\Factories\MerchantReturnMetricFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MerchantReturnMetric extends Model
{
    /** @use HasFactory<MerchantReturnMetricFactory> */
    use HasFactory;

    protected $fillable = [
        'merchant_id',
        'assessment_id',
        'source_provider',
        'source_import_id',
        'source_period_start',
        'source_period_end',
        'refund_amount_total',
        'refund_units_total',
        'estimated_refund_rate',
        'exchange_share',
        'refund_only_share',
        'average_time_to_refund_days',
        'top_refund_categories',
    ];

    protected function casts(): array
    {
        return [
            'source_period_start' => 'date',
            'source_period_end' => 'date',
            'refund_amount_total' => 'decimal:2',
            'estimated_refund_rate' => 'decimal:4',
            'exchange_share' => 'decimal:4',
            'refund_only_share' => 'decimal:4',
            'average_time_to_refund_days' => 'decimal:2',
            'top_refund_categories' => 'array',
        ];
    }

    public function merchant(): BelongsTo
    {
        return $this->belongsTo(Merchant::class);
    }

    public function assessment(): BelongsTo
    {
        return $this->belongsTo(Assessment::class);
    }
}
