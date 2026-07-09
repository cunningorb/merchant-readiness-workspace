<?php

namespace App\Services;

use App\Models\Assessment;
use App\Models\Merchant;

class CreateAssessmentService
{
    public function createAnonymousDraft(): Assessment
    {
        $merchant = Merchant::create([
            'company_name' => 'Anonymous merchant',
        ]);

        return Assessment::create([
            'merchant_id' => $merchant->id,
            'status' => 'draft',
            'started_at' => now(),
        ]);
    }
}
