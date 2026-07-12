<?php

namespace App\Models;

use Database\Factories\DataImportErrorFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DataImportError extends Model
{
    /** @use HasFactory<DataImportErrorFactory> */
    use HasFactory;

    protected $fillable = [
        'data_import_id',
        'data_import_file_id',
        'row_number',
        'message',
        'context',
    ];

    protected function casts(): array
    {
        return [
            'context' => 'array',
        ];
    }

    public function dataImport(): BelongsTo
    {
        return $this->belongsTo(DataImport::class);
    }

    public function dataImportFile(): BelongsTo
    {
        return $this->belongsTo(DataImportFile::class);
    }
}
