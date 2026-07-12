<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('benchmark_values', function (Blueprint $table) {
            $table->id();
            $table->foreignId('benchmark_set_id')->constrained()->cascadeOnDelete();
            $table->string('metric_key');
            $table->string('industry')->nullable();
            $table->string('platform')->nullable();
            $table->decimal('annual_order_volume_min', 14, 2)->nullable();
            $table->decimal('annual_order_volume_max', 14, 2)->nullable();
            $table->string('catalog_profile')->nullable();
            $table->decimal('minimum_value', 14, 2)->nullable();
            $table->decimal('maximum_value', 14, 2)->nullable();
            $table->string('unit');
            $table->integer('sample_size')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('benchmark_values');
    }
};
