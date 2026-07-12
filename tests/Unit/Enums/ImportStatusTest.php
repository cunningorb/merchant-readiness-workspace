<?php

namespace Tests\Unit\Enums;

use App\Enums\ImportStatus;
use Tests\TestCase;

class ImportStatusTest extends TestCase
{
    public function test_backed_values_match_database_columns(): void
    {
        $this->assertSame('created', ImportStatus::Created->value);
        $this->assertSame('validating', ImportStatus::Validating->value);
        $this->assertSame('queued', ImportStatus::Queued->value);
        $this->assertSame('importing', ImportStatus::Importing->value);
        $this->assertSame('processing', ImportStatus::Processing->value);
        $this->assertSame('completed', ImportStatus::Completed->value);
        $this->assertSame('completed_with_warnings', ImportStatus::CompletedWithWarnings->value);
        $this->assertSame('failed', ImportStatus::Failed->value);
        $this->assertSame('cancelled', ImportStatus::Cancelled->value);
    }

    public function test_has_exactly_nine_cases(): void
    {
        $this->assertCount(9, ImportStatus::cases());
    }

    public function test_can_be_constructed_from_string_value(): void
    {
        $this->assertSame(ImportStatus::Queued, ImportStatus::from('queued'));
    }
}
