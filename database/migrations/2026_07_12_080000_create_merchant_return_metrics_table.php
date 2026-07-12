<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('merchant_return_metrics', function (Blueprint $table) {
            $table->id();
            $table->foreignId('merchant_id')->constrained()->cascadeOnDelete();
            $table->foreignUlid('assessment_id')->constrained()->cascadeOnDelete();
            $table->string('source_provider');
            $table->foreignId('source_import_id')->nullable()->constrained('data_imports')->nullOnDelete();
            $table->date('source_period_start')->nullable();
            $table->date('source_period_end')->nullable();
            $table->decimal('refund_amount_total', 12, 2)->nullable();
            $table->integer('refund_units_total')->nullable();
            $table->decimal('estimated_refund_rate', 6, 4)->nullable();
            $table->decimal('exchange_share', 6, 4)->nullable();
            $table->decimal('refund_only_share', 6, 4)->nullable();
            $table->decimal('average_time_to_refund_days', 6, 2)->nullable();
            $table->json('top_refund_categories')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('merchant_return_metrics');
    }
};
