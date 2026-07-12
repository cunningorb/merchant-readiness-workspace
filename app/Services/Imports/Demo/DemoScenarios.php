<?php

namespace App\Services\Imports\Demo;

use InvalidArgumentException;

/**
 * Fixture data for the three demo scenarios (apparel, footwear, home_goods).
 *
 * This is a single, static, data-only class: no side effects, no I/O, no
 * dependency on Eloquent. The three demo importers (DemoCatalogImporter,
 * DemoOrderReturnImporter, DemoInventoryImporter) read from it and persist
 * the rows. Keeping the fixture data here means the numbers quoted in the
 * milestone plan live in exactly one place.
 *
 * Each product's `variants` array is a deliberately representative sample
 * (2-4 rows) rather than one row per unit of `variant_count` — variant_count
 * is a catalog-health aggregate, not a row-count contract. A product with
 * neither a size nor a color option gets a single default variant (no
 * option_names/option_values) rather than several interchangeable rows.
 */
class DemoScenarios
{
    public const APPAREL = 'apparel';

    public const FOOTWEAR = 'footwear';

    public const HOME_GOODS = 'home_goods';

    /** @var array<int, string> */
    public const SCENARIOS = [self::APPAREL, self::FOOTWEAR, self::HOME_GOODS];

    /**
     * @return array{
     *     products: array<int, array<string, mixed>>,
     *     order_metric: array<string, mixed>,
     *     return_metric: array<string, mixed>,
     *     inventory_metric: array<string, mixed>,
     *     location_metric: array<string, mixed>,
     * }
     */
    public static function for(string $scenario): array
    {
        return match ($scenario) {
            self::APPAREL => self::apparel(),
            self::FOOTWEAR => self::footwear(),
            self::HOME_GOODS => self::homeGoods(),
            default => throw new InvalidArgumentException("Unknown demo scenario '{$scenario}'."),
        };
    }

    private static function apparel(): array
    {
        return [
            'products' => [
                [
                    'title' => 'Classic Crew Tee',
                    'product_type' => 'Apparel',
                    'has_size_option' => true,
                    'has_color_option' => true,
                    'variant_count' => 8,
                    'sparse_description' => false,
                    'media_count' => 5,
                    'low_media' => false,
                    'price' => 28.00,
                    'variants' => [
                        ['option_names' => ['Size', 'Color'], 'option_values' => ['S', 'Black']],
                        ['option_names' => ['Size', 'Color'], 'option_values' => ['M', 'Black']],
                        ['option_names' => ['Size', 'Color'], 'option_values' => ['M', 'Navy']],
                        ['option_names' => ['Size', 'Color'], 'option_values' => ['L', 'Navy']],
                    ],
                ],
                [
                    'title' => 'Relaxed Denim Jacket',
                    'product_type' => 'Apparel',
                    'has_size_option' => true,
                    'has_color_option' => true,
                    'variant_count' => 6,
                    'sparse_description' => false,
                    'media_count' => 6,
                    'low_media' => false,
                    'price' => 98.00,
                    'variants' => [
                        ['option_names' => ['Size', 'Color'], 'option_values' => ['S', 'Indigo']],
                        ['option_names' => ['Size', 'Color'], 'option_values' => ['M', 'Indigo']],
                        ['option_names' => ['Size', 'Color'], 'option_values' => ['L', 'Black']],
                    ],
                ],
                [
                    'title' => 'Everyday Midi Dress',
                    'product_type' => 'Apparel',
                    'has_size_option' => true,
                    'has_color_option' => true,
                    'variant_count' => 10,
                    'sparse_description' => false,
                    'media_count' => 4,
                    'low_media' => false,
                    'price' => 64.00,
                    'variants' => [
                        ['option_names' => ['Size', 'Color'], 'option_values' => ['XS', 'Rust']],
                        ['option_names' => ['Size', 'Color'], 'option_values' => ['S', 'Rust']],
                        ['option_names' => ['Size', 'Color'], 'option_values' => ['M', 'Black']],
                        ['option_names' => ['Size', 'Color'], 'option_values' => ['L', 'Black']],
                    ],
                ],
                [
                    'title' => 'Performance Leggings',
                    'product_type' => 'Apparel',
                    'has_size_option' => true,
                    'has_color_option' => true,
                    'variant_count' => 12,
                    'sparse_description' => true,
                    'media_count' => 1,
                    'low_media' => true,
                    'price' => 54.00,
                    'variants' => [
                        ['option_names' => ['Size', 'Color'], 'option_values' => ['XS', 'Black']],
                        ['option_names' => ['Size', 'Color'], 'option_values' => ['S', 'Black']],
                        ['option_names' => ['Size', 'Color'], 'option_values' => ['M', 'Charcoal']],
                        ['option_names' => ['Size', 'Color'], 'option_values' => ['L', 'Charcoal']],
                    ],
                ],
                [
                    'title' => 'Oversized Hoodie',
                    'product_type' => 'Apparel',
                    'has_size_option' => false,
                    'has_color_option' => true,
                    'variant_count' => 3,
                    'sparse_description' => true,
                    'media_count' => 1,
                    'low_media' => true,
                    'price' => 62.00,
                    'variants' => [
                        ['option_names' => ['Color'], 'option_values' => ['Heather Grey']],
                        ['option_names' => ['Color'], 'option_values' => ['Black']],
                        ['option_names' => ['Color'], 'option_values' => ['Olive']],
                    ],
                ],
                [
                    'title' => 'Linen Wrap Top',
                    'product_type' => 'Apparel',
                    'has_size_option' => false,
                    'has_color_option' => true,
                    'variant_count' => 2,
                    'sparse_description' => false,
                    'media_count' => 5,
                    'low_media' => false,
                    'price' => 46.00,
                    'variants' => [
                        ['option_names' => ['Color'], 'option_values' => ['Natural']],
                        ['option_names' => ['Color'], 'option_values' => ['Black']],
                    ],
                ],
            ],
            'order_metric' => [
                'order_count' => 480,
                'annualized_order_volume' => 5760,
                'average_order_value' => 78.50,
            ],
            // refund_units_total = round(order_count * estimated_refund_rate)
            //                    = round(480 * 0.19) = round(91.2) = 91
            // refund_amount_total = refund_units_total * average_order_value
            //                     = 91 * 78.50 = 7143.50
            'return_metric' => [
                'estimated_refund_rate' => 0.19,
                'exchange_share' => 0.09,
                'refund_only_share' => 0.82,
                'average_time_to_refund_days' => 6.5,
                'top_refund_categories' => ['Performance Leggings', 'Relaxed Denim Jacket'],
                'refund_units_total' => 91,
                'refund_amount_total' => 7143.50,
            ],
            'inventory_metric' => [
                'percent_multi_location_stock' => 35.0,
                'percent_low_or_zero_stock' => 22.0,
                'sku_completeness' => 88.0,
                'exchange_availability_risk' => 'medium',
            ],
            'location_metric' => [
                'active_location_count' => 2,
                'operational_complexity_score' => 'medium',
            ],
        ];
    }

    private static function footwear(): array
    {
        return [
            'products' => [
                [
                    'title' => 'Trail Runner',
                    'product_type' => 'Footwear',
                    'has_size_option' => true,
                    'has_color_option' => true,
                    'variant_count' => 12,
                    'sparse_description' => false,
                    'media_count' => 6,
                    'low_media' => false,
                    'price' => 129.00,
                    'variants' => [
                        ['option_names' => ['Size', 'Color'], 'option_values' => ['8', 'Grey']],
                        ['option_names' => ['Size', 'Color'], 'option_values' => ['9', 'Grey']],
                        ['option_names' => ['Size', 'Color'], 'option_values' => ['10', 'Black']],
                        ['option_names' => ['Size', 'Color'], 'option_values' => ['11', 'Black']],
                    ],
                ],
                [
                    'title' => 'Studio Flat',
                    'product_type' => 'Footwear',
                    'has_size_option' => true,
                    'has_color_option' => false,
                    'variant_count' => 8,
                    'sparse_description' => false,
                    'media_count' => 4,
                    'low_media' => false,
                    'price' => 79.00,
                    'variants' => [
                        ['option_names' => ['Size'], 'option_values' => ['6']],
                        ['option_names' => ['Size'], 'option_values' => ['7']],
                        ['option_names' => ['Size'], 'option_values' => ['8']],
                        ['option_names' => ['Size'], 'option_values' => ['9']],
                    ],
                ],
                [
                    'title' => 'Weekend Sneaker',
                    'product_type' => 'Footwear',
                    'has_size_option' => true,
                    'has_color_option' => true,
                    'variant_count' => 10,
                    'sparse_description' => false,
                    'media_count' => 5,
                    'low_media' => false,
                    'price' => 89.00,
                    'variants' => [
                        ['option_names' => ['Size', 'Color'], 'option_values' => ['7', 'White']],
                        ['option_names' => ['Size', 'Color'], 'option_values' => ['8', 'White']],
                        ['option_names' => ['Size', 'Color'], 'option_values' => ['9', 'Black']],
                        ['option_names' => ['Size', 'Color'], 'option_values' => ['10', 'Black']],
                    ],
                ],
                [
                    'title' => 'All-Terrain Boot',
                    'product_type' => 'Footwear',
                    'has_size_option' => true,
                    'has_color_option' => false,
                    'variant_count' => 14,
                    'sparse_description' => true,
                    'media_count' => 1,
                    'low_media' => true,
                    'price' => 154.00,
                    'variants' => [
                        ['option_names' => ['Size'], 'option_values' => ['8']],
                        ['option_names' => ['Size'], 'option_values' => ['9']],
                        ['option_names' => ['Size'], 'option_values' => ['10']],
                        ['option_names' => ['Size'], 'option_values' => ['11']],
                    ],
                ],
                [
                    'title' => 'Slide Sandal',
                    'product_type' => 'Footwear',
                    'has_size_option' => true,
                    'has_color_option' => false,
                    'variant_count' => 6,
                    'sparse_description' => false,
                    'media_count' => 5,
                    'low_media' => false,
                    'price' => 39.00,
                    'variants' => [
                        ['option_names' => ['Size'], 'option_values' => ['7']],
                        ['option_names' => ['Size'], 'option_values' => ['8']],
                        ['option_names' => ['Size'], 'option_values' => ['9']],
                    ],
                ],
            ],
            'order_metric' => [
                'order_count' => 920,
                'annualized_order_volume' => 11040,
                'average_order_value' => 112.00,
            ],
            // refund_units_total = round(920 * 0.24) = round(220.8) = 221
            // refund_amount_total = 221 * 112.00 = 24752.00
            'return_metric' => [
                'estimated_refund_rate' => 0.24,
                'exchange_share' => 0.28,
                'refund_only_share' => 0.66,
                'average_time_to_refund_days' => 5.0,
                'top_refund_categories' => ['Trail Runner', 'All-Terrain Boot'],
                'refund_units_total' => 221,
                'refund_amount_total' => 24752.00,
            ],
            'inventory_metric' => [
                'percent_multi_location_stock' => 61.0,
                'percent_low_or_zero_stock' => 14.0,
                'sku_completeness' => 93.0,
                'exchange_availability_risk' => 'low',
            ],
            'location_metric' => [
                'active_location_count' => 4,
                'operational_complexity_score' => 'high',
            ],
        ];
    }

    private static function homeGoods(): array
    {
        return [
            'products' => [
                [
                    'title' => 'Ceramic Vase Set',
                    'product_type' => 'Home goods',
                    'has_size_option' => false,
                    'has_color_option' => false,
                    'variant_count' => 2,
                    'sparse_description' => false,
                    'media_count' => 6,
                    'low_media' => false,
                    'price' => 58.00,
                    'variants' => [
                        ['option_names' => null, 'option_values' => null],
                    ],
                ],
                [
                    'title' => 'Linen Throw Pillow',
                    'product_type' => 'Home goods',
                    'has_size_option' => false,
                    'has_color_option' => false,
                    'variant_count' => 3,
                    'sparse_description' => false,
                    'media_count' => 5,
                    'low_media' => false,
                    'price' => 34.00,
                    'variants' => [
                        ['option_names' => null, 'option_values' => null],
                    ],
                ],
                [
                    'title' => 'Oak Side Table',
                    'product_type' => 'Home goods',
                    'has_size_option' => false,
                    'has_color_option' => false,
                    'variant_count' => 1,
                    'sparse_description' => false,
                    'media_count' => 8,
                    'low_media' => false,
                    'price' => 220.00,
                    'variants' => [
                        ['option_names' => null, 'option_values' => null],
                    ],
                ],
                [
                    'title' => 'Woven Storage Basket',
                    'product_type' => 'Home goods',
                    'has_size_option' => false,
                    'has_color_option' => false,
                    'variant_count' => 2,
                    'sparse_description' => false,
                    'media_count' => 7,
                    'low_media' => false,
                    'price' => 42.00,
                    'variants' => [
                        ['option_names' => null, 'option_values' => null],
                    ],
                ],
            ],
            'order_metric' => [
                'order_count' => 140,
                'annualized_order_volume' => 1680,
                'average_order_value' => 145.00,
            ],
            // refund_units_total = round(140 * 0.08) = round(11.2) = 11
            // refund_amount_total = 11 * 145.00 = 1595.00
            'return_metric' => [
                'estimated_refund_rate' => 0.08,
                'exchange_share' => 0.03,
                'refund_only_share' => 0.95,
                'average_time_to_refund_days' => 9.0,
                'top_refund_categories' => ['Oak Side Table'],
                'refund_units_total' => 11,
                'refund_amount_total' => 1595.00,
            ],
            'inventory_metric' => [
                'percent_multi_location_stock' => 10.0,
                'percent_low_or_zero_stock' => 5.0,
                'sku_completeness' => 97.0,
                'exchange_availability_risk' => 'low',
            ],
            'location_metric' => [
                'active_location_count' => 1,
                'operational_complexity_score' => 'low',
            ],
        ];
    }
}
