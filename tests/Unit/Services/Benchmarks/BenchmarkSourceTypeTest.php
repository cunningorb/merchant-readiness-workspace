<?php

namespace Tests\Unit\Services\Benchmarks;

use App\Enums\BenchmarkSourceType;
use Tests\TestCase;

class BenchmarkSourceTypeTest extends TestCase
{
    public function test_backed_values_match_database_columns(): void
    {
        $this->assertSame('illustrative', BenchmarkSourceType::Illustrative->value);
        $this->assertSame('configured', BenchmarkSourceType::Configured->value);
        $this->assertSame('external_reference', BenchmarkSourceType::ExternalReference->value);
        $this->assertSame('proprietary', BenchmarkSourceType::Proprietary->value);
    }

    public function test_can_be_constructed_from_string_value(): void
    {
        $this->assertSame(BenchmarkSourceType::Configured, BenchmarkSourceType::from('configured'));
    }
}
