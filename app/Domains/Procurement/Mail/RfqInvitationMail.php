<?php

declare(strict_types=1);

namespace App\Domains\Procurement\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

final class RfqInvitationMail extends Mailable
{
    use Queueable;

    public function __construct(
        public readonly string $companyName,
        public readonly string $reference,
        public readonly string $title,
        public readonly string $closesOn,
        public readonly string $url,
        public readonly ?string $message,
        public readonly string $senderName,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: "Request for quotation {$this->reference}: {$this->title}");
    }

    public function content(): Content
    {
        return new Content(htmlString: '<p>Good day,</p>'
            .'<p>'.e($this->companyName).' invites you to quote for <strong>'.e($this->title).'</strong> ('.e($this->reference).').</p>'
            .($this->message ? '<p>'.nl2br(e($this->message)).'</p>' : '')
            .'<p><a href="'.e($this->url).'">View the items and submit your quote</a>. Quotes close on '.e($this->closesOn).'.</p>'
            .'<p>This link is personal to your company; please do not forward it. Your tax clearance and other compliance documents must be valid for an order to be placed.</p>'
            .'<p>Kind regards,<br>'.e($this->senderName).'<br>'.e($this->companyName).'</p>');
    }
}
