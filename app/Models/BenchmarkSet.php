<?php

namespace App\Models;

use Database\Factories\BenchmarkSetFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class BenchmarkSet extends Model
{
    /** @use HasFactory<BenchmarkSetFactory> */
    use HasFactory;

    protected $fillable = [
        'name',
        'version',
        'source_type',
        'source_label',
        'methodology',
        'effective_from',
        'effective_to',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'effective_from' => 'datetime',
            'effective_to' => 'datetime',
        ];
    }

    public function values(): HasMany
    {
        return $this->hasMany(BenchmarkValue::class);
    }
}
