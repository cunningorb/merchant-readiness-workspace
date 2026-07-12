<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('merchant_products', function (Blueprint $table) {
            $table->id();
            $table->foreignId('merchant_id')->constrained()->cascadeOnDelete();
            $table->string('source_provider');
            $table->foreignId('source_import_id')->nullable()->constrained('data_imports')->nullOnDelete();
            $table->string('provider_product_id');
            $table->string('title');
            $table->string('product_type')->nullable();
            $table->string('vendor')->nullable();
            $table->json('tags')->nullable();
            $table->integer('description_length')->nullable();
            $table->string('status')->nullable();
            $table->integer('variant_count')->nullable();
            $table->boolean('has_size_option')->default(false);
            $table->boolean('has_color_option')->default(false);
            $table->boolean('sparse_description')->default(false);
            $table->integer('media_count')->nullable();
            $table->boolean('low_media')->default(false);
            $table->decimal('price', 10, 2)->nullable();
            $table->decimal('compare_at_price', 10, 2)->nullable();
            $table->string('sku')->nullable();
            $table->boolean('inventory_tracked')->default(false);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('merchant_products');
    }
};
