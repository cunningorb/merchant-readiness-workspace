<?php

return [
    'metrics' => [
        'return_window_days' => [
            'label' => 'Return window',
            'unit' => 'days',
            'answer_key' => 'return_policy.window_days',
            'band_values' => [
                '14 days or less' => 14,
                '15-30 days' => 22,
                '31-60 days' => 45,
                'More than 60 days' => 75,
            ],
        ],
        'manual_processing_hours_per_week' => [
            'label' => 'Manual processing time',
            'unit' => 'hours_per_week',
            'answer_key' => 'manual_operations.weekly_hours',
            'band_values' => null, // reuse config('assessment.opportunities.weekly_manual_hours_band_midpoints') instead of duplicating
        ],
        'catalog_sku_count' => [
            'label' => 'Catalog size',
            'unit' => 'sku_count',
            'answer_key' => 'catalog.sku_count',
            'band_values' => [
                'Under 500' => 250,
                '500-5,000' => 2750,
                '5,001-25,000' => 15000,
                '25,000+' => 40000,
            ],
        ],
    ],

    'catalog_profile_priority' => ['Apparel' => 'apparel', 'Footwear' => 'footwear', 'Home goods' => 'home_goods'],
    // Derive a single segment from catalog.fit_sensitive_categories (a multiselect) by checking this
    // map in insertion order and returning the first match found in the merchant's selected values;
    // null if none match. This same derived value is used for BOTH the "industry" and "catalog_profile"
    // resolver dimensions (documented limitation: we don't have a separate industry signal yet).
];
