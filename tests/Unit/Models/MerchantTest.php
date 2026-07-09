<?php

namespace Tests\Unit\Models;

use App\Models\Merchant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MerchantTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_merchant_can_be_created_with_expected_attributes(): void
    {
        $merchant = Merchant::factory()->create([
            'company_name' => 'Acme Returns Co.',
            'contact_email' => 'ops@acme.test',
        ]);

        $this->assertDatabaseHas('merchants', [
            'id' => $merchant->id,
            'company_name' => 'Acme Returns Co.',
            'contact_email' => 'ops@acme.test',
        ]);
    }

    public function test_contact_fields_are_nullable(): void
    {
        $merchant = Merchant::factory()->create([
            'contact_name' => null,
            'contact_email' => null,
            'website' => null,
        ]);

        $this->assertNull($merchant->fresh()->contact_name);
        $this->assertNull($merchant->fresh()->contact_email);
        $this->assertNull($merchant->fresh()->website);
    }
}
