<?php

namespace App\Contracts;

use App\Models\DataImport;

interface ImportMetricCalculator
{
    /**
     * Compute aggregate metrics from the normalized rows produced for the
     * given import.
     */
    public function calculate(DataImport $dataImport): array;
}
