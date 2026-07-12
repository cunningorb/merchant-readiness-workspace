<?php

namespace App\Services\Imports;

use App\Contracts\CatalogImporter;
use App\Contracts\InventoryImporter;
use App\Contracts\OrderReturnImporter;

/**
 * Registry of the importer capabilities each provider offers.
 *
 * Providers register themselves here (from their own service providers in later
 * tasks); the coordinator and queued jobs look up the right importer for a
 * given provider + data type at run time. Bound as a singleton so a provider's
 * registration is visible to the jobs that consume it.
 */
class ImportProviderRegistry
{
    /** @var array<string, CatalogImporter> */
    private array $catalogImporters = [];

    /** @var array<string, OrderReturnImporter> */
    private array $orderReturnImporters = [];

    /** @var array<string, InventoryImporter> */
    private array $inventoryImporters = [];

    public function register(
        string $provider,
        ?CatalogImporter $catalogImporter = null,
        ?OrderReturnImporter $orderReturnImporter = null,
        ?InventoryImporter $inventoryImporter = null,
    ): void {
        if ($catalogImporter !== null) {
            $this->catalogImporters[$provider] = $catalogImporter;
        }

        if ($orderReturnImporter !== null) {
            $this->orderReturnImporters[$provider] = $orderReturnImporter;
        }

        if ($inventoryImporter !== null) {
            $this->inventoryImporters[$provider] = $inventoryImporter;
        }
    }

    public function catalogImporterFor(string $provider): ?CatalogImporter
    {
        return $this->catalogImporters[$provider] ?? null;
    }

    public function orderReturnImporterFor(string $provider): ?OrderReturnImporter
    {
        return $this->orderReturnImporters[$provider] ?? null;
    }

    public function inventoryImporterFor(string $provider): ?InventoryImporter
    {
        return $this->inventoryImporters[$provider] ?? null;
    }
}
