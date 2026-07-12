<?php

namespace Tests\Unit\Services\Imports;

use App\Contracts\CatalogImporter;
use App\Contracts\InventoryImporter;
use App\Contracts\OrderReturnImporter;
use App\Models\DataImport;
use App\Services\Imports\ImportProviderRegistry;
use PHPUnit\Framework\TestCase;

class ImportProviderRegistryTest extends TestCase
{
    private function catalogImporter(): CatalogImporter
    {
        return new class implements CatalogImporter
        {
            public function importCatalog(DataImport $dataImport): void {}
        };
    }

    private function orderReturnImporter(): OrderReturnImporter
    {
        return new class implements OrderReturnImporter
        {
            public function importOrdersAndReturns(DataImport $dataImport): void {}
        };
    }

    private function inventoryImporter(): InventoryImporter
    {
        return new class implements InventoryImporter
        {
            public function importInventory(DataImport $dataImport): void {}
        };
    }

    public function test_it_registers_and_looks_up_each_importer_type(): void
    {
        $registry = new ImportProviderRegistry;
        $catalog = $this->catalogImporter();
        $orders = $this->orderReturnImporter();
        $inventory = $this->inventoryImporter();

        $registry->register(
            provider: 'demo',
            catalogImporter: $catalog,
            orderReturnImporter: $orders,
            inventoryImporter: $inventory,
        );

        $this->assertSame($catalog, $registry->catalogImporterFor('demo'));
        $this->assertSame($orders, $registry->orderReturnImporterFor('demo'));
        $this->assertSame($inventory, $registry->inventoryImporterFor('demo'));
    }

    public function test_unregistered_provider_returns_null(): void
    {
        $registry = new ImportProviderRegistry;

        $this->assertNull($registry->catalogImporterFor('shopify'));
        $this->assertNull($registry->orderReturnImporterFor('shopify'));
        $this->assertNull($registry->inventoryImporterFor('shopify'));
    }

    public function test_a_provider_may_register_only_some_importer_types(): void
    {
        $registry = new ImportProviderRegistry;
        $catalog = $this->catalogImporter();

        $registry->register(provider: 'csv', catalogImporter: $catalog);

        $this->assertSame($catalog, $registry->catalogImporterFor('csv'));
        $this->assertNull($registry->orderReturnImporterFor('csv'));
        $this->assertNull($registry->inventoryImporterFor('csv'));
    }

    public function test_registering_the_same_provider_again_merges_importers(): void
    {
        $registry = new ImportProviderRegistry;
        $catalog = $this->catalogImporter();
        $orders = $this->orderReturnImporter();

        $registry->register(provider: 'csv', catalogImporter: $catalog);
        $registry->register(provider: 'csv', orderReturnImporter: $orders);

        $this->assertSame($catalog, $registry->catalogImporterFor('csv'));
        $this->assertSame($orders, $registry->orderReturnImporterFor('csv'));
    }
}
