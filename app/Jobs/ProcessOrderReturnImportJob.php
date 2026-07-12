<?php

namespace App\Jobs;

use App\Contracts\OrderReturnImporter;
use App\Models\DataImport;
use App\Services\Imports\ImportCoordinator;
use App\Services\Imports\ImportProviderRegistry;

class ProcessOrderReturnImportJob extends ProcessImportJob
{
    protected function dataType(): string
    {
        return ImportCoordinator::DATA_TYPE_ORDERS_RETURNS;
    }

    protected function resolveImporter(ImportProviderRegistry $registry, string $provider): ?object
    {
        return $registry->orderReturnImporterFor($provider);
    }

    protected function runImport(object $importer, DataImport $dataImport): void
    {
        /** @var OrderReturnImporter $importer */
        $importer->importOrdersAndReturns($dataImport);
    }
}
