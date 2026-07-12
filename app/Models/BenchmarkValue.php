<?php

namespace App\Models;

use Database\Factories\BenchmarkValueFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BenchmarkValue extends Model
{
    /** @use HasFactory<BenchmarkValueFactory> */
    use HasFactory;

    protected $fillable = [
        'benchmark_set_id',
        'metric_key',
        'industry',
        'platform',
        'annual_order_volume_min',
        'annual_order_volume_max',
        'catalog_profile',
        'minimum_value',
        'maximum_value',
        'unit',
        'sample_size',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'annual_order_volume_min' => 'decimal:2',
            'annual_order_volume_max' => 'decimal:2',
            'minimum_value' => 'decimal:2',
            'maximum_value' => 'decimal:2',
        ];
    }

    public function benchmarkSet(): BelongsTo
    {
        return $this->belongsTo(BenchmarkSet::class);
    }
}
