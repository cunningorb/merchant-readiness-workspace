<?php

namespace Tests\Unit\Services\Opportunities;

use App\Services\Opportunities\OpportunityEvidence;
use Error;
use Tests\TestCase;

class OpportunityEvidenceTest extends TestCase
{
    public function test_serializes_to_expected_json_shape(): void
    {
        $evidence = new OpportunityEvidence(
            inputs: ['average_order_value' => 75.0, 'monthly_orders' => 400],
            sourceAnswerKeys: ['catalog.average_order_value', 'business.monthly_orders'],
            why: ['Average order value of $75 across 400 monthly orders.'],
        );

        $this->assertSame([
            'inputs' => ['average_order_value' => 75.0, 'monthly_orders' => 400],
            'source_answer_keys' => ['catalog.average_order_value', 'business.monthly_orders'],
            'why' => ['Average order value of $75 across 400 monthly orders.'],
        ], $evidence->toArray());
    }

    public function test_round_trips_through_from_array(): void
    {
        $original = new OpportunityEvidence(
            inputs: ['x' => 1],
            sourceAnswerKeys: ['a.b'],
            why: ['because x'],
        );

        $rebuilt = OpportunityEvidence::fromArray($original->toArray());

        $this->assertEquals($original, $rebuilt);
    }

    public function test_properties_are_readonly(): void
    {
        $evidence = new OpportunityEvidence(inputs: [], sourceAnswerKeys: [], why: []);

        $this->expectException(Error::class);

        $evidence->why = ['mutated'];
    }
}
