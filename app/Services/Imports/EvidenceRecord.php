<?php

namespace App\Services\Imports;

final readonly class EvidenceRecord
{
    public string $source;

    public mixed $authoritativeValue;

    public function __construct(
        public string $key,
        public mixed $answerValue,
        public mixed $importedValue,
    ) {
        if (self::isAnswered($this->answerValue)) {
            $this->source = 'merchant_answer';
            $this->authoritativeValue = $this->answerValue;
        } elseif (self::isAnswered($this->importedValue)) {
            $this->source = 'imported';
            $this->authoritativeValue = $this->importedValue;
        } else {
            $this->source = 'none';
            $this->authoritativeValue = null;
        }
    }

    /**
     * Matches the "isAnswered" convention already used in SubmitAssessmentService:
     * null, empty string, and empty array all count as "not present".
     */
    private static function isAnswered(mixed $value): bool
    {
        return $value !== null && $value !== '' && $value !== [];
    }
}
