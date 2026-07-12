<?php

namespace Tests\Unit\Models;

use App\Models\Assessment;
use App\Models\DataConnection;
use App\Models\Merchant;
use App\Models\MerchantInventoryMetric;
use App\Models\MerchantLocationMetric;
use App\Models\MerchantOrderMetric;
use App\Models\MerchantProduct;
use App\Models\MerchantReturnMetric;
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

    public function test_merchant_has_many_assessments(): void
    {
        $merchant = Merchant::factory()->create();
        $assessments = Assessment::factory()->count(2)->for($merchant)->create();

        $this->assertCount(2, $merchant->fresh()->assessments);
        $this->assertTrue($merchant->assessments->contains($assessments->first()));
    }

    public function test_merchant_has_many_data_connections(): void
    {
        $merchant = Merchant::factory()->create();
        $connection = DataConnection::factory()->for($merchant)->create();

        $this->assertTrue($merchant->fresh()->dataConnections->contains($connection));
    }

    public function test_merchant_has_many_products(): void
    {
        $merchant = Merchant::factory()->create();
        $product = MerchantProduct::factory()->for($merchant)->create();

        $this->assertTrue($merchant->fresh()->products->contains($product));
    }

    public function test_merchant_has_many_order_metrics(): void
    {
        $merchant = Merchant::factory()->create();
        $metric = MerchantOrderMetric::factory()->for($merchant)->create();

        $this->assertTrue($merchant->fresh()->orderMetrics->contains($metric));
    }

    public function test_merchant_has_many_return_metrics(): void
    {
        $merchant = Merchant::factory()->create();
        $metric = MerchantReturnMetric::factory()->for($merchant)->create();

        $this->assertTrue($merchant->fresh()->returnMetrics->contains($metric));
    }

    public function test_merchant_has_many_inventory_metrics(): void
    {
        $merchant = Merchant::factory()->create();
        $metric = MerchantInventoryMetric::factory()->for($merchant)->create();

        $this->assertTrue($merchant->fresh()->inventoryMetrics->contains($metric));
    }

    public function test_merchant_has_many_location_metrics(): void
    {
        $merchant = Merchant::factory()->create();
        $metric = MerchantLocationMetric::factory()->for($merchant)->create();

        $this->assertTrue($merchant->fresh()->locationMetrics->contains($metric));
    }
}
