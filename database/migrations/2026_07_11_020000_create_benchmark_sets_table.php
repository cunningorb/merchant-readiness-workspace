<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('benchmark_sets', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('version');
            $table->string('source_type');
            $table->string('source_label');
            $table->text('methodology');
            $table->timestamp('effective_from')->nullable();
            $table->timestamp('effective_to')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['name', 'version']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('benchmark_sets');
    }
};
