<?php

namespace App\Services;

use App\Mail\PayslipMail;
use App\Models\PayrollItem;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\Mail;

class PayslipMailService
{
    /**
     * Send payslip PDF to employee personal email.
     *
     * @return array{sent: bool, recipient: ?string, error: ?string}
     */
    public function sendPayslip(PayrollItem $item): array
    {
        $item->load(['run', 'user', 'profile', 'employee']);
        $recipient = $item->employee?->email ?: $item->user?->email;

        if (! $recipient) {
            return [
                'sent' => false,
                'recipient' => null,
                'error' => 'No email address found for this employee.',
            ];
        }

        try {
            $view = view('payroll.payslip', [
                'item' => $item,
                'organization' => $item->run?->organization,
            ]);

            $pdfContent = Pdf::loadHTML($view->render())->setPaper('a4')->output();
            $filename = "payslip-{$item->run?->run_no}.pdf";

            Mail::to($recipient)->send(new PayslipMail($item, $pdfContent, $filename));

            return [
                'sent' => true,
                'recipient' => $recipient,
                'error' => null,
            ];
        } catch (\Throwable $e) {
            return [
                'sent' => false,
                'recipient' => $recipient,
                'error' => $e->getMessage(),
            ];
        }
    }
}
