<?php

namespace App\Contracts;

use App\Models\DataImport;

interface CatalogImporter
{
    public function importCatalog(DataImport $dataImport): void;
}
