<?php

namespace App\Mail;

use App\Models\PayrollItem;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class PayslipMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public PayrollItem $item,
        public string $pdfContent,
        public string $filename,
    ) {}

    public function envelope(): Envelope
    {
        $run = $this->item->run;
        $subject = "Your Payslip for Period: {$run?->period_start?->format('Y-m-d')} to {$run?->period_end?->format('Y-m-d')}";

        return new Envelope(subject: $subject);
    }

    public function content(): Content
    {
        $run = $this->item->run;

        return new Content(
            htmlString: "<p>Dear employee,</p><p>Please find attached your payslip for the period {$run?->period_start?->format('Y-m-d')} to {$run?->period_end?->format('Y-m-d')}.</p><p>Best regards,<br>Human Resources</p>"
        );
    }

    public function attachments(): array
    {
        return [
            Attachment::fromData(fn () => $this->pdfContent, $this->filename)
                ->withMime('application/pdf'),
        ];
    }
}
