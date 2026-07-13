<?php

namespace App\Http\Controllers;

use App\Enums\ImportMethod;
use App\Enums\ImportStatus;
use App\Http\Requests\StartImportRequest;
use App\Http\Requests\StoreDataImportFileRequest;
use App\Models\Assessment;
use App\Models\DataImport;
use App\Models\DataImportFile;
use App\Services\Imports\Demo\DemoDataProvider;
use App\Services\Imports\ImportCoordinator;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Storage;
use LogicException;

/**
 * Thin HTTP surface over ImportCoordinator's three-phase API plus file
 * upload/storage for the 'csv' provider. All decision logic (idempotency,
 * status transitions, parsing/validation of file contents) lives in
 * ImportCoordinator and the provider-specific importers; this controller
 * only validates request shape, resolves the uploaded file, and delegates.
 */
class ImportController extends Controller
{
    public function store(StartImportRequest $request, Assessment $assessment, ImportCoordinator $coordinator): JsonResponse
    {
        $data = $request->validated();

        if ($data['provider'] === 'demo') {
            $dataImport = DemoDataProvider::startImport($assessment, $data['scenario']);

            return $this->respondWithImport($dataImport);
        }

        // 'method' is never accepted from the client: the only provider that
        // reaches this branch is 'csv' (the 'demo' provider returns above),
        // and csv imports are always performed via the 'csv' method. Trusting
        // a client-supplied method would let a caller vary the idempotency
        // fingerprint (which folds provider+method in) without changing the
        // underlying file content, defeating duplicate detection.
        $dataImport = $coordinator->create($assessment, provider: $data['provider'], method: ImportMethod::Csv->value);

        return $this->respondWithImport($dataImport, 201);
    }

    public function storeFile(
        StoreDataImportFileRequest $request,
        Assessment $assessment,
        DataImport $import,
        ImportCoordinator $coordinator,
    ): JsonResponse {
        $this->ensureImportBelongsToAssessment($assessment, $import);

        if ($import->status !== ImportStatus::Created->value) {
            return response()->json([
                'message' => 'Files cannot be attached once processing has started.',
            ], 422);
        }

        $dataType = $request->validated('data_type');
        $uploaded = $request->file('file');

        // Re-attaching a file for a data_type REPLACES the previous one rather
        // than appending. The importers locate "the file for this data type"
        // with ->first(), so a second row would leave the importer parsing the
        // stale first file while the fingerprint/UI reflect the second. Keeping
        // exactly one row per data_type makes both unambiguous.
        $existingFiles = DataImportFile::query()
            ->where('data_import_id', $import->id)
            ->where('data_type', $dataType)
            ->get();

        foreach ($existingFiles as $existingFile) {
            if ($existingFile->stored_path !== null) {
                Storage::disk('local')->delete($existingFile->stored_path);
            }

            $existingFile->delete();
        }

        $storedPath = $uploaded->storeAs(
            "imports/{$import->id}",
            "{$dataType}-{$uploaded->getClientOriginalName()}",
            'local',
        );

        $fingerprint = hash_file('sha256', Storage::disk('local')->path($storedPath));

        DataImportFile::create([
            'data_import_id' => $import->id,
            'data_type' => $dataType,
            'original_filename' => $uploaded->getClientOriginalName(),
            'stored_path' => $storedPath,
            'fingerprint' => $fingerprint,
        ]);

        $coordinator->attachDataType($import, $dataType, fingerprintSeed: $fingerprint);

        return $this->respondWithImport($import->fresh());
    }

    public function process(Assessment $assessment, DataImport $import, ImportCoordinator $coordinator): JsonResponse
    {
        $this->ensureImportBelongsToAssessment($assessment, $import);

        if ($import->status !== ImportStatus::Created->value) {
            return response()->json([
                'message' => 'This import has already been processed.',
            ], 422);
        }

        try {
            $coordinator->process($import);
        } catch (LogicException $exception) {
            return response()->json([
                'message' => $exception->getMessage(),
            ], 422);
        }

        return $this->respondWithImport($import->fresh());
    }

    public function cancel(Assessment $assessment, DataImport $import, ImportCoordinator $coordinator): JsonResponse
    {
        $this->ensureImportBelongsToAssessment($assessment, $import);

        $coordinator->cancel($import);

        return $this->respondWithImport($import->fresh());
    }

    public function show(Assessment $assessment, DataImport $import): JsonResponse
    {
        $this->ensureImportBelongsToAssessment($assessment, $import);

        return $this->respondWithImport($import);
    }

    private function ensureImportBelongsToAssessment(Assessment $assessment, DataImport $import): void
    {
        abort_unless($import->assessment_id === $assessment->id, 404);
    }

    private function respondWithImport(DataImport $dataImport, int $status = 200): JsonResponse
    {
        $dataImport->loadMissing(['files', 'errors']);

        return response()->json([
            'data_import' => [
                'id' => $dataImport->id,
                'assessment_id' => $dataImport->assessment_id,
                'provider' => $dataImport->provider,
                'method' => $dataImport->method,
                'status' => $dataImport->status,
                'data_types' => $dataImport->data_types,
                'warnings_count' => $dataImport->warnings_count,
                'errors_count' => $dataImport->errors_count,
                'started_at' => $dataImport->started_at,
                'completed_at' => $dataImport->completed_at,
                'files' => $dataImport->files->map(fn (DataImportFile $file) => [
                    'id' => $file->id,
                    'data_type' => $file->data_type,
                    'original_filename' => $file->original_filename,
                    'row_count' => $file->row_count,
                    'accepted_count' => $file->accepted_count,
                    'rejected_count' => $file->rejected_count,
                ])->values(),
                'errors' => $dataImport->errors->map(fn ($error) => [
                    'id' => $error->id,
                    'row_number' => $error->row_number,
                    'message' => $error->message,
                ])->values(),
            ],
        ], $status);
    }
}
