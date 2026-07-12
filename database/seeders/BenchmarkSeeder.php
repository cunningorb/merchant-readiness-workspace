<?php

namespace Database\Seeders;

use App\Enums\BenchmarkSourceType;
use App\Models\BenchmarkSet;
use Illuminate\Database\Seeder;

class BenchmarkSeeder extends Seeder
{
    private const SET_NAME = 'Illustrative Returns Benchmark';

    private const SET_VERSION = '1.0';

    public function run(): void
    {
        if (BenchmarkSet::where('name', self::SET_NAME)->where('version', self::SET_VERSION)->exists()) {
            return;
        }

        $benchmarkSet = BenchmarkSet::create([
            'name' => self::SET_NAME,
            'version' => self::SET_VERSION,
            'source_type' => BenchmarkSourceType::Illustrative->value,
            'source_label' => 'Illustrative benchmark',
            'methodology' => 'These are configured, illustrative reference ranges, not measured industry data. '
                .'They are used until the product has an authoritative proprietary dataset.',
            'is_active' => true,
        ]);

        foreach ($this->values() as $value) {
            $benchmarkSet->values()->create($value);
        }
    }

    /**
     * @return array<int, array{metric_key: string, industry: ?string, minimum_value: float, maximum_value: float, unit: string}>
     */
    private function values(): array
    {
        return [
            ['metric_key' => 'return_window_days', 'industry' => 'apparel', 'minimum_value' => 30, 'maximum_value' => 45, 'unit' => 'days'],
            ['metric_key' => 'return_window_days', 'industry' => 'footwear', 'minimum_value' => 30, 'maximum_value' => 45, 'unit' => 'days'],
            ['metric_key' => 'return_window_days', 'industry' => 'home_goods', 'minimum_value' => 20, 'maximum_value' => 30, 'unit' => 'days'],
            ['metric_key' => 'return_window_days', 'industry' => null, 'minimum_value' => 21, 'maximum_value' => 30, 'unit' => 'days'],

            ['metric_key' => 'manual_processing_hours_per_week', 'industry' => 'apparel', 'minimum_value' => 3, 'maximum_value' => 6, 'unit' => 'hours_per_week'],
            ['metric_key' => 'manual_processing_hours_per_week', 'industry' => 'footwear', 'minimum_value' => 4, 'maximum_value' => 8, 'unit' => 'hours_per_week'],
            ['metric_key' => 'manual_processing_hours_per_week', 'industry' => 'home_goods', 'minimum_value' => 2, 'maximum_value' => 5, 'unit' => 'hours_per_week'],
            ['metric_key' => 'manual_processing_hours_per_week', 'industry' => null, 'minimum_value' => 3, 'maximum_value' => 8, 'unit' => 'hours_per_week'],

            ['metric_key' => 'catalog_sku_count', 'industry' => 'apparel', 'minimum_value' => 1000, 'maximum_value' => 8000, 'unit' => 'sku_count'],
            ['metric_key' => 'catalog_sku_count', 'industry' => 'footwear', 'minimum_value' => 800, 'maximum_value' => 6000, 'unit' => 'sku_count'],
            ['metric_key' => 'catalog_sku_count', 'industry' => 'home_goods', 'minimum_value' => 500, 'maximum_value' => 4000, 'unit' => 'sku_count'],
            ['metric_key' => 'catalog_sku_count', 'industry' => null, 'minimum_value' => 500, 'maximum_value' => 10000, 'unit' => 'sku_count'],
        ];
    }
}
