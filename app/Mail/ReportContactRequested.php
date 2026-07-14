<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class ReportContactRequested extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public readonly string $companyName,
        public readonly ?string $contactEmail,
        public readonly string $reportUrl,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: "Talk to the team request from {$this->companyName}",
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'emails.report-contact-requested',
        );
    }
}
