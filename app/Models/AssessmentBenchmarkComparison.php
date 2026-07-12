<?php

namespace App\Models;

use Database\Factories\AssessmentBenchmarkComparisonFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AssessmentBenchmarkComparison extends Model
{
    /** @use HasFactory<AssessmentBenchmarkComparisonFactory> */
    use HasFactory;

    protected $fillable = [
        'assessment_id',
        'benchmark_set_id',
        'metric_key',
        'label',
        'merchant_value',
        'minimum_value',
        'maximum_value',
        'unit',
        'interpretation',
        'source_type',
        'source_label',
        'methodology',
        'benchmark_version',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'merchant_value' => 'decimal:2',
            'minimum_value' => 'decimal:2',
            'maximum_value' => 'decimal:2',
        ];
    }

    public function assessment(): BelongsTo
    {
        return $this->belongsTo(Assessment::class);
    }

    public function benchmarkSet(): BelongsTo
    {
        return $this->belongsTo(BenchmarkSet::class);
    }
}
