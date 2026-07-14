<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('assessment_answer_evidence', function (Blueprint $table) {
            $table->id();
            $table->foreignUlid('assessment_id')->constrained()->cascadeOnDelete();
            $table->string('question_key');
            $table->string('source_type');
            $table->string('source_label');
            $table->string('confidence')->nullable();
            $table->json('value')->nullable();
            $table->string('evidence_url')->nullable();
            $table->text('evidence_snippet')->nullable();
            $table->date('observed_period_start')->nullable();
            $table->date('observed_period_end')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['assessment_id', 'question_key']);
            $table->index(['assessment_id', 'source_type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('assessment_answer_evidence');
    }
};
