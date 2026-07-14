<?php

namespace App\Services\Imports\Csv;

use App\Contracts\CatalogImporter;
use App\Models\DataImport;
use App\Models\DataImportError;
use App\Models\DataImportFile;
use App\Models\MerchantProduct;
use App\Services\Imports\Csv\Concerns\LocatesDataImportFile;
use App\Services\Imports\Csv\Concerns\ParsesCsvValues;
use App\Services\Imports\Csv\Concerns\ReadsCsvRows;
use App\Services\Imports\ImportCoordinator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * Parses products.csv — one row per product, exact headers:
 * title,product_type,vendor,tags,description_length,status,variant_count,
 * has_size_option,has_color_option,media_count,price,compare_at_price,sku,
 * inventory_tracked.
 *
 * Unlike the two summary-row importers, a malformed products.csv is a
 * partial failure, not a total one: a row missing 'title' or with an
 * unparseable numeric field is rejected individually (recorded as a
 * DataImportError carrying its row number) and processing continues with
 * the remaining rows, so one bad row never costs the whole file.
 */
class CsvCatalogImporter implements CatalogImporter
{
    use LocatesDataImportFile, ParsesCsvValues, ReadsCsvRows;

    public function importCatalog(DataImport $dataImport): void
    {
        $file = $this->fileFor($dataImport, ImportCoordinator::DATA_TYPE_CATALOG);
        $rows = $this->readRows(Storage::disk('local')->path($file->stored_path));
        $merchantId = $dataImport->assessment->merchant_id;

        $accepted = 0;
        $rejected = 0;

        DB::transaction(function () use ($dataImport, $file, $rows, $merchantId, &$accepted, &$rejected): void {
            MerchantProduct::query()
                ->where('source_import_id', $dataImport->id)
                ->delete();

            foreach ($rows as $index => $row) {
                $rowNumber = $index + 1;
                $title = $this->nullableString($row['title'] ?? null);

                if ($title === null) {
                    $this->rejectRow($dataImport, $file, $rowNumber, "Row {$rowNumber}: 'title' is required.");
                    $rejected++;

                    continue;
                }

                [$descriptionOk, $descriptionLength] = $this->parseOptionalInt($row['description_length'] ?? null);
                [$variantOk, $variantCount] = $this->parseOptionalInt($row['variant_count'] ?? null);
                [$mediaOk, $mediaCount] = $this->parseOptionalInt($row['media_count'] ?? null);
                [$priceOk, $price] = $this->parseOptionalFloat($row['price'] ?? null);
                [$compareOk, $compareAtPrice] = $this->parseOptionalFloat($row['compare_at_price'] ?? null);

                if (! $descriptionOk || ! $variantOk || ! $mediaOk || ! $priceOk || ! $compareOk) {
                    $this->rejectRow(
                        $dataImport,
                        $file,
                        $rowNumber,
                        "Row {$rowNumber}: one or more numeric fields could not be parsed."
                    );
                    $rejected++;

                    continue;
                }

                $sku = $this->nullableString($row['sku'] ?? null);

                MerchantProduct::create([
                    'merchant_id' => $merchantId,
                    'source_provider' => 'csv',
                    'source_import_id' => $dataImport->id,
                    'provider_product_id' => $sku ?? "csv-{$dataImport->id}-row-{$rowNumber}",
                    'title' => $title,
                    'product_type' => $this->nullableString($row['product_type'] ?? null),
                    'vendor' => $this->nullableString($row['vendor'] ?? null),
                    'tags' => $this->parseTags($row['tags'] ?? null),
                    'description_length' => $descriptionLength,
                    'status' => $this->nullableString($row['status'] ?? null),
                    'variant_count' => $variantCount,
                    'has_size_option' => $this->parseBoolean($row['has_size_option'] ?? null),
                    'has_color_option' => $this->parseBoolean($row['has_color_option'] ?? null),
                    'media_count' => $mediaCount,
                    'price' => $price,
                    'compare_at_price' => $compareAtPrice,
                    'sku' => $sku,
                    'inventory_tracked' => $this->parseBoolean($row['inventory_tracked'] ?? null),
                ]);

                $accepted++;
            }
        });

        $file->update([
            'row_count' => count($rows),
            'accepted_count' => $accepted,
            'rejected_count' => $rejected,
        ]);
    }

    private function rejectRow(DataImport $dataImport, DataImportFile $file, int $rowNumber, string $message): void
    {
        DataImportError::create([
            'data_import_id' => $dataImport->id,
            'data_import_file_id' => $file->id,
            'row_number' => $rowNumber,
            'message' => $message,
        ]);
    }

    private function parseTags(?string $raw): ?array
    {
        $value = $this->nullableString($raw);

        if ($value === null) {
            return null;
        }

        $tags = array_values(array_filter(
            array_map('trim', explode(';', $value)),
            fn (string $tag) => $tag !== '',
        ));

        return $tags === [] ? null : $tags;
    }
}
