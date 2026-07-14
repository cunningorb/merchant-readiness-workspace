<?php

namespace App\Services;

use App\Mail\AssessmentReportReady;
use App\Models\Assessment;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Throwable;

class SendAssessmentReportEmailService
{
    public function send(Assessment $assessment): void
    {
        $assessment->loadMissing(['answers', 'merchant', 'report']);

        $email = $assessment->answerValue('business.contact_email')
            ?? $assessment->merchant?->contact_email;

        if (! is_string($email) || $email === '' || $assessment->report === null) {
            return;
        }

        $companyName = $assessment->answerValue('business.company_name')
            ?? $assessment->merchant?->company_name
            ?? 'your store';

        try {
            Mail::to($email)->send(new AssessmentReportReady(
                companyName: $companyName,
                reportUrl: route('reports.show', $assessment->report->token),
            ));
        } catch (Throwable $exception) {
            Log::warning('Assessment report email could not be sent.', [
                'assessment_id' => $assessment->id,
                'message' => $exception->getMessage(),
            ]);
        }
    }
}
