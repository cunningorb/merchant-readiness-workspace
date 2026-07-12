<?php

namespace App\Services\Imports\Demo;

use App\Models\DataImport;
use RuntimeException;

/**
 * Shared by the three demo importers: reads the 'demo_scenario' key stamped
 * onto the import's metadata by DemoDataProvider::startImport() before
 * process() dispatches the jobs.
 */
trait ResolvesDemoScenario
{
    private function scenarioFor(DataImport $dataImport): string
    {
        $scenario = $dataImport->metadata['demo_scenario'] ?? null;

        if (! is_string($scenario) || $scenario === '') {
            throw new RuntimeException('Demo import is missing a demo_scenario in its metadata.');
        }

        return $scenario;
    }
}
