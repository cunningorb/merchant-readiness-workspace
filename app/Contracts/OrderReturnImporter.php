<?php

namespace App\Contracts;

use App\Models\DataImport;

interface OrderReturnImporter
{
    public function importOrdersAndReturns(DataImport $dataImport): void;
}
