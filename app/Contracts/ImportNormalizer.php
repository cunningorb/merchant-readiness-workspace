<?php

namespace App\Contracts;

use App\Models\DataImport;

interface ImportNormalizer
{
    /**
     * Normalize one raw source record (a CSV row, a future API response
     * fragment) into a normalized associative array shape.
     */
    public function normalize(mixed $rawRecord, DataImport $dataImport): array;
}
