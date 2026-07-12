<?php

namespace App\Models;

use Database\Factories\DataImportFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class DataImport extends Model
{
    /** @use HasFactory<DataImportFactory> */
    use HasFactory;

    protected $fillable = [
        'assessment_id',
        'data_connection_id',
        'provider',
        'method',
        'data_types',
        'status',
        'started_at',
        'completed_at',
        'warnings_count',
        'errors_count',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'data_types' => 'array',
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
            'metadata' => 'array',
        ];
    }

    public function assessment(): BelongsTo
    {
        return $this->belongsTo(Assessment::class);
    }

    public function dataConnection(): BelongsTo
    {
        return $this->belongsTo(DataConnection::class);
    }

    public function files(): HasMany
    {
        return $this->hasMany(DataImportFile::class);
    }

    public function errors(): HasMany
    {
        return $this->hasMany(DataImportError::class);
    }
}
