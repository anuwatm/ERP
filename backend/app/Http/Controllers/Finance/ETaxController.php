<?php

namespace App\Http\Controllers\Finance;

use App\Http\Controllers\Controller;
use App\Jobs\SubmitETaxDocument;
use App\Models\AuditLog;
use App\Models\CreditDebitNote;
use App\Models\ETaxConfig;
use App\Models\ETaxDocument;
use App\Models\Invoice;
use App\Models\Payment;
use App\Services\ETax\ETaxDocumentService;
use App\Services\ETax\ImmutableETaxDocumentException;
use App\Services\ETax\RDPrepExportService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class ETaxController extends Controller
{
    public function index(Request $request): Response
    {
        $orgId = $request->user()->org_id;

        return Inertia::render('Finance/ETax', [
            'config' => ETaxConfig::firstOrNew(
                ['org_id' => $orgId],
                ['mode' => 'disabled', 'signature_mode' => 'external'],
            )->only(['mode', 'provider_code', 'certificate_reference', 'certificate_expires_at', 'signature_mode']),
            'documents' => ETaxDocument::where('org_id', $orgId)->with('attempts')->latest()->take(100)->get(),
            'invoices' => Invoice::where('org_id', $orgId)->whereIn('status', ['sent', 'partially_paid', 'paid', 'overdue'])->latest()->take(100)->get(['id', 'invoice_no', 'status', 'issue_date', 'total']),
            'payments' => Payment::where('org_id', $orgId)->where('entry_type', 'receipt')->latest('payment_date')->take(100)->get(['id', 'invoice_id', 'amount', 'payment_date']),
            'notes' => CreditDebitNote::where('org_id', $orgId)->latest('issue_date')->take(100)->get(['id', 'note_no', 'type', 'issue_date', 'total']),
        ]);
    }

    public function updateConfig(Request $request): RedirectResponse
    {
        $data = $request->validate(['mode' => ['required', Rule::in(ETaxConfig::MODES)], 'provider_code' => ['nullable', 'string', 'max:50'], 'certificate_reference' => ['nullable', 'string', 'max:255'], 'certificate_expires_at' => ['nullable', 'date'], 'signature_mode' => ['required', Rule::in(['external', 'none'])]]);
        if ($data['mode'] === 'provider' && blank($data['provider_code'])) {
            abort(422, 'Provider code is required for provider mode.');
        }
        $config = ETaxConfig::updateOrCreate(['org_id' => $request->user()->org_id], [...$data, 'updated_by' => $request->user()->id, 'created_by' => $request->user()->id]);
        AuditLog::create(['org_id' => $config->org_id, 'actor_user_id' => $request->user()->id, 'action' => 'e_tax.config_update', 'entity_type' => 'e_tax_config', 'entity_id' => $config->id, 'after_json' => $config->only(['mode', 'provider_code', 'certificate_reference', 'certificate_expires_at', 'signature_mode'])]);

        return back()->with('success', 'e-Tax configuration updated.');
    }

    public function generate(Request $request, ETaxDocumentService $service): RedirectResponse
    {
        $data = $request->validate(['source_type' => ['required', Rule::in(['invoice', 'payment', 'credit_debit_note'])], 'source_id' => ['required', 'uuid'], 'document_type' => ['required', Rule::in(ETaxDocument::TYPES)]]);
        $model = match ($data['source_type']) {
            'invoice' => Invoice::query(), 'payment' => Payment::query(), default => CreditDebitNote::query()
        };
        $source = $model->where('org_id', $request->user()->org_id)->findOrFail($data['source_id']);
        try {
            $document = $service->generate($source, $data['document_type'], $request->user()->id);
        } catch (ImmutableETaxDocumentException $exception) {
            abort(422, $exception->getMessage());
        }

        return back()->with('success', "e-Tax XML {$document->document_no} generated.");
    }

    public function download(Request $request, ETaxDocument $document)
    {
        abort_unless($document->org_id === $request->user()->org_id, 403);
        abort_unless(Storage::disk('local')->exists($document->xml_storage_path), 404);

        return Storage::disk('local')->download($document->xml_storage_path, $document->document_no.'.xml', ['Content-Type' => 'application/xml; charset=UTF-8']);
    }

    public function submit(Request $request, ETaxDocument $document): RedirectResponse
    {
        abort_unless($document->org_id === $request->user()->org_id, 403);
        abort_if(in_array($document->status, ['submitted', 'accepted'], true), 422, 'Document is already submitted.');
        SubmitETaxDocument::dispatch($document->id);
        AuditLog::create(['org_id' => $document->org_id, 'actor_user_id' => $request->user()->id, 'action' => 'e_tax.submit_queued', 'entity_type' => 'e_tax_document', 'entity_id' => $document->id]);

        return back()->with('success', 'e-Tax submission queued.');
    }

    public function rdPrep(Request $request, RDPrepExportService $service)
    {
        $data = $request->validate(['form' => ['required', Rule::in(['pnd3', 'pnd53'])], 'date_from' => ['nullable', 'date'], 'date_to' => ['nullable', 'date', 'after_or_equal:date_from']]);
        $contents = $service->export($request->user()->organization, $data['form'], $data['date_from'] ?? null, $data['date_to'] ?? null);
        AuditLog::create(['org_id' => $request->user()->org_id, 'actor_user_id' => $request->user()->id, 'action' => 'e_tax.rd_prep_export', 'entity_type' => 'organization', 'entity_id' => $request->user()->org_id, 'after_json' => $data]);

        return response($contents, 200, ['Content-Type' => 'text/plain; charset=UTF-8', 'Content-Disposition' => 'attachment; filename="rd-prep-'.$data['form'].'.txt"']);
    }
}
