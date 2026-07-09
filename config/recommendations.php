<?php

return [
    'rules' => [
        \App\Services\Recommendations\Rules\ShortReturnWindowRule::class,
        \App\Services\Recommendations\Rules\UndocumentedPolicyRule::class,
        \App\Services\Recommendations\Rules\NoExchangesOfferedRule::class,
        \App\Services\Recommendations\Rules\NoExchangeIncentivesRule::class,
        \App\Services\Recommendations\Rules\HighManualHoursRule::class,
        \App\Services\Recommendations\Rules\ReturnBottlenecksRule::class,
        \App\Services\Recommendations\Rules\ManualReturnToolingRule::class,
        \App\Services\Recommendations\Rules\BasicReturnToolingRule::class,
    ],
];
