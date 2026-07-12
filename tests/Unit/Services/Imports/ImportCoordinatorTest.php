<?php

namespace Tests\Unit\Services\Imports;

use App\Enums\ImportMethod;
use App\Enums\ImportStatus;
use App\Jobs\ProcessCatalogImportJob;
use App\Jobs\ProcessInventoryImportJob;
use App\Jobs\ProcessOrderReturnImportJob;
use App\Models\Assessment;
use App\Models\DataImport;
use App\Models\Merchant;
use App\Services\Imports\ImportCoordinator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class ImportCoordinatorTest extends TestCase
{
    use RefreshDatabase;

    private function coordinator(): ImportCoordinator
    {
        return new ImportCoordinator;
    }

    private function assessment(?Merchant $merchant = null): Assessment
    {
        return Assessment::factory()->create([
            'merchant_id' => ($merchant ?? Merchant::factory()->create())->id,
        ]);
    }

    // --- create() ----------------------------------------------------------

    public function test_create_returns_a_created_import_with_empty_data_and_no_jobs(): void
    {
        Queue::fake();
        $assessment = $this->assessment();

        $import = $this->coordinator()->create($assessment, 'demo', ImportMethod::Demo->value);

        $this->assertSame(ImportStatus::Created->value, $import->status);
        $this->assertSame([], $import->data_types);
        $this->assertSame([], $import->metadata);
        $this->assertSame('demo', $import->provider);
        $this->assertSame($assessment->id, $import->assessment_id);
        $this->assertNull($import->data_connection_id);
        Queue::assertNothingPushed();
    }

    // --- attachDataType() --------------------------------------------------

    public function test_attach_data_type_appends_and_dedupes(): void
    {
        $import = $this->coordinator()->create($this->assessment(), 'demo', ImportMethod::Demo->value);

        $this->coordinator()->attachDataType($import, 'catalog');
        $this->coordinator()->attachDataType($import, 'orders_returns');
        $this->coordinator()->attachDataType($import, 'catalog');

        $this->assertSame(['catalog', 'orders_returns'], $import->fresh()->data_types);
    }

    public function test_attach_data_type_stores_fingerprint_seeds(): void
    {
        $import = $this->coordinator()->create($this->assessment(), 'csv', ImportMethod::Csv->value);

        $this->coordinator()->attachDataType($import, 'catalog', 'seed-catalog');
        $this->coordinator()->attachDataType($import, 'orders_returns', 'seed-orders');

        $this->assertSame([
            'catalog' => 'seed-catalog',
            'orders_returns' => 'seed-orders',
        ], $import->fresh()->metadata['fingerprint_seeds']);
    }

    public function test_attach_data_type_rejects_unknown_data_type(): void
    {
        $import = $this->coordinator()->create($this->assessment(), 'demo', ImportMethod::Demo->value);

        $this->expectException(\InvalidArgumentException::class);
        $this->coordinator()->attachDataType($import, 'not_a_real_type');
    }

    public function test_attach_data_type_after_process_throws(): void
    {
        Queue::fake();
        $import = $this->coordinator()->create($this->assessment(), 'demo', ImportMethod::Demo->value);
        $this->coordinator()->attachDataType($import, 'catalog');
        $this->coordinator()->process($import);

        $this->expectException(\LogicException::class);
        $this->coordinator()->attachDataType($import->fresh(), 'orders_returns');
    }

    // --- process() ---------------------------------------------------------

    public function test_process_transitions_status_and_dispatches_one_job_per_data_type(): void
    {
        Queue::fake();
        $import = $this->coordinator()->create($this->assessment(), 'demo', ImportMethod::Demo->value);
        $this->coordinator()->attachDataType($import, 'catalog');
        $this->coordinator()->attachDataType($import, 'orders_returns');
        $this->coordinator()->attachDataType($import, 'inventory_locations');

        $this->coordinator()->process($import);

        $fresh = $import->fresh();
        $this->assertSame(ImportStatus::Queued->value, $fresh->status);
        $this->assertSame(
            ['catalog', 'orders_returns', 'inventory_locations'],
            $fresh->metadata['pending_data_types'],
        );

        Queue::assertPushed(ProcessCatalogImportJob::class, fn ($job) => $job->dataImportId === $import->id);
        Queue::assertPushed(ProcessOrderReturnImportJob::class, fn ($job) => $job->dataImportId === $import->id);
        Queue::assertPushed(ProcessInventoryImportJob::class, fn ($job) => $job->dataImportId === $import->id);
        Queue::assertPushed(ProcessCatalogImportJob::class, 1);
    }

    public function test_process_only_dispatches_jobs_for_attached_data_types(): void
    {
        Queue::fake();
        $import = $this->coordinator()->create($this->assessment(), 'demo', ImportMethod::Demo->value);
        $this->coordinator()->attachDataType($import, 'catalog');

        $this->coordinator()->process($import);

        Queue::assertPushed(ProcessCatalogImportJob::class, 1);
        Queue::assertNotPushed(ProcessOrderReturnImportJob::class);
        Queue::assertNotPushed(ProcessInventoryImportJob::class);
    }

    // --- idempotency -------------------------------------------------------

    private function completedImportWithSeeds(Merchant $merchant, array $seeds, string $status = ImportStatus::Completed->value): DataImport
    {
        Queue::fake();
        $import = $this->coordinator()->create($this->assessment($merchant), 'csv', ImportMethod::Csv->value);
        foreach ($seeds as $dataType => $seed) {
            $this->coordinator()->attachDataType($import, $dataType, $seed);
        }
        $this->coordinator()->process($import);

        // Simulate the jobs having finished successfully.
        $import->fresh()->update([
            'status' => $status,
            'completed_at' => now(),
        ]);

        return $import->fresh();
    }

    public function test_process_short_circuits_on_a_matching_completed_import_for_the_same_merchant(): void
    {
        $merchant = Merchant::factory()->create();
        $seeds = ['catalog' => 'sha-a', 'orders_returns' => 'sha-b'];
        $existing = $this->completedImportWithSeeds($merchant, $seeds);

        Queue::fake();
        $import = $this->coordinator()->create($this->assessment($merchant), 'csv', ImportMethod::Csv->value);
        foreach ($seeds as $dataType => $seed) {
            $this->coordinator()->attachDataType($import, $dataType, $seed);
        }

        $this->coordinator()->process($import);

        $fresh = $import->fresh();
        $this->assertSame(ImportStatus::Completed->value, $fresh->status);
        $this->assertSame($existing->id, $fresh->metadata['duplicate_of_import_id']);
        $this->assertArrayHasKey('fingerprint', $fresh->metadata);
        $this->assertNotNull($fresh->started_at);
        $this->assertNotNull($fresh->completed_at);
        Queue::assertNothingPushed();
    }

    public function test_process_does_not_short_circuit_for_a_different_merchant(): void
    {
        $seeds = ['catalog' => 'sha-a', 'orders_returns' => 'sha-b'];
        $this->completedImportWithSeeds(Merchant::factory()->create(), $seeds);

        Queue::fake();
        $otherMerchant = Merchant::factory()->create();
        $import = $this->coordinator()->create($this->assessment($otherMerchant), 'csv', ImportMethod::Csv->value);
        foreach ($seeds as $dataType => $seed) {
            $this->coordinator()->attachDataType($import, $dataType, $seed);
        }

        $this->coordinator()->process($import);

        $fresh = $import->fresh();
        $this->assertSame(ImportStatus::Queued->value, $fresh->status);
        $this->assertArrayNotHasKey('duplicate_of_import_id', $fresh->metadata);
        Queue::assertPushed(ProcessCatalogImportJob::class);
    }

    public function test_process_does_not_short_circuit_when_only_a_failed_import_matches(): void
    {
        $merchant = Merchant::factory()->create();
        $seeds = ['catalog' => 'sha-a', 'orders_returns' => 'sha-b'];
        $this->completedImportWithSeeds($merchant, $seeds, ImportStatus::Failed->value);

        Queue::fake();
        $import = $this->coordinator()->create($this->assessment($merchant), 'csv', ImportMethod::Csv->value);
        foreach ($seeds as $dataType => $seed) {
            $this->coordinator()->attachDataType($import, $dataType, $seed);
        }

        $this->coordinator()->process($import);

        $fresh = $import->fresh();
        $this->assertSame(ImportStatus::Queued->value, $fresh->status);
        $this->assertArrayNotHasKey('duplicate_of_import_id', $fresh->metadata);
        Queue::assertPushed(ProcessCatalogImportJob::class);
    }

    public function test_completed_with_warnings_import_also_short_circuits(): void
    {
        $merchant = Merchant::factory()->create();
        $seeds = ['catalog' => 'sha-a'];
        $existing = $this->completedImportWithSeeds($merchant, $seeds, ImportStatus::CompletedWithWarnings->value);

        Queue::fake();
        $import = $this->coordinator()->create($this->assessment($merchant), 'csv', ImportMethod::Csv->value);
        $this->coordinator()->attachDataType($import, 'catalog', 'sha-a');

        $this->coordinator()->process($import);

        $this->assertSame(ImportStatus::Completed->value, $import->fresh()->status);
        $this->assertSame($existing->id, $import->fresh()->metadata['duplicate_of_import_id']);
    }

    public function test_process_does_not_short_circuit_for_a_different_set_of_data_types(): void
    {
        $merchant = Merchant::factory()->create();
        // Existing import covered catalog + orders_returns.
        $this->completedImportWithSeeds($merchant, ['catalog' => 'sha-a', 'orders_returns' => 'sha-b']);

        Queue::fake();
        // New import covers only catalog, with the same seed — different set, must not match.
        $import = $this->coordinator()->create($this->assessment($merchant), 'csv', ImportMethod::Csv->value);
        $this->coordinator()->attachDataType($import, 'catalog', 'sha-a');

        $this->coordinator()->process($import);

        $this->assertSame(ImportStatus::Queued->value, $import->fresh()->status);
        $this->assertArrayNotHasKey('duplicate_of_import_id', $import->fresh()->metadata);
    }

    public function test_demo_import_without_seeds_stores_fingerprint_but_does_not_short_circuit(): void
    {
        $merchant = Merchant::factory()->create();
        // A prior completed demo import exists for this merchant with no seeds.
        Queue::fake();
        $first = $this->coordinator()->create($this->assessment($merchant), 'demo', ImportMethod::Demo->value);
        $this->coordinator()->attachDataType($first, 'catalog');
        $this->coordinator()->process($first);
        $first->fresh()->update(['status' => ImportStatus::Completed->value]);

        // A second identical demo import (no seeds) must still be processed, not deduped.
        $import = $this->coordinator()->create($this->assessment($merchant), 'demo', ImportMethod::Demo->value);
        $this->coordinator()->attachDataType($import, 'catalog');

        $this->coordinator()->process($import);

        $fresh = $import->fresh();
        $this->assertSame(ImportStatus::Queued->value, $fresh->status);
        $this->assertArrayHasKey('fingerprint', $fresh->metadata);
        $this->assertArrayNotHasKey('duplicate_of_import_id', $fresh->metadata);
        Queue::assertPushed(ProcessCatalogImportJob::class);
    }

    // --- cancel() ----------------------------------------------------------

    public function test_cancel_cancels_a_queued_import(): void
    {
        Queue::fake();
        $import = $this->coordinator()->create($this->assessment(), 'demo', ImportMethod::Demo->value);
        $this->coordinator()->attachDataType($import, 'catalog');
        $this->coordinator()->process($import);

        $this->coordinator()->cancel($import->fresh());

        $this->assertSame(ImportStatus::Cancelled->value, $import->fresh()->status);
    }

    public function test_cancel_is_a_no_op_on_an_already_completed_import(): void
    {
        $import = DataImport::factory()->create(['status' => ImportStatus::Completed->value]);

        $this->coordinator()->cancel($import);

        $this->assertSame(ImportStatus::Completed->value, $import->fresh()->status);
    }
}
