<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('assessment_opportunities', function (Blueprint $table) {
            $table->id();
            $table->foreignUlid('assessment_id')->constrained()->cascadeOnDelete();
            $table->string('type');
            $table->string('title');
            $table->text('summary');
            $table->decimal('minimum_value', 14, 2)->nullable();
            $table->decimal('maximum_value', 14, 2)->nullable();
            $table->string('unit');
            $table->string('confidence');
            $table->string('effort');
            $table->json('assumptions');
            $table->json('evidence');
            $table->string('formula_version');
            $table->integer('sort_order');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('assessment_opportunities');
    }
};
