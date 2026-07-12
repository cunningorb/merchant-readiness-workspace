<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('assessment_benchmark_comparisons', function (Blueprint $table) {
            $table->id();
            $table->foreignUlid('assessment_id')->constrained()->cascadeOnDelete();
            $table->foreignId('benchmark_set_id')->constrained()->cascadeOnDelete();
            $table->string('metric_key');
            $table->string('label');
            $table->decimal('merchant_value', 14, 2);
            $table->decimal('minimum_value', 14, 2);
            $table->decimal('maximum_value', 14, 2);
            $table->string('unit');
            $table->text('interpretation');
            $table->string('source_type');
            $table->string('source_label');
            $table->text('methodology');
            $table->string('benchmark_version');
            $table->integer('sort_order');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('assessment_benchmark_comparisons');
    }
};
