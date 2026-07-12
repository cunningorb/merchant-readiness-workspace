<?php

namespace App\Services\Imports\Csv;

use App\Contracts\MerchantDataProvider;
use App\Services\Imports\ImportCoordinator;

/**
 * The 'csv' provider: a merchant improves assessment accuracy by uploading
 * their own store data as three documented CSV files instead of connecting
 * a live store integration. This is deliberately a simple, framework-proving
 * column format of our own — not a real Shopify/WooCommerce export reader
 * with alias detection, which is Milestone 12's scope.
 */
class CsvMerchantDataProvider implements MerchantDataProvider
{
    public function provider(): string
    {
        return 'csv';
    }

    public function supports(string $dataType): bool
    {
        return in_array($dataType, [
            ImportCoordinator::DATA_TYPE_CATALOG,
            ImportCoordinator::DATA_TYPE_ORDERS_RETURNS,
            ImportCoordinator::DATA_TYPE_INVENTORY_LOCATIONS,
        ], true);
    }
}
