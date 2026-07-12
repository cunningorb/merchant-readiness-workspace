<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('merchant_location_metrics', function (Blueprint $table) {
            $table->id();
            $table->foreignId('merchant_id')->constrained()->cascadeOnDelete();
            $table->foreignUlid('assessment_id')->constrained()->cascadeOnDelete();
            $table->string('source_provider');
            $table->foreignId('source_import_id')->nullable()->constrained('data_imports')->nullOnDelete();
            $table->integer('active_location_count')->nullable();
            $table->string('operational_complexity_score')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('merchant_location_metrics');
    }
};
