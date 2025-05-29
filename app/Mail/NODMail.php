<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Attachment;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class NODMail extends Mailable implements ShouldQueue
{

    protected $file_path;
    public function __construct($filePath)
    {
        $this->file_path = $filePath;
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Tardiness - Notice of Disciplinary Action',
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.NODEmail',
        );
    }

    public function attachments(): array
    {
        return [
            Attachment::fromPath($this->file_path),
        ];
    }
}
