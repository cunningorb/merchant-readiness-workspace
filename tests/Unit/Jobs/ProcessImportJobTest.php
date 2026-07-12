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

    // --- id-based finalization re-read ------------------------------------

    /**
     * finalizeDataType() re-reads the DataImport fresh by id on each call
     * rather than trusting a stale in-memory model handed to it by the caller.
     * This test drives two calls sequentially, each holding its own stale
     * snapshot taken while the pending list still held both data types, and
     * proves the removals accumulate correctly: the first call removes only its
     * own data type and does NOT finalize, and only the second call — the one
     * that empties the pending list — finalizes the overall status.
     *
     * NOTE: this is a sequential, single-process test. It proves the id-based
     * re-read behaviour, NOT protection against a true concurrent race. The
     * actual concurrent-safety guard (lockForUpdate() inside a transaction) is
     * covered separately by
     * test_finalization_source_requests_a_row_lock_inside_a_transaction(),
     * because SQLite cannot enact real row locks in this environment.
     */
    public function test_finalization_re_reads_the_pending_list_by_id_on_each_call(): void
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

    /**
     * Sequential companion to the re-read test above: each call increments the
     * error count on the freshly re-read row, so two failing data types
     * accumulate to two errors and the import ends Failed. Like its sibling this
     * proves id-based accumulation, not concurrent-race protection.
     */
    public function test_finalization_accumulates_errors_across_repeated_calls(): void
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

    /**
     * finalizeDataType()'s real concurrent-safety comes from taking a
     * `FOR UPDATE` row lock inside a DB transaction. This project's local/CI
     * database is SQLite, whose query grammar compiles lockForUpdate() to an
     * empty string, so the lock is never actually enacted and NO behavioural
     * test in this environment can observe a real row lock — the production
     * guarantee rests on Postgres enforcing the lock. Because behavioural proof
     * is impossible here, this test instead pins the guard's presence: it reads
     * the source of ImportCoordinator::finalizeDataType() and asserts the lock
     * is still requested inside a transaction. If a future refactor silently
     * drops lockForUpdate() (or the surrounding transaction) from the
     * finalization path, this test fails regardless of the database driver.
     */
    public function test_finalization_source_requests_a_row_lock_inside_a_transaction(): void
    {
        $method = new \ReflectionMethod(ImportCoordinator::class, 'finalizeDataType');
        $lines = file($method->getFileName());
        $source = implode('', array_slice(
            $lines,
            $method->getStartLine() - 1,
            $method->getEndLine() - $method->getStartLine() + 1,
        ));

        $this->assertStringContainsString(
            'DB::transaction',
            $source,
            'finalizeDataType() must run inside a DB transaction for the row lock to hold.',
        );
        $this->assertStringContainsString(
            'lockForUpdate()',
            $source,
            'finalizeDataType() must lock the DataImport row FOR UPDATE; dropping this reintroduces the finalization race on production (Postgres).',
        );
    }
}
