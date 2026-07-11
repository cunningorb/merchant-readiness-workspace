<?php

return [
    'opportunities' => [
        'formula_version' => '1.0',

        'order_volume_band_midpoints' => [
            'Under 1,000' => 500,
            '1,000-10,000' => 5500,
            '10,001-50,000' => 30000,
            '50,000+' => 75000,
        ], // orders per month

        'weekly_manual_hours_band_midpoints' => [
            'Under 5' => 3,
            '5-20' => 12,
            '21-50' => 35,
            '50+' => 60,
        ],

        'assumed_average_order_value' => 85.0, // USD, configured assumption

        'base_return_rate' => 0.08,
        'fit_sensitive_return_rate' => 0.17, // used when fit_sensitive_categories includes Apparel or Footwear

        'eligible_refund_share' => 0.60,

        'exchange_conversion_lift' => ['min' => 0.08, 'max' => 0.12],
        'exchange_lift_dampener_when_exchanges_offered' => 0.5, // halve lift if exchanges.offered is already true

        'automation_share' => ['min' => 0.40, 'max' => 0.60],

        'policy_confusion_share' => [
            'Not documented' => 0.35,
            'Basic FAQ' => 0.25,
            'Detailed policy page' => 0.12,
            'Contextual policy by product/order' => 0.05,
        ],

        'clarity_reduction' => ['min' => 0.30, 'max' => 0.50],

        // Static effort per opportunity type for this milestone.
        'effort_by_type' => [
            'retained_revenue' => 'medium',
            'manual_work_savings' => 'medium',
            'support_contact_reduction' => 'low',
        ],

        // Maps a Recommendation's category to the opportunity type it supports,
        // used to rank recommendations by the opportunity they help capture.
        'recommendation_category_to_opportunity_type' => [
            'exchanges' => 'retained_revenue',
            'manual_operations' => 'manual_work_savings',
            'return_policy' => 'support_contact_reduction',
        ],
    ],
];
