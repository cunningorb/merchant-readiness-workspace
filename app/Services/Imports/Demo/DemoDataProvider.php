<?php

namespace App\Services\Imports\Demo;

use App\Contracts\MerchantDataProvider;
use App\Enums\ImportMethod;
use App\Models\Assessment;
use App\Models\DataImport;
use App\Services\Imports\ImportCoordinator;
use InvalidArgumentException;

/**
 * The 'demo' provider: entirely synthetic, fixture-based data so the product
 * always has something to show without a merchant connecting a real store.
 * This must never be presented as real merchant data anywhere (the wizard,
 * built in a later task, is responsible for labeling it as a demo).
 */
class DemoDataProvider implements MerchantDataProvider
{
    public function provider(): string
    {
        return 'demo';
    }

    public function supports(string $dataType): bool
    {
        return in_array($dataType, [
            ImportCoordinator::DATA_TYPE_CATALOG,
            ImportCoordinator::DATA_TYPE_ORDERS_RETURNS,
            ImportCoordinator::DATA_TYPE_INVENTORY_LOCATIONS,
        ], true);
    }

    /**
     * Convenience entry point that drives the full three-phase
     * ImportCoordinator API for a single demo scenario.
     */
    public static function startImport(Assessment $assessment, string $scenario): DataImport
    {
        if (! in_array($scenario, DemoScenarios::SCENARIOS, true)) {
            throw new InvalidArgumentException("Unknown demo scenario '{$scenario}'.");
        }

        $coordinator = app(ImportCoordinator::class);

        $dataImport = $coordinator->create($assessment, provider: 'demo', method: ImportMethod::Demo->value);

        // Stamp the scenario onto metadata BEFORE process(), since the queue
        // may run synchronously within this same request/test and the
        // importers read this key while the job executes.
        $metadata = $dataImport->metadata ?? [];
        $metadata['demo_scenario'] = $scenario;
        $dataImport->metadata = $metadata;
        $dataImport->save();

        // No fingerprint seeds: demo data is synthetic and re-running the same
        // scenario for a merchant should always produce a fresh, fully
        // populated import rather than being deduped against a prior demo run
        // the way real CSV/API content is.
        $coordinator->attachDataType($dataImport, ImportCoordinator::DATA_TYPE_CATALOG);
        $coordinator->attachDataType($dataImport, ImportCoordinator::DATA_TYPE_ORDERS_RETURNS);
        $coordinator->attachDataType($dataImport, ImportCoordinator::DATA_TYPE_INVENTORY_LOCATIONS);

        $coordinator->process($dataImport);

        return $dataImport->fresh();
    }
}
