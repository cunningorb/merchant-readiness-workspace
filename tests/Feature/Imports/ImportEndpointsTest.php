<?php

namespace Tests\Feature\Imports;

use App\Enums\ImportStatus;
use App\Models\Assessment;
use App\Models\DataImportError;
use App\Models\Merchant;
use App\Models\MerchantInventoryMetric;
use App\Models\MerchantLocationMetric;
use App\Models\MerchantOrderMetric;
use App\Models\MerchantProduct;
use App\Models\MerchantReturnMetric;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ImportEndpointsTest extends TestCase
{
    use RefreshDatabase;

    private function assessment(?Merchant $merchant = null): Assessment
    {
        return Assessment::factory()->create([
            'merchant_id' => ($merchant ?? Merchant::factory()->create())->id,
        ]);
    }

    private function validProductsCsv(): string
    {
        return <<<'CSV'
        title,product_type,vendor,tags,description_length,status,variant_count,has_size_option,has_color_option,media_count,price,compare_at_price,sku,inventory_tracked
        Classic Tee,Apparel,Acme,"sale;new",120,active,4,true,true,5,19.99,24.99,TEE-001,true
        CSV;
    }

    private function validOrdersCsv(): string
    {
        return <<<'CSV'
        period_start,period_end,order_count,annualized_order_volume,average_order_value,refund_amount_total,refund_units_total,estimated_refund_rate,exchange_share,refund_only_share,average_time_to_refund_days
        2026-01-01,2026-01-31,500,6000,85.50,12500.75,150,0.18,0.20,0.75,5.25
        CSV;
    }

    private function malformedOrdersCsv(): string
    {
        return <<<'CSV'
        period_start,period_end,order_count,annualized_order_volume,average_order_value,refund_amount_total,refund_units_total,estimated_refund_rate,exchange_share,refund_only_share,average_time_to_refund_days
        ,2026-01-31,500,6000,85.50,12500.75,150,0.18,0.20,0.75,5.25
        CSV;
    }

    private function validInventoryCsv(): string
    {
        return <<<'CSV'
        percent_multi_location_stock,percent_low_or_zero_stock,sku_completeness,exchange_availability_risk,active_location_count,operational_complexity_score
        45.5,12.0,92.5,medium,3,medium
        CSV;
    }

    // --- Full happy path -----------------------------------------------------

    public function test_full_happy_path_create_attach_process_and_poll_to_completion(): void
    {
        Storage::fake('local');
        $assessment = $this->assessment();

        $create = $this->postJson("/api/assessments/{$assessment->id}/imports", [
            'provider' => 'csv',
            'method' => 'csv',
        ]);

        $create->assertCreated();
        $create->assertJsonPath('data_import.status', ImportStatus::Created->value);
        $importId = $create->json('data_import.id');

        $this->postJson("/api/assessments/{$assessment->id}/imports/{$importId}/files", [
            'data_type' => 'catalog',
            'file' => UploadedFile::fake()->createWithContent('products.csv', $this->validProductsCsv()),
        ])->assertOk();

        $this->postJson("/api/assessments/{$assessment->id}/imports/{$importId}/files", [
            'data_type' => 'orders_returns',
            'file' => UploadedFile::fake()->createWithContent('orders_and_returns.csv', $this->validOrdersCsv()),
        ])->assertOk();

        $this->postJson("/api/assessments/{$assessment->id}/imports/{$importId}/files", [
            'data_type' => 'inventory_locations',
            'file' => UploadedFile::fake()->createWithContent('inventory_and_locations.csv', $this->validInventoryCsv()),
        ])->assertOk();

        $process = $this->postJson("/api/assessments/{$assessment->id}/imports/{$importId}/process");
        $process->assertOk();

        // Queue runs synchronously in tests, so the import is already
        // terminal by the time process() returns; polling still succeeds.
        $show = $this->getJson("/api/assessments/{$assessment->id}/imports/{$importId}");
        $show->assertOk();
        $show->assertJsonPath('data_import.status', ImportStatus::Completed->value);
        $show->assertJsonPath('data_import.errors_count', 0);
        $this->assertCount(3, $show->json('data_import.files'));

        $this->assertSame(1, MerchantProduct::query()->where('source_import_id', $importId)->count());
        $this->assertSame(1, MerchantOrderMetric::query()->where('source_import_id', $importId)->count());
        $this->assertSame(1, MerchantReturnMetric::query()->where('source_import_id', $importId)->count());
        $this->assertSame(1, MerchantInventoryMetric::query()->where('source_import_id', $importId)->count());
        $this->assertSame(1, MerchantLocationMetric::query()->where('source_import_id', $importId)->count());
    }

    // --- Validation ------------------------------------------------------------

    public function test_rejects_a_non_csv_file_upload(): void
    {
        Storage::fake('local');
        $assessment = $this->assessment();
        $importId = $this->postJson("/api/assessments/{$assessment->id}/imports", [
            'provider' => 'csv',
            'method' => 'csv',
        ])->json('data_import.id');

        $response = $this->postJson("/api/assessments/{$assessment->id}/imports/{$importId}/files", [
            'data_type' => 'catalog',
            'file' => UploadedFile::fake()->create('products.pdf', 10, 'application/pdf'),
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['file']);
    }

    public function test_rejects_a_file_attach_after_process_has_already_run(): void
    {
        Storage::fake('local');
        $assessment = $this->assessment();
        $importId = $this->postJson("/api/assessments/{$assessment->id}/imports", [
            'provider' => 'csv',
            'method' => 'csv',
        ])->json('data_import.id');

        $this->postJson("/api/assessments/{$assessment->id}/imports/{$importId}/files", [
            'data_type' => 'catalog',
            'file' => UploadedFile::fake()->createWithContent('products.csv', $this->validProductsCsv()),
        ])->assertOk();

        $this->postJson("/api/assessments/{$assessment->id}/imports/{$importId}/process")->assertOk();

        $response = $this->postJson("/api/assessments/{$assessment->id}/imports/{$importId}/files", [
            'data_type' => 'orders_returns',
            'file' => UploadedFile::fake()->createWithContent('orders_and_returns.csv', $this->validOrdersCsv()),
        ]);

        $response->assertStatus(422);
    }

    // --- Cancel ------------------------------------------------------------

    public function test_cancel_transitions_a_created_import_to_cancelled(): void
    {
        $assessment = $this->assessment();
        $importId = $this->postJson("/api/assessments/{$assessment->id}/imports", [
            'provider' => 'csv',
            'method' => 'csv',
        ])->json('data_import.id');

        $response = $this->postJson("/api/assessments/{$assessment->id}/imports/{$importId}/cancel");

        $response->assertOk();
        $response->assertJsonPath('data_import.status', ImportStatus::Cancelled->value);
    }

    // --- Partial failure across data types -----------------------------------

    public function test_one_malformed_data_type_yields_completed_with_warnings_not_failed(): void
    {
        Storage::fake('local');
        $assessment = $this->assessment();
        $importId = $this->postJson("/api/assessments/{$assessment->id}/imports", [
            'provider' => 'csv',
            'method' => 'csv',
        ])->json('data_import.id');

        $this->postJson("/api/assessments/{$assessment->id}/imports/{$importId}/files", [
            'data_type' => 'catalog',
            'file' => UploadedFile::fake()->createWithContent('products.csv', $this->validProductsCsv()),
        ])->assertOk();

        $this->postJson("/api/assessments/{$assessment->id}/imports/{$importId}/files", [
            'data_type' => 'orders_returns',
            'file' => UploadedFile::fake()->createWithContent('orders_and_returns.csv', $this->malformedOrdersCsv()),
        ])->assertOk();

        $this->postJson("/api/assessments/{$assessment->id}/imports/{$importId}/process")->assertOk();

        $show = $this->getJson("/api/assessments/{$assessment->id}/imports/{$importId}");
        $show->assertJsonPath('data_import.status', ImportStatus::CompletedWithWarnings->value);
        $this->assertGreaterThanOrEqual(1, $show->json('data_import.errors_count'));

        // The catalog data type succeeded despite the other one failing.
        $this->assertSame(1, MerchantProduct::query()->where('source_import_id', $importId)->count());
        $this->assertSame(0, MerchantOrderMetric::query()->where('source_import_id', $importId)->count());
        $this->assertGreaterThanOrEqual(1, DataImportError::query()->where('data_import_id', $importId)->count());
    }

    // --- Duplicate-file idempotency ------------------------------------------

    public function test_uploading_the_same_products_csv_twice_does_not_create_duplicate_products(): void
    {
        Storage::fake('local');
        $merchant = Merchant::factory()->create();
        $csv = $this->validProductsCsv();

        $firstAssessment = $this->assessment($merchant);
        $firstImportId = $this->postJson("/api/assessments/{$firstAssessment->id}/imports", [
            'provider' => 'csv',
            'method' => 'csv',
        ])->json('data_import.id');

        $this->postJson("/api/assessments/{$firstAssessment->id}/imports/{$firstImportId}/files", [
            'data_type' => 'catalog',
            'file' => UploadedFile::fake()->createWithContent('products.csv', $csv),
        ])->assertOk();

        $this->postJson("/api/assessments/{$firstAssessment->id}/imports/{$firstImportId}/process")->assertOk();

        $this->assertSame(1, MerchantProduct::query()->count());

        $secondAssessment = $this->assessment($merchant);
        $secondImportId = $this->postJson("/api/assessments/{$secondAssessment->id}/imports", [
            'provider' => 'csv',
            'method' => 'csv',
        ])->json('data_import.id');

        $this->postJson("/api/assessments/{$secondAssessment->id}/imports/{$secondImportId}/files", [
            'data_type' => 'catalog',
            'file' => UploadedFile::fake()->createWithContent('products.csv', $csv),
        ])->assertOk();

        $secondProcess = $this->postJson("/api/assessments/{$secondAssessment->id}/imports/{$secondImportId}/process");
        $secondProcess->assertOk();
        $secondProcess->assertJsonPath('data_import.status', ImportStatus::Completed->value);

        // Idempotency short-circuit: no second pass of the importer ran, so
        // no duplicate MerchantProduct rows exist for this merchant.
        $this->assertSame(1, MerchantProduct::query()->count());
    }

    // --- No customer PII -----------------------------------------------------

    public function test_no_merchant_data_table_contains_a_customer_pii_column(): void
    {
        $tables = [
            'merchant_products',
            'merchant_product_variants',
            'merchant_order_metrics',
            'merchant_return_metrics',
            'merchant_inventory_metrics',
            'merchant_location_metrics',
        ];

        $forbiddenSubstrings = ['email', 'phone', 'address', 'ssn', 'credit_card', 'date_of_birth'];
        $forbiddenExactNames = ['name', 'customer_name', 'contact_name', 'first_name', 'last_name', 'full_name', 'zip', 'zip_code', 'postal_code'];

        foreach ($tables as $table) {
            $columns = Schema::getColumnListing($table);
            $this->assertNotEmpty($columns, "Expected table '{$table}' to exist with columns.");

            foreach ($columns as $column) {
                $lower = strtolower($column);

                foreach ($forbiddenSubstrings as $needle) {
                    $this->assertStringNotContainsString(
                        $needle,
                        $lower,
                        "Column {$table}.{$column} looks like it stores customer PII."
                    );
                }

                $this->assertNotContains(
                    $lower,
                    $forbiddenExactNames,
                    "Column {$table}.{$column} looks like it stores customer PII."
                );
            }
        }
    }
}
