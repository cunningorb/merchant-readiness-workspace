<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('merchant_inventory_metrics', function (Blueprint $table) {
            $table->id();
            $table->foreignId('merchant_id')->constrained()->cascadeOnDelete();
            $table->foreignUlid('assessment_id')->constrained()->cascadeOnDelete();
            $table->string('source_provider');
            $table->foreignId('source_import_id')->nullable()->constrained('data_imports')->nullOnDelete();
            $table->decimal('percent_multi_location_stock', 5, 2)->nullable();
            $table->decimal('percent_low_or_zero_stock', 5, 2)->nullable();
            $table->decimal('sku_completeness', 5, 2)->nullable();
            $table->string('exchange_availability_risk')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('merchant_inventory_metrics');
    }
};
