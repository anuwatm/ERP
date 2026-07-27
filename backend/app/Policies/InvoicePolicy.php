<?php

namespace App\Policies;

use App\Models\Invoice;
use App\Models\User;

class InvoicePolicy
{
    public function view(User $user, Invoice $invoice): bool
    {
        return $invoice->org_id === $user->org_id;
    }

    public function update(User $user, Invoice $invoice): bool
    {
        return $this->view($user, $invoice) && (float) $invoice->paid_amount <= 0 && $invoice->status !== 'void';
    }

    public function void(User $user, Invoice $invoice): bool
    {
        return $this->view($user, $invoice) && (float) $invoice->paid_amount <= 0 && $invoice->status !== 'void';
    }
}
