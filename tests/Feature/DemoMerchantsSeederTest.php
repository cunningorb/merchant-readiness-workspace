<?php

namespace Tests\Feature;

use App\Models\Merchant;
use Database\Seeders\DemoMerchantsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DemoMerchantsSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_creates_three_demo_merchants_with_expected_scores(): void
    {
        $this->seed(DemoMerchantsSeeder::class);

        $this->assertSame(3, Merchant::where('is_demo', true)->count());

        $thistle = Merchant::where('company_name', 'Thistle & Bloom Apparel')->firstOrFail();
        $thistleAssessment = $thistle->assessments->first();
        $this->assertTrue($thistle->is_demo);
        $this->assertSame('submitted', $thistleAssessment->status);
        $this->assertSame(0, $thistleAssessment->overall_score);
        $this->assertSame('Foundational', $thistleAssessment->overall_tier);
        $this->assertNotNull($thistleAssessment->report);

        $northline = Merchant::where('company_name', 'Northline Outdoor Supply')->firstOrFail();
        $northlineAssessment = $northline->assessments->first();
        $this->assertSame(71, $northlineAssessment->overall_score);
        $this->assertSame('Established', $northlineAssessment->overall_tier);
        $this->assertNotNull($northlineAssessment->report);

        $vantage = Merchant::where('company_name', 'Vantage Home Goods')->firstOrFail();
        $vantageAssessment = $vantage->assessments->first();
        $this->assertSame(100, $vantageAssessment->overall_score);
        $this->assertSame('Advanced', $vantageAssessment->overall_tier);
        $this->assertNotNull($vantageAssessment->report);
    }

    public function test_is_idempotent(): void
    {
        $this->seed(DemoMerchantsSeeder::class);
        $this->seed(DemoMerchantsSeeder::class);

        $this->assertSame(3, Merchant::where('is_demo', true)->count());
    }

    public function test_self_heals_a_partial_seed(): void
    {
        $this->seed(DemoMerchantsSeeder::class);

        // Simulate a crash mid-seed on a prior run: two of the three demo
        // merchants never got created, as if the process died after the
        // first profile but before the second and third.
        Merchant::where('company_name', '!=', 'Thistle & Bloom Apparel')
            ->where('is_demo', true)
            ->each(fn (Merchant $merchant) => $merchant->delete());

        $this->assertSame(1, Merchant::where('is_demo', true)->count());

        $this->seed(DemoMerchantsSeeder::class);

        $this->assertSame(3, Merchant::where('is_demo', true)->count());
        $this->assertSame(1, Merchant::where('company_name', 'Thistle & Bloom Apparel')->count());
        $this->assertNotNull(Merchant::where('company_name', 'Northline Outdoor Supply')->first());
        $this->assertNotNull(Merchant::where('company_name', 'Vantage Home Goods')->first());
    }
}
