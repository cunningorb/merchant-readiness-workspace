<?php

namespace App\Models;

use Database\Factories\MerchantFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Merchant extends Model
{
    /** @use HasFactory<MerchantFactory> */
    use HasFactory;

    protected $fillable = [
        'company_name',
        'contact_name',
        'contact_email',
        'website',
        'is_demo',
    ];

    protected function casts(): array
    {
        return [
            'is_demo' => 'boolean',
        ];
    }

    public function assessments(): HasMany
    {
        return $this->hasMany(Assessment::class);
    }

    public function dataConnections(): HasMany
    {
        return $this->hasMany(DataConnection::class);
    }

    public function products(): HasMany
    {
        return $this->hasMany(MerchantProduct::class);
    }

    public function orderMetrics(): HasMany
    {
        return $this->hasMany(MerchantOrderMetric::class);
    }

    public function returnMetrics(): HasMany
    {
        return $this->hasMany(MerchantReturnMetric::class);
    }

    public function inventoryMetrics(): HasMany
    {
        return $this->hasMany(MerchantInventoryMetric::class);
    }

    public function locationMetrics(): HasMany
    {
        return $this->hasMany(MerchantLocationMetric::class);
    }
}
