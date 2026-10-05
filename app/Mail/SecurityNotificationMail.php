<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class SecurityNotificationMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public string $messageText) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'INTSEC account security notification');
    }

    public function content(): Content
    {
        return new Content(view: 'mail.security-notification');
    }
}
