<?php

namespace App\Models;

use Database\Factories\AssessmentAnswerEvidenceFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AssessmentAnswerEvidence extends Model
{
    /** @use HasFactory<AssessmentAnswerEvidenceFactory> */
    use HasFactory;

    protected $table = 'assessment_answer_evidence';

    protected $fillable = [
        'assessment_id',
        'question_key',
        'source_type',
        'source_label',
        'confidence',
        'value',
        'evidence_url',
        'evidence_snippet',
        'observed_period_start',
        'observed_period_end',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'value' => 'array',
            'metadata' => 'array',
            'observed_period_start' => 'date',
            'observed_period_end' => 'date',
        ];
    }

    public function assessment(): BelongsTo
    {
        return $this->belongsTo(Assessment::class);
    }
}
