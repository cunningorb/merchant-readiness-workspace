<?php

namespace Tests\Unit\Jobs;

use App\Contracts\CatalogImporter;
use App\Contracts\OrderReturnImporter;
use App\Enums\ImportStatus;
use App\Jobs\ProcessCatalogImportJob;
use App\Jobs\ProcessOrderReturnImportJob;
use App\Models\Assessment;
use App\Models\DataImport;
use App\Services\Imports\ImportCoordinator;
use App\Services\Imports\ImportProviderRegistry;
use Illuminate\Foundation\Testing\RefreshDatabase;
use RuntimeException;
use Tests\TestCase;

class ProcessImportJobTest extends TestCase
{
    use RefreshDatabase;

    private function registry(): ImportProviderRegistry
    {
        $registry = new ImportProviderRegistry;
        $this->app->instance(ImportProviderRegistry::class, $registry);

        return $registry;
    }

    private function import(array $dataTypes, string $provider = 'demo', array $metadata = []): DataImport
    {
        $assessment = Assessment::factory()->create();

        return DataImport::factory()->create([
            'assessment_id' => $assessment->id,
            'provider' => $provider,
            'data_types' => $dataTypes,
            'status' => ImportStatus::Queued->value,
            'errors_count' => 0,
            'metadata' => array_merge(['pending_data_types' => $dataTypes], $metadata),
        ]);
    }

    private function successfulCatalogImporter(): CatalogImporter
    {
        return new class implements CatalogImporter
        {
            public function importCatalog(DataImport $dataImport): void {}
        };
    }

    private function throwingCatalogImporter(string $message): CatalogImporter
    {
        return new class($message) implements CatalogImporter
        {
            public function __construct(private string $message) {}

            public function importCatalog(DataImport $dataImport): void
            {
                throw new RuntimeException($this->message);
            }
        };
    }

    private function successfulOrderReturnImporter(): OrderReturnImporter
    {
        return new class implements OrderReturnImporter
        {
            public function importOrdersAndReturns(DataImport $dataImport): void {}
        };
    }

    // --- single data type outcomes ----------------------------------------

    public function test_all_data_types_succeed_completes_the_import(): void
    {
        $registry = $this->registry();
        $registry->register(provider: 'demo', catalogImporter: $this->successfulCatalogImporter());
        $import = $this->import(['catalog']);

        (new ProcessCatalogImportJob($import->id))->handle($registry);

        $fresh = $import->fresh();
        $this->assertSame(ImportStatus::Completed->value, $fresh->status);
        $this->assertSame(0, $fresh->errors_count);
        $this->assertSame([], $fresh->metadata['pending_data_types']);
        $this->assertNotNull($fresh->completed_at);
        $this->assertSame(0, $fresh->errors()->count());
    }

    public function test_missing_importer_records_an_error_and_fails_the_data_type(): void
    {
        $registry = $this->registry(); // nothing registered
        $import = $this->import(['catalog']);

        (new ProcessCatalogImportJob($import->id))->handle($registry);

        $fresh = $import->fresh();
        $this->assertSame(ImportStatus::Failed->value, $fresh->status);
        $this->assertSame(1, $fresh->errors_count);
        $this->assertSame(1, $fresh->errors()->count());
        $this->assertStringContainsString(
            "No catalog importer registered for provider 'demo'.",
            $fresh->errors()->first()->message,
        );
    }

    public function test_importer_that_throws_records_the_message_and_fails_the_data_type(): void
    {
        $registry = $this->registry();
        $registry->register(provider: 'demo', catalogImporter: $this->throwingCatalogImporter('Boom while importing'));
        $import = $this->import(['catalog']);

        (new ProcessCatalogImportJob($import->id))->handle($registry);

        $fresh = $import->fresh();
        $this->assertSame(ImportStatus::Failed->value, $fresh->status);
        $this->assertSame(1, $fresh->errors_count);
        $this->assertStringContainsString('Boom while importing', $fresh->errors()->first()->message);
    }

    // --- multi data type outcomes -----------------------------------------

    public function test_some_succeed_some_fail_completes_with_warnings(): void
    {
        $registry = $this->registry();
        $registry->register(
            provider: 'demo',
            catalogImporter: $this->successfulCatalogImporter(),
            // no order-return importer registered => that data type fails
        );
        $import = $this->import(['catalog', 'orders_returns']);

        (new ProcessCatalogImportJob($import->id))->handle($registry);
        (new ProcessOrderReturnImportJob($import->id))->handle($registry);

        $fresh = $import->fresh();
        $this->assertSame(ImportStatus::CompletedWithWarnings->value, $fresh->status);
        $this->assertSame(1, $fresh->errors_count);
        $this->assertSame([], $fresh->metadata['pending_data_types']);
    }

    public function test_all_data_types_fail_marks_the_import_failed(): void
    {
        $registry = $this->registry(); // nothing registered => both fail
        $import = $this->import(['catalog', 'orders_returns']);

        (new ProcessCatalogImportJob($import->id))->handle($registry);
        (new ProcessOrderReturnImportJob($import->id))->handle($registry);

        $fresh = $import->fresh();
        $this->assertSame(ImportStatus::Failed->value, $fresh->status);
        $this->assertSame(2, $fresh->errors_count);
    }

    public function test_a_non_final_job_leaves_the_import_processing(): void
    {
        $registry = $this->registry();
        $registry->register(
            provider: 'demo',
            catalogImporter: $this->successfulCatalogImporter(),
            orderReturnImporter: $this->successfulOrderReturnImporter(),
        );
        $import = $this->import(['catalog', 'orders_returns']);

        (new ProcessCatalogImportJob($import->id))->handle($registry);

        $fresh = $import->fresh();
        $this->assertSame(ImportStatus::Processing->value, $fresh->status);
        $this->assertSame(['orders_returns'], $fresh->metadata['pending_data_types']);
        $this->assertNull($fresh->completed_at);
    }

    // --- cancellation ------------------------------------------------------

    public function test_a_cancelled_import_short_circuits_the_job(): void
    {
        $registry = $this->registry();
        $registry->register(provider: 'demo', catalogImporter: $this->throwingCatalogImporter('should never run'));
        $import = $this->import(['catalog']);
        $import->update(['status' => ImportStatus::Cancelled->value]);

        (new ProcessCatalogImportJob($import->id))->handle($registry);

        $fresh = $import->fresh();
        $this->assertSame(ImportStatus::Cancelled->value, $fresh->status);
        $this->assertSame(0, $fresh->errors()->count());
        // pending list untouched because the job returned early
        $this->assertSame(['catalog'], $fresh->metadata['pending_data_types']);
    }

    // --- race-safe finalization -------------------------------------------

    /**
     * Two jobs for the same import can reach finalization having both observed
     * the full pending list before either removed its own data type. A naive
     * read-modify-write on a stale in-memory model would lose one removal and
     * leave the import stuck (never finalized). finalizeDataType() re-reads the
     * pending list from the row it locks inside the transaction, so the result
     * is correct regardless of the two callers' stale snapshots.
     */
    public function test_finalization_is_safe_against_concurrent_stale_snapshots(): void
    {
        $coordinator = new ImportCoordinator;
        $import = $this->import(['catalog', 'orders_returns']);

        // Two jobs each captured the import while pending held both data types.
        $snapshotA = DataImport::find($import->id);
        $snapshotB = DataImport::find($import->id);
        $this->assertSame(['catalog', 'orders_returns'], $snapshotA->metadata['pending_data_types']);
        $this->assertSame(['catalog', 'orders_returns'], $snapshotB->metadata['pending_data_types']);

        // Finalization happens by id: each call re-reads the locked row rather
        // than trusting the stale snapshot it was handed.
        $coordinator->finalizeDataType($snapshotA->id, 'catalog', false);

        // After the first removal the import must NOT yet be finalized.
        $afterFirst = $import->fresh();
        $this->assertSame(['orders_returns'], $afterFirst->metadata['pending_data_types']);
        $this->assertNotContains($afterFirst->status, [
            ImportStatus::Completed->value,
            ImportStatus::CompletedWithWarnings->value,
            ImportStatus::Failed->value,
        ]);

        $coordinator->finalizeDataType($snapshotB->id, 'orders_returns', false);

        $fresh = $import->fresh();
        $this->assertSame([], $fresh->metadata['pending_data_types']);
        $this->assertSame(ImportStatus::Completed->value, $fresh->status);
        $this->assertSame(0, $fresh->errors_count);
        $this->assertNotNull($fresh->completed_at);
    }

    public function test_finalization_counts_errors_across_concurrent_failures(): void
    {
        $coordinator = new ImportCoordinator;
        $import = $this->import(['catalog', 'orders_returns']);

        $snapshotA = DataImport::find($import->id);
        $snapshotB = DataImport::find($import->id);

        $coordinator->finalizeDataType($snapshotA->id, 'catalog', true);
        $coordinator->finalizeDataType($snapshotB->id, 'orders_returns', true);

        $fresh = $import->fresh();
        $this->assertSame([], $fresh->metadata['pending_data_types']);
        $this->assertSame(2, $fresh->errors_count);
        $this->assertSame(ImportStatus::Failed->value, $fresh->status);
    }
}
