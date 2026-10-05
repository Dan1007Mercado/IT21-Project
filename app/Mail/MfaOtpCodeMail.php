<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class MfaOtpCodeMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public string $code, public string $purpose) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'INTSEC security verification code');
    }

    public function content(): Content
    {
        return new Content(view: 'mail.mfa-otp');
    }
}
