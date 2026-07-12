<?php

namespace App\Jobs;

use App\Enums\ImportStatus;
use App\Models\DataImport;
use App\Models\DataImportError;
use App\Services\Imports\ImportCoordinator;
use App\Services\Imports\ImportProviderRegistry;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Throwable;

/**
 * Shared behaviour for the per-data-type import jobs. Each subclass names its
 * data type, resolves its importer from the registry, and runs the importer's
 * single method; this base handles the status flow, error capture, and the
 * race-safe hand-off to ImportCoordinator::finalizeDataType(). Jobs hold the
 * DataImport id (not the model) per Laravel convention so they reload fresh
 * state when they run.
 *
 * Race-safety here relies on lockForUpdate() inside a DB transaction; this
 * project's local/CI database is SQLite, which does not enforce row-level
 * locking, so this guard's real concurrent-safety is unverified by the
 * automated test suite and depends on the production database (Postgres)
 * actually enforcing it.
 */
abstract class ProcessImportJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(public int $dataImportId) {}

    /** The data type this job imports (e.g. 'catalog'). */
    abstract protected function dataType(): string;

    /** Resolve this data type's importer for the given provider, or null. */
    abstract protected function resolveImporter(ImportProviderRegistry $registry, string $provider): ?object;

    /** Invoke the resolved importer's single import method. */
    abstract protected function runImport(object $importer, DataImport $dataImport): void;

    public function handle(ImportProviderRegistry $registry): void
    {
        $dataImport = DataImport::find($this->dataImportId);

        if ($dataImport === null || $dataImport->status === ImportStatus::Cancelled->value) {
            return;
        }

        $this->markProcessing($dataImport);

        $failed = false;
        $importer = $this->resolveImporter($registry, $dataImport->provider);

        if ($importer === null) {
            $this->recordError(
                $dataImport,
                "No {$this->dataType()} importer registered for provider '{$dataImport->provider}'."
            );
            $failed = true;
        } else {
            try {
                $this->runImport($importer, $dataImport);
            } catch (Throwable $exception) {
                $this->recordError($dataImport, $exception->getMessage());
                $failed = true;
            }
        }

        app(ImportCoordinator::class)->finalizeDataType($this->dataImportId, $this->dataType(), $failed);
    }

    /**
     * Advance a fresh import into the processing state. The first job to run
     * flips queued -> importing (stamping started_at); every job then ensures
     * the import is marked processing. Terminal states are never overwritten.
     */
    private function markProcessing(DataImport $dataImport): void
    {
        if (in_array($dataImport->status, [ImportStatus::Queued->value, ImportStatus::Validating->value], true)) {
            $dataImport->status = ImportStatus::Importing->value;
            $dataImport->started_at ??= now();
            $dataImport->save();
        }

        if ($dataImport->status === ImportStatus::Importing->value) {
            $dataImport->status = ImportStatus::Processing->value;
            $dataImport->save();
        }
    }

    private function recordError(DataImport $dataImport, string $message): void
    {
        DataImportError::create([
            'data_import_id' => $dataImport->id,
            'message' => $message,
        ]);
    }
}
