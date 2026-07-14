<?php

namespace App\Models;

use Database\Factories\AssessmentFactory;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Assessment extends Model
{
    /** @use HasFactory<AssessmentFactory> */
    use HasFactory, HasUlids;

    protected $fillable = [
        'merchant_id',
        'status',
        'started_at',
        'submitted_at',
        'overall_score',
        'overall_tier',
        'section_scores',
    ];

    protected function casts(): array
    {
        return [
            'started_at' => 'datetime',
            'submitted_at' => 'datetime',
            'section_scores' => 'array',
        ];
    }

    public function merchant(): BelongsTo
    {
        return $this->belongsTo(Merchant::class);
    }

    public function answers(): HasMany
    {
        return $this->hasMany(AssessmentAnswer::class);
    }

    public function answerValue(string $questionKey): mixed
    {
        return $this->answers->firstWhere('question_key', $questionKey)?->value;
    }

    public function recommendations(): HasMany
    {
        return $this->hasMany(Recommendation::class);
    }

    public function opportunities(): HasMany
    {
        return $this->hasMany(AssessmentOpportunity::class)->orderBy('sort_order');
    }

    public function report(): HasOne
    {
        return $this->hasOne(Report::class);
    }

    public function benchmarkComparisons(): HasMany
    {
        return $this->hasMany(AssessmentBenchmarkComparison::class)->orderBy('sort_order');
    }

    public function websiteScans(): HasMany
    {
        return $this->hasMany(WebsiteScan::class);
    }

    public function answerEvidence(): HasMany
    {
        return $this->hasMany(AssessmentAnswerEvidence::class);
    }

    public function dataImports(): HasMany
    {
        return $this->hasMany(DataImport::class);
    }
}
