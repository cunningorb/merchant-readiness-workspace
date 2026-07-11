<?php

namespace Tests\Feature;

use App\Models\Merchant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DemoResetCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_creates_demo_merchants_when_none_exist(): void
    {
        $this->artisan('demo:reset')->assertSuccessful();

        $this->assertSame(3, Merchant::where('is_demo', true)->count());
    }

    public function test_running_it_again_replaces_rather_than_duplicates(): void
    {
        $this->artisan('demo:reset')->assertSuccessful();
        $firstRunIds = Merchant::where('is_demo', true)->pluck('id');

        $this->artisan('demo:reset')->assertSuccessful();
        $secondRunIds = Merchant::where('is_demo', true)->pluck('id');

        $this->assertSame(3, $secondRunIds->count());
        $this->assertEmpty(
            $firstRunIds->intersect($secondRunIds),
            'Expected the second run to create entirely new merchant rows, not reuse the first run\'s IDs.'
        );
    }

    public function test_real_merchant_is_untouched_by_reset(): void
    {
        $realMerchant = Merchant::factory()->create([
            'is_demo' => false,
            'company_name' => 'Real Prospect Co',
        ]);

        $this->artisan('demo:reset')->assertSuccessful();

        $this->assertDatabaseHas('merchants', [
            'id' => $realMerchant->id,
            'company_name' => 'Real Prospect Co',
        ]);
        $this->assertSame(3, Merchant::where('is_demo', true)->count());
        $this->assertSame(4, Merchant::count());
    }
}
