<?php

namespace App\Jobs;

use App\Contracts\CatalogImporter;
use App\Models\DataImport;
use App\Services\Imports\ImportCoordinator;
use App\Services\Imports\ImportProviderRegistry;

class ProcessCatalogImportJob extends ProcessImportJob
{
    protected function dataType(): string
    {
        return ImportCoordinator::DATA_TYPE_CATALOG;
    }

    protected function resolveImporter(ImportProviderRegistry $registry, string $provider): ?object
    {
        return $registry->catalogImporterFor($provider);
    }

    protected function runImport(object $importer, DataImport $dataImport): void
    {
        /** @var CatalogImporter $importer */
        $importer->importCatalog($dataImport);
    }
}
