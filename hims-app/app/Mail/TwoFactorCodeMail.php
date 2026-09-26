<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class TwoFactorCodeMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public string $code,
        public string $userName = 'User'
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Your HIMS Verification Code: ' . $this->code,
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.two-factor-code',
            text: 'emails.two-factor-code-text',
        );
    }
}
