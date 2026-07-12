<?php

namespace App\Enums;

enum BenchmarkSourceType: string
{
    case Illustrative = 'illustrative';
    case Configured = 'configured';
    case ExternalReference = 'external_reference';
    case Proprietary = 'proprietary';
}
