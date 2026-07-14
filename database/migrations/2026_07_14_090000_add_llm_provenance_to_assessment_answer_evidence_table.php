<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('assessment_answer_evidence', function (Blueprint $table) {
            $table->string('provider')->nullable()->after('source_label');
            $table->string('model')->nullable()->after('provider');
            $table->string('prompt_version')->nullable()->after('model');
            $table->boolean('requires_confirmation')->default(false)->after('confidence');
        });
    }

    public function down(): void
    {
        Schema::table('assessment_answer_evidence', function (Blueprint $table) {
            $table->dropColumn(['provider', 'model', 'prompt_version', 'requires_confirmation']);
        });
    }
};
