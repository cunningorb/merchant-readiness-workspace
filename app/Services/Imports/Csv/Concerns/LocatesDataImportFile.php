<?php

namespace App\Services\Imports\Csv\Concerns;

use App\Models\DataImport;
use App\Models\DataImportFile;
use RuntimeException;

/**
 * Shared by the three CSV importers: each one needs to find the single
 * DataImportFile row the controller attached for its data type before it
 * can read anything off disk.
 */
trait LocatesDataImportFile
{
    private function fileFor(DataImport $dataImport, string $dataType): DataImportFile
    {
        $file = DataImportFile::query()
            ->where('data_import_id', $dataImport->id)
            ->where('data_type', $dataType)
            ->first();

        if ($file === null) {
            throw new RuntimeException(
                "No '{$dataType}' file attached to import {$dataImport->id}."
            );
        }

        return $file;
    }
}
