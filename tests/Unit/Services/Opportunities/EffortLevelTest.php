<?php

namespace Tests\Unit\Services\Opportunities;

use App\Enums\EffortLevel;
use Tests\TestCase;

class EffortLevelTest extends TestCase
{
    public function test_backed_values_match_database_columns(): void
    {
        $this->assertSame('low', EffortLevel::Low->value);
        $this->assertSame('medium', EffortLevel::Medium->value);
        $this->assertSame('high', EffortLevel::High->value);
    }

    public function test_can_be_constructed_from_string_value(): void
    {
        $this->assertSame(EffortLevel::High, EffortLevel::from('high'));
    }
}
