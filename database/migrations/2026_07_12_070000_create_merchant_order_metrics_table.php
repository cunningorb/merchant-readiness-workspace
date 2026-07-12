<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('merchant_order_metrics', function (Blueprint $table) {
            $table->id();
            $table->foreignId('merchant_id')->constrained()->cascadeOnDelete();
            $table->foreignUlid('assessment_id')->constrained()->cascadeOnDelete();
            $table->string('source_provider');
            $table->foreignId('source_import_id')->nullable()->constrained('data_imports')->nullOnDelete();
            $table->date('source_period_start')->nullable();
            $table->date('source_period_end')->nullable();
            $table->integer('order_count')->nullable();
            $table->integer('annualized_order_volume')->nullable();
            $table->decimal('average_order_value', 10, 2)->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('merchant_order_metrics');
    }
};
