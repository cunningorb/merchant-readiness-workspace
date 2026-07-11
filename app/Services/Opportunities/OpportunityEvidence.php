<?php

namespace App\Services\Opportunities;

final readonly class OpportunityEvidence
{
    /**
     * @param  array<string, mixed>  $inputs  Input values used in the calculation, keyed by name.
     * @param  array<int, string>  $sourceAnswerKeys  Assessment answer keys the calculation drew from.
     * @param  array<int, string>  $why  Human-readable lines explaining how the opportunity was derived.
     */
    public function __construct(
        public array $inputs,
        public array $sourceAnswerKeys,
        public array $why,
    ) {
    }

    /**
     * @return array{inputs: array<string, mixed>, source_answer_keys: array<int, string>, why: array<int, string>}
     */
    public function toArray(): array
    {
        return [
            'inputs' => $this->inputs,
            'source_answer_keys' => $this->sourceAnswerKeys,
            'why' => $this->why,
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            inputs: $data['inputs'] ?? [],
            sourceAnswerKeys: $data['source_answer_keys'] ?? [],
            why: $data['why'] ?? [],
        );
    }
}
