<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('data_imports', function (Blueprint $table) {
            $table->id();
            $table->foreignUlid('assessment_id')->constrained()->cascadeOnDelete();
            $table->foreignId('data_connection_id')->nullable()->constrained()->nullOnDelete();
            $table->string('provider');
            $table->string('method');
            $table->json('data_types');
            $table->string('status');
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->integer('warnings_count')->default(0);
            $table->integer('errors_count')->default(0);
            $table->json('metadata')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('data_imports');
    }
};
