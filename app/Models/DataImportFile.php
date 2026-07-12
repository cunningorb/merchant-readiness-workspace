<?php

namespace App\Models;

use Database\Factories\DataImportFileFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class DataImportFile extends Model
{
    /** @use HasFactory<DataImportFileFactory> */
    use HasFactory;

    protected $fillable = [
        'data_import_id',
        'data_type',
        'original_filename',
        'stored_path',
        'row_count',
        'accepted_count',
        'rejected_count',
        'fingerprint',
    ];

    public function dataImport(): BelongsTo
    {
        return $this->belongsTo(DataImport::class);
    }

    public function errors(): HasMany
    {
        return $this->hasMany(DataImportError::class);
    }
}
