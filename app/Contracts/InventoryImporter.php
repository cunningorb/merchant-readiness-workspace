<?php

namespace App\Contracts;

use App\Models\DataImport;

interface InventoryImporter
{
    public function importInventory(DataImport $dataImport): void;
}
