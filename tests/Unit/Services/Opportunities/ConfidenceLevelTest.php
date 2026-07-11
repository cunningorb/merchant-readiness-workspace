<?php

namespace Tests\Unit\Services\Opportunities;

use App\Enums\ConfidenceLevel;
use Tests\TestCase;

class ConfidenceLevelTest extends TestCase
{
    public function test_backed_values_match_database_columns(): void
    {
        $this->assertSame('high', ConfidenceLevel::High->value);
        $this->assertSame('medium', ConfidenceLevel::Medium->value);
        $this->assertSame('low', ConfidenceLevel::Low->value);
    }

    public function test_can_be_constructed_from_string_value(): void
    {
        $this->assertSame(ConfidenceLevel::Medium, ConfidenceLevel::from('medium'));
    }
}
