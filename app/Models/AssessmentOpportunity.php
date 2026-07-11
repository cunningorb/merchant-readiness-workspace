<?php

namespace App\Models;

use Database\Factories\AssessmentOpportunityFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AssessmentOpportunity extends Model
{
    /** @use HasFactory<AssessmentOpportunityFactory> */
    use HasFactory;

    protected $fillable = [
        'assessment_id',
        'type',
        'title',
        'summary',
        'minimum_value',
        'maximum_value',
        'unit',
        'confidence',
        'effort',
        'assumptions',
        'evidence',
        'formula_version',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'assumptions' => 'array',
            'evidence' => 'array',
            'minimum_value' => 'decimal:2',
            'maximum_value' => 'decimal:2',
        ];
    }

    public function assessment(): BelongsTo
    {
        return $this->belongsTo(Assessment::class);
    }
}
