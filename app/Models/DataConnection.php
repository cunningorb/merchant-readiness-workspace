<?php

namespace App\Models;

use Database\Factories\DataConnectionFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DataConnection extends Model
{
    /** @use HasFactory<DataConnectionFactory> */
    use HasFactory;

    protected $fillable = [
        'merchant_id',
        'provider',
        'status',
        'credentials',
        'granted_scopes',
        'connected_at',
        'disconnected_at',
    ];

    protected function casts(): array
    {
        return [
            'credentials' => 'encrypted:array',
            'granted_scopes' => 'array',
            'connected_at' => 'datetime',
            'disconnected_at' => 'datetime',
        ];
    }

    public function merchant(): BelongsTo
    {
        return $this->belongsTo(Merchant::class);
    }
}
