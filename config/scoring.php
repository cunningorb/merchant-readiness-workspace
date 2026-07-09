<?php

return [
    'scorers' => [
        \App\Services\Scoring\Questions\ReturnWindowScorer::class,
        \App\Services\Scoring\Questions\PolicyClarityScorer::class,
        \App\Services\Scoring\Questions\WeeklyHoursScorer::class,
        \App\Services\Scoring\Questions\CommonBottlenecksScorer::class,
        \App\Services\Scoring\Questions\ExchangesOfferedScorer::class,
        \App\Services\Scoring\Questions\ExchangeIncentivesScorer::class,
        \App\Services\Scoring\Questions\ReturnToolsScorer::class,
    ],

    'section_weights' => [
        'return_policy' => 30,
        'manual_operations' => 30,
        'exchanges' => 20,
        'platform' => 20,
    ],

    'tiers' => [
        39 => 'Foundational',
        64 => 'Developing',
        84 => 'Established',
        100 => 'Advanced',
    ],
];
