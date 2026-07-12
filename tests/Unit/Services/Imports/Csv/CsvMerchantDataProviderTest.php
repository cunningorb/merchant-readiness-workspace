<?php

namespace Tests\Unit\Services\Imports\Csv;

use App\Services\Imports\Csv\CsvMerchantDataProvider;
use Tests\TestCase;

class CsvMerchantDataProviderTest extends TestCase
{
    public function test_provider_identifies_itself_as_csv(): void
    {
        $this->assertSame('csv', (new CsvMerchantDataProvider)->provider());
    }

    public function test_supports_all_three_data_types(): void
    {
        $provider = new CsvMerchantDataProvider;

        $this->assertTrue($provider->supports('catalog'));
        $this->assertTrue($provider->supports('orders_returns'));
        $this->assertTrue($provider->supports('inventory_locations'));
    }

    public function test_does_not_support_an_unknown_data_type(): void
    {
        $this->assertFalse((new CsvMerchantDataProvider)->supports('not_a_real_type'));
    }
}
