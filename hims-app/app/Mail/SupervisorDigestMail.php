<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class SupervisorDigestMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public array $digestData
    ) {}

    public function envelope(): Envelope
    {
        $deptName = $this->digestData['department_name'] ?? 'Team';

        return new Envelope(
            subject: "HIMS Weekly Digest: {$deptName} Team Overview & Action Items",
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.supervisor-digest',
        );
    }

    public function attachments(): array
    {
        return [];
    }
}
