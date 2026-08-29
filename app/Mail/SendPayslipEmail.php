<?php

namespace App\Mail;

use App\Models\Cutoff;
use App\Models\Payroll;
use Carbon\Carbon;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class SendPayslipEmail extends Mailable
{
    use Queueable, SerializesModels;

    protected $cutOff;
    protected $payroll;
    public function __construct(Payroll $payroll, Cutoff $cutoff)
    {
        $this->payroll = $payroll;
        $this->cutOff = $cutoff;
    }

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        $cutOff = $this->cutOff;
        return new Envelope(
            subject: 'Payslip: ' . Carbon::parse($cutOff->start_date)->format('F j, Y') ." to ". Carbon::parse($cutOff->end_date)->format('F j, Y')
        );
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        $payroll = $this->payroll;
        $employee = $payroll->employee;
        $timekeeping = $payroll->details ?? $payroll->timekeeping_summary;
        $company = env("COMPANY_NAME") === "SFI" ? "Savory Fastfood Inc." : "Tony Ting Food Concepts";

        return new Content(
            view: 'emails.PayslipEmail',
            with: [
                "payroll" => $payroll,
                "employee" => $employee,
                "company" => $company,
                "timekeeping" => $timekeeping
            ]
        );
    }

    /**
     * Get the attachments for the message.
     *
     * @return array<int, \Illuminate\Mail\Mailables\Attachment>
     */
    public function attachments(): array
    {
        return [];
    }
}
