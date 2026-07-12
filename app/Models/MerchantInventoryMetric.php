<?php

namespace App\Models;

use Database\Factories\MerchantInventoryMetricFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MerchantInventoryMetric extends Model
{
    /** @use HasFactory<MerchantInventoryMetricFactory> */
    use HasFactory;

    protected $fillable = [
        'merchant_id',
        'assessment_id',
        'source_provider',
        'source_import_id',
        'percent_multi_location_stock',
        'percent_low_or_zero_stock',
        'sku_completeness',
        'exchange_availability_risk',
    ];

    protected function casts(): array
    {
        return [
            'percent_multi_location_stock' => 'decimal:2',
            'percent_low_or_zero_stock' => 'decimal:2',
            'sku_completeness' => 'decimal:2',
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
