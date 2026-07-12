<?php

namespace App\Enums;

enum ImportStatus: string
{
    case Created = 'created';
    case Validating = 'validating';
    case Queued = 'queued';
    case Importing = 'importing';
    case Processing = 'processing';
    case Completed = 'completed';
    case CompletedWithWarnings = 'completed_with_warnings';
    case Failed = 'failed';
    case Cancelled = 'cancelled';
}
