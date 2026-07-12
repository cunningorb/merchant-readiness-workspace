<?php

namespace App\Models;

use Database\Factories\MerchantProductVariantFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MerchantProductVariant extends Model
{
    /** @use HasFactory<MerchantProductVariantFactory> */
    use HasFactory;

    protected $fillable = [
        'merchant_product_id',
        'provider_variant_id',
        'option_names',
        'option_values',
        'price',
        'sku',
        'inventory_tracked',
    ];

    protected function casts(): array
    {
        return [
            'option_names' => 'array',
            'option_values' => 'array',
            'price' => 'decimal:2',
            'inventory_tracked' => 'boolean',
        ];
    }

    public function merchantProduct(): BelongsTo
    {
        return $this->belongsTo(MerchantProduct::class);
    }
}
