<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('assessments', function (Blueprint $table) {
            $table->unsignedTinyInteger('overall_score')->nullable()->after('status');
            $table->string('overall_tier')->nullable()->after('overall_score');
            $table->json('section_scores')->nullable()->after('overall_tier');
        });
    }

    public function down(): void
    {
        Schema::table('assessments', function (Blueprint $table) {
            $table->dropColumn(['overall_score', 'overall_tier', 'section_scores']);
        });
    }
};
