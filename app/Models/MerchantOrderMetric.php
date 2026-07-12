<?php

namespace App\Models;

use Database\Factories\MerchantOrderMetricFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MerchantOrderMetric extends Model
{
    /** @use HasFactory<MerchantOrderMetricFactory> */
    use HasFactory;

    protected $fillable = [
        'merchant_id',
        'assessment_id',
        'source_provider',
        'source_import_id',
        'source_period_start',
        'source_period_end',
        'order_count',
        'annualized_order_volume',
        'average_order_value',
    ];

    protected function casts(): array
    {
        return [
            'source_period_start' => 'date',
            'source_period_end' => 'date',
            'average_order_value' => 'decimal:2',
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
