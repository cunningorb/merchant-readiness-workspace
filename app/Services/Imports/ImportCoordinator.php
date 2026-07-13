<?php

namespace App\Services\Imports;

use App\Enums\ImportStatus;
use App\Jobs\ProcessCatalogImportJob;
use App\Jobs\ProcessInventoryImportJob;
use App\Jobs\ProcessOrderReturnImportJob;
use App\Models\Assessment;
use App\Models\DataConnection;
use App\Models\DataImport;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use InvalidArgumentException;
use LogicException;

/**
 * Orchestrates the lifecycle of a provider-agnostic data import.
 *
 * The API is deliberately three-phase — create() opens an import, one or more
 * attachDataType() calls declare what will be imported (a CSV flow spreads
 * these across separate HTTP requests as files arrive; a demo flow calls them
 * back-to-back), and process() is the single "go" trigger that either
 * short-circuits an idempotent re-import or dispatches one queued job per data
 * type. The queued jobs call finalizeDataType() back on this coordinator as
 * each data type finishes; the last one to finish computes the overall status.
 */
class ImportCoordinator
{
    public const DATA_TYPE_CATALOG = 'catalog';

    public const DATA_TYPE_ORDERS_RETURNS = 'orders_returns';

    public const DATA_TYPE_INVENTORY_LOCATIONS = 'inventory_locations';

    /** @var array<string, class-string> */
    private const JOBS = [
        self::DATA_TYPE_CATALOG => ProcessCatalogImportJob::class,
        self::DATA_TYPE_ORDERS_RETURNS => ProcessOrderReturnImportJob::class,
        self::DATA_TYPE_INVENTORY_LOCATIONS => ProcessInventoryImportJob::class,
    ];

    /**
     * Phase 1: open a new import. No jobs are dispatched here.
     */
    public function create(Assessment $assessment, string $provider, string $method, ?DataConnection $connection = null): DataImport
    {
        return DataImport::create([
            'assessment_id' => $assessment->id,
            'data_connection_id' => $connection?->id,
            'provider' => $provider,
            'method' => $method,
            'data_types' => [],
            'status' => ImportStatus::Created->value,
            'metadata' => [],
        ]);
    }

    /**
     * Phase 2: declare a data type to be imported, optionally with a fingerprint
     * seed used for idempotency at process() time. Appending is idempotent (a
     * repeated data type is a no-op). May be called only while the import is
     * still in the created state; calling it after process() throws, because
     * the pending-work set is already frozen and jobs may be in flight.
     */
    public function attachDataType(DataImport $dataImport, string $dataType, ?string $fingerprintSeed = null): void
    {
        if (! array_key_exists($dataType, self::JOBS)) {
            throw new InvalidArgumentException("Unknown import data type '{$dataType}'.");
        }

        if ($dataImport->status !== ImportStatus::Created->value) {
            throw new LogicException(
                "Cannot attach data types once processing has started (status: {$dataImport->status})."
            );
        }

        $dataTypes = $dataImport->data_types ?? [];
        if (! in_array($dataType, $dataTypes, true)) {
            $dataTypes[] = $dataType;
        }

        $metadata = $dataImport->metadata ?? [];
        if ($fingerprintSeed !== null) {
            $metadata['fingerprint_seeds'][$dataType] = $fingerprintSeed;
        }

        $dataImport->data_types = $dataTypes;
        $dataImport->metadata = $metadata;
        $dataImport->save();
    }

    /**
     * Phase 3: the single "go" trigger. Either short-circuits an idempotent
     * duplicate re-import or transitions the import to queued and dispatches one
     * job per attached data type.
     */
    public function process(DataImport $dataImport): void
    {
        $jobs = DB::transaction(function () use ($dataImport) {
            $dataImport = DataImport::query()->lockForUpdate()->findOrFail($dataImport->id);

            if ($dataImport->status !== ImportStatus::Created->value) {
                throw new LogicException("Cannot process an import with status '{$dataImport->status}'.");
            }

            $dataTypes = $dataImport->data_types ?? [];

            if ($dataTypes === []) {
                throw new LogicException('Cannot process an import without at least one attached data type.');
            }

            $metadata = $dataImport->metadata ?? [];
            $seeds = $metadata['fingerprint_seeds'] ?? [];

            $everyDataTypeSeeded = true;
            foreach ($dataTypes as $dataType) {
                if (! array_key_exists($dataType, $seeds)) {
                    $everyDataTypeSeeded = false;
                    break;
                }
            }

            // Compute a fingerprint over whatever seeds are present, sorted by data
            // type, so it is stored for audit even for imports that opt out of
            // deduplication (e.g. demo imports, which never provide seeds).
            $fingerprint = $this->fingerprintFor($dataImport, $dataTypes, $seeds);
            $metadata['fingerprint'] = $fingerprint;

            // Idempotency short-circuit: only when every attached data type provided
            // a seed do we treat this as a content-addressable re-import and look
            // for a prior successful import of the exact same content, for the same
            // merchant, across any of that merchant's assessments.
            if ($everyDataTypeSeeded) {
                $existing = DataImport::query()
                    ->where('id', '!=', $dataImport->id)
                    ->where('metadata->fingerprint', $fingerprint)
                    ->whereIn('status', [
                        ImportStatus::Completed->value,
                        ImportStatus::CompletedWithWarnings->value,
                    ])
                    ->whereHas('assessment', function ($query) use ($dataImport) {
                        $query->where('merchant_id', $dataImport->assessment->merchant_id);
                    })
                    ->first();

                if ($existing !== null) {
                    $metadata['duplicate_of_import_id'] = $existing->id;
                    $dataImport->metadata = $metadata;
                    $dataImport->status = ImportStatus::Completed->value;
                    $dataImport->started_at = now();
                    $dataImport->completed_at = now();
                    $dataImport->save();

                    // Terminal: this stub's uploaded blobs are never parsed (the
                    // original import already holds the data), so drop them.
                    $this->deleteStoredFiles($dataImport);

                    return [];
                }
            }

            $metadata['pending_data_types'] = array_values($dataTypes);
            $dataImport->metadata = $metadata;

            $dataImport->status = ImportStatus::Validating->value;
            $dataImport->save();

            $dataImport->status = ImportStatus::Queued->value;
            $dataImport->save();

            return array_map(fn (string $dataType) => self::JOBS[$dataType], $dataTypes);
        });

        foreach ($jobs as $jobClass) {
            $jobClass::dispatch($dataImport->id);
        }
    }

    /**
     * Cancel an import that has not yet reached a terminal state. Terminal
     * imports (completed / completed_with_warnings / failed / cancelled) are
     * left untouched — cancel is a documented no-op there rather than an error,
     * so callers can cancel optimistically without racing the finalizer.
     */
    public function cancel(DataImport $dataImport): void
    {
        $cancelled = DB::transaction(function () use ($dataImport) {
            $dataImport = DataImport::query()->lockForUpdate()->find($dataImport->id);

            if ($dataImport === null) {
                return null;
            }

            $cancellable = [
                ImportStatus::Created->value,
                ImportStatus::Validating->value,
                ImportStatus::Queued->value,
                ImportStatus::Importing->value,
                ImportStatus::Processing->value,
            ];

            if (! in_array($dataImport->status, $cancellable, true)) {
                return null;
            }

            $dataImport->status = ImportStatus::Cancelled->value;
            $dataImport->save();

            return $dataImport;
        });

        // Terminal: stored blobs are never read again once cancelled.
        if ($cancelled !== null) {
            $this->deleteStoredFiles($cancelled);
        }
    }

    /**
     * Race-safe finalization for a single data type, called by each queued job
     * when its data type finishes. The row is locked FOR UPDATE inside a
     * transaction and the pending list is re-read from the locked row — never
     * from a stale in-memory snapshot — so two jobs finishing within
     * milliseconds of each other cannot lose one another's removal. The job
     * whose removal empties the pending list computes the overall status.
     */
    public function finalizeDataType(int $dataImportId, string $dataType, bool $failed): void
    {
        DB::transaction(function () use ($dataImportId, $dataType, $failed) {
            $import = DataImport::query()->lockForUpdate()->find($dataImportId);

            if ($import === null || $import->status === ImportStatus::Cancelled->value) {
                return;
            }

            $metadata = $import->metadata ?? [];
            $pending = $metadata['pending_data_types'] ?? [];
            $pending = array_values(array_filter($pending, fn ($type) => $type !== $dataType));
            $metadata['pending_data_types'] = $pending;
            $import->metadata = $metadata;

            if ($failed) {
                $import->errors_count = $import->errors_count + 1;
            }

            if ($pending === []) {
                $import->status = $this->overallStatus($import);
                $import->completed_at = now();
            }

            $import->save();

            // Terminal: the importers have finished parsing, so the stored blobs
            // are never read again. Delete them (keeping the DB rows for audit).
            if ($pending === []) {
                $this->deleteStoredFiles($import);
            }
        });
    }

    /**
     * Delete the on-disk blob for each attached file once an import is terminal.
     * The DataImportFile rows are kept for audit/history; only the file content
     * on the local disk is removed. Best-effort: a missing file is a no-op.
     */
    private function deleteStoredFiles(DataImport $import): void
    {
        foreach ($import->files()->get() as $file) {
            if ($file->stored_path !== null) {
                Storage::disk('local')->delete($file->stored_path);
            }
        }
    }

    private function overallStatus(DataImport $import): string
    {
        $totalDataTypes = count($import->data_types ?? []);
        $errors = $import->errors_count;

        if ($errors === 0) {
            return ImportStatus::Completed->value;
        }

        if ($errors >= $totalDataTypes) {
            return ImportStatus::Failed->value;
        }

        return ImportStatus::CompletedWithWarnings->value;
    }

    /**
     * @param  array<int, string>  $dataTypes
     * @param  array<string, string>  $seeds
     */
    private function fingerprintFor(DataImport $dataImport, array $dataTypes, array $seeds): string
    {
        $relevant = [];
        foreach ($dataTypes as $dataType) {
            if (array_key_exists($dataType, $seeds)) {
                $relevant[$dataType] = $seeds[$dataType];
            }
        }

        ksort($relevant);

        $parts = [];
        foreach ($relevant as $dataType => $seed) {
            $parts[] = "{$dataType}:{$seed}";
        }

        return hash('sha256', implode('|', [
            $dataImport->assessment->merchant_id,
            $dataImport->provider,
            $dataImport->method,
            implode('|', $parts),
        ]));
    }
}
