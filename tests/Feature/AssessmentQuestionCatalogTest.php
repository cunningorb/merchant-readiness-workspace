<?php

namespace Tests\Feature;

use App\Services\AssessmentQuestionCatalog;
use Tests\TestCase;

class AssessmentQuestionCatalogTest extends TestCase
{
    public function test_catalog_contains_required_milestone_two_sections(): void
    {
        $sections = collect(app(AssessmentQuestionCatalog::class)->sections());

        $this->assertSame([
            'business',
            'catalog',
            'return_policy',
            'exchanges',
            'manual_operations',
            'platform',
        ], $sections->pluck('key')->all());

        $sections->each(fn (array $section) => $this->assertNotEmpty($section['questions']));
    }
}
