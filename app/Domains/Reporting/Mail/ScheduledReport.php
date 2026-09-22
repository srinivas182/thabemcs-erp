<?php

declare(strict_types=1);

namespace App\Domains\Reporting\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Attachment;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

final class ScheduledReport extends Mailable
{
    use Queueable;

    public function __construct(
        public readonly string $reportTitle,
        public readonly string $subtitle,
        public readonly string $companyName,
        public readonly string $fileName,
        public readonly string $fileContent,
        public readonly string $mime,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: "{$this->reportTitle}: {$this->companyName}");
    }

    public function content(): Content
    {
        return new Content(htmlString: '<p>Attached is the '.e($this->reportTitle).' for '.e($this->companyName).' ('.e($this->subtitle).').</p>'
            .'<p>You receive this because you are on the schedule. Ask a Company Admin to remove you.</p>');
    }

    /**
     * @return list<Attachment>
     */
    public function attachments(): array
    {
        return [Attachment::fromData(fn (): string => $this->fileContent, $this->fileName)->withMime($this->mime)];
    }
}
