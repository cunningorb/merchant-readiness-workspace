<?php

namespace Tests\Unit\Enums;

use App\Enums\ImportMethod;
use Tests\TestCase;

class ImportMethodTest extends TestCase
{
    public function test_backed_values_match_database_columns(): void
    {
        $this->assertSame('csv', ImportMethod::Csv->value);
        $this->assertSame('api', ImportMethod::Api->value);
        $this->assertSame('demo', ImportMethod::Demo->value);
    }

    public function test_has_exactly_three_cases(): void
    {
        $this->assertCount(3, ImportMethod::cases());
    }

    public function test_can_be_constructed_from_string_value(): void
    {
        $this->assertSame(ImportMethod::Csv, ImportMethod::from('csv'));
    }
}
