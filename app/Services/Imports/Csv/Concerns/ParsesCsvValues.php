<?php

namespace App\Services\Imports\Csv\Concerns;

use DateTime;

/**
 * Field-level parsing helpers shared by the three CSV importers. The
 * "parseOptional*" helpers return a [success, value] tuple rather than
 * throwing: blank input is a valid "no value" (success, null); non-blank
 * input that fails to parse as a number is a failure (not success, null),
 * and callers decide what a parse failure means for their format (a
 * per-row rejection for products.csv, a null field for the two
 * summary-row formats).
 */
trait ParsesCsvValues
{
    private function nullableString(?string $value): ?string
    {
        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }

    private function parseBoolean(?string $value): bool
    {
        $value = strtolower(trim((string) $value));

        return in_array($value, ['true', '1'], true);
    }

    /**
     * @return array{0: bool, 1: int|null}
     */
    private function parseOptionalInt(?string $value): array
    {
        $trimmed = trim((string) $value);

        if ($trimmed === '') {
            return [true, null];
        }

        if (! is_numeric($trimmed)) {
            return [false, null];
        }

        return [true, (int) $trimmed];
    }

    /**
     * @return array{0: bool, 1: float|null}
     */
    private function parseOptionalFloat(?string $value): array
    {
        $trimmed = trim((string) $value);

        if ($trimmed === '') {
            return [true, null];
        }

        if (! is_numeric($trimmed)) {
            return [false, null];
        }

        return [true, (float) $trimmed];
    }

    private function isValidDate(string $value): bool
    {
        $date = DateTime::createFromFormat('Y-m-d', $value);

        return $date !== false && $date->format('Y-m-d') === $value;
    }
}
