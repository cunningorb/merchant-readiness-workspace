<?php

namespace Tests\Unit\Services\Imports;

use App\Enums\ImportStatus;
use App\Models\Assessment;
use App\Models\AssessmentAnswer;
use App\Models\DataImport;
use App\Models\Merchant;
use App\Models\MerchantOrderMetric;
use App\Models\MerchantProduct;
use App\Services\Imports\AssessmentEvidenceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AssessmentEvidenceServiceTest extends TestCase
{
    use RefreshDatabase;

    private function answer(Assessment $assessment, string $questionKey, string $section, mixed $value): void
    {
        AssessmentAnswer::factory()->for($assessment)->create([
            'question_key' => $questionKey,
            'section' => $section,
            'value' => $value,
        ]);
    }

    private function completedImport(Assessment $assessment, array $overrides = []): DataImport
    {
        return DataImport::factory()->for($assessment)->create(array_merge([
            'status' => ImportStatus::Completed->value,
        ], $overrides));
    }

    private function service(): AssessmentEvidenceService
    {
        return new AssessmentEvidenceService;
    }

    // --- core non-overwrite guarantee (written first) ----------------------

    public function test_both_present_answer_wins_for_monthly_order_volume(): void
    {
        $assessment = Assessment::factory()->create();
        $this->answer($assessment, 'business.monthly_order_volume', 'business', '1,000-10,000');
        $import = $this->completedImport($assessment);
        MerchantOrderMetric::factory()->create([
            'merchant_id' => $assessment->merchant_id,
            'assessment_id' => $assessment->id,
            'source_import_id' => $import->id,
            'annualized_order_volume' => 66000,
        ]);

        $evidence = $this->service()->mergedEvidence($assessment->fresh(['answers']));
        $record = $evidence['business.monthly_order_volume'];

        $this->assertSame('merchant_answer', $record->source);
        $this->assertSame('1,000-10,000', $record->authoritativeValue);
        $this->assertSame('1,000-10,000', $record->answerValue);
        $this->assertSame(5500, $record->importedValue);
    }

    public function test_both_present_answer_wins_for_sku_count(): void
    {
        $assessment = Assessment::factory()->create();
        $this->answer($assessment, 'catalog.sku_count', 'catalog', '500-5,000');
        $import = $this->completedImport($assessment);
        MerchantProduct::factory()->count(3)->create([
            'merchant_id' => $assessment->merchant_id,
            'source_import_id' => $import->id,
        ]);

        $evidence = $this->service()->mergedEvidence($assessment->fresh(['answers']));
        $record = $evidence['catalog.sku_count'];

        $this->assertSame('merchant_answer', $record->source);
        $this->assertSame('500-5,000', $record->authoritativeValue);
        $this->assertSame(3, $record->importedValue);
    }

    // --- monthly_order_volume: all four states ------------------------------

    public function test_monthly_order_volume_answer_only(): void
    {
        $assessment = Assessment::factory()->create();
        $this->answer($assessment, 'business.monthly_order_volume', 'business', '1,000-10,000');

        $record = $this->service()->mergedEvidence($assessment->fresh(['answers']))['business.monthly_order_volume'];

        $this->assertSame('merchant_answer', $record->source);
        $this->assertSame('1,000-10,000', $record->authoritativeValue);
        $this->assertNull($record->importedValue);
    }

    public function test_monthly_order_volume_imported_only(): void
    {
        $assessment = Assessment::factory()->create();
        $import = $this->completedImport($assessment);
        MerchantOrderMetric::factory()->create([
            'merchant_id' => $assessment->merchant_id,
            'assessment_id' => $assessment->id,
            'source_import_id' => $import->id,
            'annualized_order_volume' => 66000,
        ]);

        $record = $this->service()->mergedEvidence($assessment->fresh(['answers']))['business.monthly_order_volume'];

        $this->assertSame('imported', $record->source);
        $this->assertSame(5500, $record->authoritativeValue);
        $this->assertNull($record->answerValue);
    }

    public function test_monthly_order_volume_neither_present(): void
    {
        $assessment = Assessment::factory()->create();

        $record = $this->service()->mergedEvidence($assessment->fresh(['answers']))['business.monthly_order_volume'];

        $this->assertSame('none', $record->source);
        $this->assertNull($record->authoritativeValue);
        $this->assertNull($record->answerValue);
        $this->assertNull($record->importedValue);
    }

    public function test_monthly_order_volume_conversion_divides_evenly(): void
    {
        $assessment = Assessment::factory()->create();
        $import = $this->completedImport($assessment);
        MerchantOrderMetric::factory()->create([
            'merchant_id' => $assessment->merchant_id,
            'assessment_id' => $assessment->id,
            'source_import_id' => $import->id,
            'annualized_order_volume' => 66000,
        ]);

        $record = $this->service()->mergedEvidence($assessment->fresh(['answers']))['business.monthly_order_volume'];

        $this->assertSame(5500, $record->importedValue);
    }

    public function test_monthly_order_volume_conversion_rounds_to_nearest_integer(): void
    {
        $assessment = Assessment::factory()->create();
        $import = $this->completedImport($assessment);
        MerchantOrderMetric::factory()->create([
            'merchant_id' => $assessment->merchant_id,
            'assessment_id' => $assessment->id,
            'source_import_id' => $import->id,
            'annualized_order_volume' => 12345, // 12345 / 12 = 1028.75 -> rounds to 1029
        ]);

        $record = $this->service()->mergedEvidence($assessment->fresh(['answers']))['business.monthly_order_volume'];

        $this->assertSame(1029, $record->importedValue);
    }

    public function test_monthly_order_volume_scoped_to_latest_import_only(): void
    {
        $assessment = Assessment::factory()->create();
        $olderImport = $this->completedImport($assessment, ['created_at' => now()->subDays(2)]);
        $latestImport = $this->completedImport($assessment, ['created_at' => now()]);

        MerchantOrderMetric::factory()->create([
            'merchant_id' => $assessment->merchant_id,
            'assessment_id' => $assessment->id,
            'source_import_id' => $olderImport->id,
            'annualized_order_volume' => 24000,
        ]);
        MerchantOrderMetric::factory()->create([
            'merchant_id' => $assessment->merchant_id,
            'assessment_id' => $assessment->id,
            'source_import_id' => $latestImport->id,
            'annualized_order_volume' => 66000,
        ]);

        $record = $this->service()->mergedEvidence($assessment->fresh(['answers']))['business.monthly_order_volume'];

        $this->assertSame(5500, $record->importedValue);
    }

    // --- catalog.sku_count: all four states ---------------------------------

    public function test_sku_count_answer_only(): void
    {
        $assessment = Assessment::factory()->create();
        $this->answer($assessment, 'catalog.sku_count', 'catalog', '500-5,000');

        $record = $this->service()->mergedEvidence($assessment->fresh(['answers']))['catalog.sku_count'];

        $this->assertSame('merchant_answer', $record->source);
        $this->assertSame('500-5,000', $record->authoritativeValue);
        $this->assertNull($record->importedValue);
    }

    public function test_sku_count_imported_only(): void
    {
        $assessment = Assessment::factory()->create();
        $import = $this->completedImport($assessment);
        MerchantProduct::factory()->count(4)->create([
            'merchant_id' => $assessment->merchant_id,
            'source_import_id' => $import->id,
        ]);

        $record = $this->service()->mergedEvidence($assessment->fresh(['answers']))['catalog.sku_count'];

        $this->assertSame('imported', $record->source);
        $this->assertSame(4, $record->authoritativeValue);
        $this->assertNull($record->answerValue);
    }

    public function test_sku_count_neither_present(): void
    {
        $assessment = Assessment::factory()->create();

        $record = $this->service()->mergedEvidence($assessment->fresh(['answers']))['catalog.sku_count'];

        $this->assertSame('none', $record->source);
        $this->assertNull($record->authoritativeValue);
    }

    public function test_sku_count_scoped_to_latest_import_only(): void
    {
        $assessment = Assessment::factory()->create();
        $olderImport = $this->completedImport($assessment, ['created_at' => now()->subDays(2)]);
        $latestImport = $this->completedImport($assessment, ['created_at' => now()]);

        MerchantProduct::factory()->count(5)->create([
            'merchant_id' => $assessment->merchant_id,
            'source_import_id' => $olderImport->id,
        ]);
        MerchantProduct::factory()->count(2)->create([
            'merchant_id' => $assessment->merchant_id,
            'source_import_id' => $latestImport->id,
        ]);

        $record = $this->service()->mergedEvidence($assessment->fresh(['answers']))['catalog.sku_count'];

        $this->assertSame(2, $record->importedValue);
    }

    // --- duplicate stubs resolve to the original import's evidence -----------

    public function test_duplicate_detected_import_resolves_evidence_from_the_original_import(): void
    {
        $merchant = Merchant::factory()->create();

        // Assessment A: the genuine import that actually holds the metric/product rows.
        $assessmentA = Assessment::factory()->create(['merchant_id' => $merchant->id]);
        $originalImport = $this->completedImport($assessmentA, [
            'metadata' => ['fingerprint' => 'abc123'],
        ]);
        MerchantOrderMetric::factory()->create([
            'merchant_id' => $merchant->id,
            'assessment_id' => $assessmentA->id,
            'source_import_id' => $originalImport->id,
            'annualized_order_volume' => 66000,
        ]);
        MerchantProduct::factory()->count(4)->create([
            'merchant_id' => $merchant->id,
            'source_import_id' => $originalImport->id,
        ]);

        // Assessment B (same merchant): a re-upload of the same file was
        // detected as a duplicate — completed, but no rows of its own.
        $assessmentB = Assessment::factory()->create(['merchant_id' => $merchant->id]);
        $this->completedImport($assessmentB, [
            'metadata' => [
                'fingerprint' => 'abc123',
                'duplicate_of_import_id' => $originalImport->id,
            ],
        ]);

        $evidence = $this->service()->mergedEvidence($assessmentB->fresh(['answers']));

        // Both evidence lookups follow the duplicate pointer to the original.
        $this->assertSame(5500, $evidence['business.monthly_order_volume']->importedValue);
        $this->assertSame(4, $evidence['catalog.sku_count']->importedValue);
    }

    // --- latestUsableImport --------------------------------------------------

    public function test_latest_usable_import_picks_most_recent_completed(): void
    {
        $assessment = Assessment::factory()->create();
        $oldest = $this->completedImport($assessment);
        $middle = $this->completedImport($assessment, ['status' => ImportStatus::CompletedWithWarnings->value]);
        $newest = $this->completedImport($assessment);

        $picked = $this->service()->latestUsableImport($assessment);

        $this->assertNotNull($picked);
        $this->assertSame($newest->id, $picked->id);
        $this->assertNotSame($oldest->id, $picked->id);
        $this->assertNotSame($middle->id, $picked->id);
    }

    public function test_latest_usable_import_ignores_failed_cancelled_and_in_progress_even_if_newer(): void
    {
        $assessment = Assessment::factory()->create();
        $usable = $this->completedImport($assessment);
        DataImport::factory()->for($assessment)->create(['status' => ImportStatus::Failed->value]);
        DataImport::factory()->for($assessment)->create(['status' => ImportStatus::Cancelled->value]);
        DataImport::factory()->for($assessment)->create(['status' => ImportStatus::Importing->value]);

        $picked = $this->service()->latestUsableImport($assessment);

        $this->assertNotNull($picked);
        $this->assertSame($usable->id, $picked->id);
    }

    public function test_latest_usable_import_returns_null_when_none_usable(): void
    {
        $assessment = Assessment::factory()->create();
        DataImport::factory()->for($assessment)->create(['status' => ImportStatus::Failed->value]);
        DataImport::factory()->for($assessment)->create(['status' => ImportStatus::Cancelled->value]);
        DataImport::factory()->for($assessment)->create(['status' => ImportStatus::Queued->value]);

        $picked = $this->service()->latestUsableImport($assessment);

        $this->assertNull($picked);
    }

    public function test_latest_usable_import_returns_null_when_no_imports_exist(): void
    {
        $assessment = Assessment::factory()->create();

        $picked = $this->service()->latestUsableImport($assessment);

        $this->assertNull($picked);
    }

    public function test_latest_usable_import_with_three_imports_at_different_statuses_and_times_picks_correct_one(): void
    {
        $assessment = Assessment::factory()->create();

        // Oldest: usable but older than the failed one below.
        $oldestUsable = $this->completedImport($assessment, ['created_at' => now()->subDays(5)]);

        // Newer than $oldestUsable, but failed - must never be picked, even though it is more recent.
        DataImport::factory()->for($assessment)->create([
            'status' => ImportStatus::Failed->value,
            'created_at' => now()->subDays(3),
        ]);

        // The real answer: newest AND usable.
        $newestUsable = $this->completedImport($assessment, [
            'status' => ImportStatus::CompletedWithWarnings->value,
            'created_at' => now()->subDay(),
        ]);

        $picked = $this->service()->latestUsableImport($assessment);

        $this->assertNotNull($picked);
        $this->assertSame($newestUsable->id, $picked->id);
        $this->assertNotSame($oldestUsable->id, $picked->id);
    }
}
