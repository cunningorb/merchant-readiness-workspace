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

    public const TYPE_RETAINED_REVENUE = 'retained_revenue';

    public const TYPE_MANUAL_WORK_SAVINGS = 'manual_work_savings';

    public const TYPE_SUPPORT_CONTACT_REDUCTION = 'support_contact_reduction';

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
