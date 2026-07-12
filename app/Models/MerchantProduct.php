<?php

namespace App\Models;

use Database\Factories\MerchantProductFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MerchantProduct extends Model
{
    /** @use HasFactory<MerchantProductFactory> */
    use HasFactory;

    protected $fillable = [
        'merchant_id',
        'source_provider',
        'source_import_id',
        'provider_product_id',
        'title',
        'product_type',
        'vendor',
        'tags',
        'description_length',
        'status',
        'variant_count',
        'has_size_option',
        'has_color_option',
        'sparse_description',
        'media_count',
        'low_media',
        'price',
        'compare_at_price',
        'sku',
        'inventory_tracked',
    ];

    protected function casts(): array
    {
        return [
            'tags' => 'array',
            'has_size_option' => 'boolean',
            'has_color_option' => 'boolean',
            'sparse_description' => 'boolean',
            'low_media' => 'boolean',
            'price' => 'decimal:2',
            'compare_at_price' => 'decimal:2',
            'inventory_tracked' => 'boolean',
        ];
    }

    public function merchant(): BelongsTo
    {
        return $this->belongsTo(Merchant::class);
    }

    public function variants(): HasMany
    {
        return $this->hasMany(MerchantProductVariant::class);
    }
}
