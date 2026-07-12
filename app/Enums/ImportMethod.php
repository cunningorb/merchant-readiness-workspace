<?php

namespace App\Enums;

enum ImportMethod: string
{
    case Csv = 'csv';
    case Api = 'api';
    case Demo = 'demo';
}
