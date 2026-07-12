<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('merchant_product_variants', function (Blueprint $table) {
            $table->id();
            $table->foreignId('merchant_product_id')->constrained()->cascadeOnDelete();
            $table->string('provider_variant_id')->nullable();
            $table->json('option_names')->nullable();
            $table->json('option_values')->nullable();
            $table->decimal('price', 10, 2)->nullable();
            $table->string('sku')->nullable();
            $table->boolean('inventory_tracked')->default(false);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('merchant_product_variants');
    }
};
