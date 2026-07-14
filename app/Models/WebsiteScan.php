<?php

namespace App\Models;

use Database\Factories\WebsiteScanFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WebsiteScan extends Model
{
    /** @use HasFactory<WebsiteScanFactory> */
    use HasFactory;

    protected $fillable = [
        'assessment_id',
        'url',
        'status',
        'pages_scanned',
        'extracted_data',
        'error_message',
        'started_at',
        'completed_at',
    ];

    protected function casts(): array
    {
        return [
            'extracted_data' => 'array',
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
        ];
    }

    public function assessment(): BelongsTo
    {
        return $this->belongsTo(Assessment::class);
    }
}
