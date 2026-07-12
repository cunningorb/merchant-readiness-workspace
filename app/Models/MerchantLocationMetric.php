<?php

namespace App\Models;

use Database\Factories\MerchantLocationMetricFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MerchantLocationMetric extends Model
{
    /** @use HasFactory<MerchantLocationMetricFactory> */
    use HasFactory;

    protected $fillable = [
        'merchant_id',
        'assessment_id',
        'source_provider',
        'source_import_id',
        'active_location_count',
        'operational_complexity_score',
    ];

    public function merchant(): BelongsTo
    {
        return $this->belongsTo(Merchant::class);
    }

    public function assessment(): BelongsTo
    {
        return $this->belongsTo(Assessment::class);
    }
}
