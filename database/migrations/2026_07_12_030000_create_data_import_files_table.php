<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('data_import_files', function (Blueprint $table) {
            $table->id();
            $table->foreignId('data_import_id')->constrained()->cascadeOnDelete();
            $table->string('data_type');
            $table->string('original_filename');
            $table->string('stored_path');
            $table->integer('row_count')->nullable();
            $table->integer('accepted_count')->nullable();
            $table->integer('rejected_count')->nullable();
            $table->string('fingerprint')->index();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('data_import_files');
    }
};
