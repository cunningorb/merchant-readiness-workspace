<?php

namespace Tests\Unit\Services\Imports;

use App\Services\Imports\EvidenceRecord;
use Error;
use Tests\TestCase;

class EvidenceRecordTest extends TestCase
{
    public function test_answer_only_source_is_merchant_answer(): void
    {
        $record = new EvidenceRecord(
            key: 'business.monthly_order_volume',
            answerValue: '1,000-10,000',
            importedValue: null,
        );

        $this->assertSame('merchant_answer', $record->source);
        $this->assertSame('1,000-10,000', $record->authoritativeValue);
    }

    public function test_imported_only_source_is_imported(): void
    {
        $record = new EvidenceRecord(
            key: 'business.monthly_order_volume',
            answerValue: null,
            importedValue: 5500,
        );

        $this->assertSame('imported', $record->source);
        $this->assertSame(5500, $record->authoritativeValue);
    }

    public function test_both_present_answer_wins(): void
    {
        $record = new EvidenceRecord(
            key: 'business.monthly_order_volume',
            answerValue: '1,000-10,000',
            importedValue: 5500,
        );

        $this->assertSame('merchant_answer', $record->source);
        $this->assertSame('1,000-10,000', $record->authoritativeValue);
    }

    public function test_neither_present_source_is_none(): void
    {
        $record = new EvidenceRecord(
            key: 'business.monthly_order_volume',
            answerValue: null,
            importedValue: null,
        );

        $this->assertSame('none', $record->source);
        $this->assertNull($record->authoritativeValue);
    }

    public function test_empty_string_answer_is_treated_as_unanswered(): void
    {
        $record = new EvidenceRecord(
            key: 'catalog.sku_count',
            answerValue: '',
            importedValue: 42,
        );

        $this->assertSame('imported', $record->source);
        $this->assertSame(42, $record->authoritativeValue);
    }

    public function test_empty_array_answer_is_treated_as_unanswered(): void
    {
        $record = new EvidenceRecord(
            key: 'catalog.sku_count',
            answerValue: [],
            importedValue: 42,
        );

        $this->assertSame('imported', $record->source);
        $this->assertSame(42, $record->authoritativeValue);
    }

    public function test_properties_are_readonly(): void
    {
        $record = new EvidenceRecord(
            key: 'catalog.sku_count',
            answerValue: null,
            importedValue: null,
        );

        $this->expectException(Error::class);

        $record->source = 'imported';
    }
}
