<?php

namespace App\Services\Benchmarks;

use App\Models\BenchmarkValue;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Resolves the best-matching BenchmarkValue for a metric and comparison
 * context, using a deterministic fallback order (verbatim from the project
 * plan):
 *
 * 1. exact industry + annual_order_volume range + platform match
 * 2. industry + annual_order_volume range match (platform ignored)
 * 3. industry match only (volume and platform ignored)
 * 4. global (industry is null on the row) — first active, in-window row
 * 5. no benchmark -> null
 *
 * Only rows belonging to an active benchmark set whose effective window
 * (if set) contains now are considered. `catalog_profile` is intentionally
 * not part of this algorithm.
 */
class BenchmarkResolver
{
    /**
     * @param  array{industry: ?string, platform: ?string, annual_order_volume: ?float}  $context
     */
    public function resolve(string $metricKey, array $context): ?BenchmarkValue
    {
        $candidates = $this->candidatesFor($metricKey);

        $industry = $context['industry'] ?? null;
        $platform = $context['platform'] ?? null;
        $annualOrderVolume = $context['annual_order_volume'] ?? null;

        if ($industry !== null) {
            $tier1 = $candidates->first(fn (BenchmarkValue $value) => $this->industryMatches($value, $industry)
                && $this->volumeMatches($value, $annualOrderVolume)
                && $this->platformMatches($value, $platform));

            if ($tier1 !== null) {
                return $tier1;
            }

            $tier2 = $candidates->first(fn (BenchmarkValue $value) => $this->industryMatches($value, $industry)
                && $this->volumeMatches($value, $annualOrderVolume));

            if ($tier2 !== null) {
                return $tier2;
            }

            $tier3 = $candidates->first(fn (BenchmarkValue $value) => $this->industryMatches($value, $industry));

            if ($tier3 !== null) {
                return $tier3;
            }
        }

        return $candidates->first(fn (BenchmarkValue $value) => $value->industry === null);
    }

    /**
     * @return Collection<int, BenchmarkValue>
     */
    private function candidatesFor(string $metricKey): Collection
    {
        return BenchmarkValue::query()
            ->where('metric_key', $metricKey)
            ->whereHas('benchmarkSet', function (Builder $query) {
                $now = Carbon::now();

                $query->where('is_active', true)
                    ->where(function (Builder $query) use ($now) {
                        $query->whereNull('effective_from')->orWhere('effective_from', '<=', $now);
                    })
                    ->where(function (Builder $query) use ($now) {
                        $query->whereNull('effective_to')->orWhere('effective_to', '>=', $now);
                    });
            })
            ->orderBy('id')
            ->get();
    }

    private function industryMatches(BenchmarkValue $value, string $industry): bool
    {
        return $value->industry === $industry;
    }

    private function platformMatches(BenchmarkValue $value, ?string $platform): bool
    {
        if ($value->platform === null) {
            return true;
        }

        return $platform !== null && $value->platform === $platform;
    }

    private function volumeMatches(BenchmarkValue $value, ?float $annualOrderVolume): bool
    {
        $min = $value->annual_order_volume_min;
        $max = $value->annual_order_volume_max;

        if ($min === null && $max === null) {
            return true;
        }

        if ($min === null || $max === null || $annualOrderVolume === null) {
            return false;
        }

        return $annualOrderVolume >= (float) $min && $annualOrderVolume <= (float) $max;
    }
}
