<?php

namespace App\Jobs;

use App\Contracts\InventoryImporter;
use App\Models\DataImport;
use App\Services\Imports\ImportCoordinator;
use App\Services\Imports\ImportProviderRegistry;

class ProcessInventoryImportJob extends ProcessImportJob
{
    protected function dataType(): string
    {
        return ImportCoordinator::DATA_TYPE_INVENTORY_LOCATIONS;
    }

    protected function resolveImporter(ImportProviderRegistry $registry, string $provider): ?object
    {
        return $registry->inventoryImporterFor($provider);
    }

    protected function runImport(object $importer, DataImport $dataImport): void
    {
        /** @var InventoryImporter $importer */
        $importer->importInventory($dataImport);
    }
}
