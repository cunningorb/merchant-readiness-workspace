<?php

namespace App\Services\Imports\Csv\Concerns;

use RuntimeException;

/**
 * Minimal header-mapped CSV reader shared by the three CSV importers. Reads
 * the whole file into memory as an array of associative rows keyed by the
 * header column names — the framework-proving CSV path this milestone
 * builds only ever deals with small, single- or few-row files, so streaming
 * isn't warranted here.
 */
trait ReadsCsvRows
{
    /**
     * @return array<int, array<string, string|null>>
     */
    private function readRows(string $absolutePath): array
    {
        $handle = fopen($absolutePath, 'r');

        if ($handle === false) {
            throw new RuntimeException("Unable to open CSV file at '{$absolutePath}'.");
        }

        $header = fgetcsv($handle);

        if ($header === false || $header === null) {
            fclose($handle);

            return [];
        }

        $columnCount = count($header);
        $rows = [];

        while (($row = fgetcsv($handle)) !== false) {
            // fgetcsv() returns [null] for a genuinely blank line; skip it
            // rather than treating it as a data row of all-null fields.
            if ($row === [null]) {
                continue;
            }

            $row = array_pad(array_slice($row, 0, $columnCount), $columnCount, null);
            $rows[] = array_combine($header, $row);
        }

        fclose($handle);

        return $rows;
    }
}
