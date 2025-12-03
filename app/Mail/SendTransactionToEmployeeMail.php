<?php

namespace App\Mail;

use App\Models\ConverterDetail;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class SendTransactionToEmployeeMail extends Mailable
{
    protected $transactionDetails;
    public function __construct(ConverterDetail $transactionDetails)
    {
        $this->transactionDetails = $transactionDetails;
    }

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Transaction Crediting',
        );
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        $company = env("COMPANY_NAME") === "SFI" ? "Savory Fastfood Inc." : "Tony Ting Food Concepts";
        return new Content(
            view: 'emails.TransactionEmail',
            with: [
                "transaction" => $this->transactionDetails,
                "company" => $company
            ]
        );
    }

    public function attachments(): array
    {
        return [];
    }
}
