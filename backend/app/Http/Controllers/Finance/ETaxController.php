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
use App\Services\ETax\ETaxProviderGatewayService;
use App\Services\ETax\ImmutableETaxDocumentException;
use App\Services\ETax\RDPrepExportService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class ETaxController extends Controller
{
    public function index(Request $request): Response
    {
        $orgId = $request->user()->org_id;
        $configModel = ETaxConfig::firstOrNew(
            ['org_id' => $orgId],
            ['mode' => 'disabled', 'signature_mode' => 'external']
        );
        $settings = (array) ($configModel->provider_settings ?? []);

        $configData = [
            'id' => $configModel->id,
            'mode' => $configModel->mode,
            'provider_code' => $configModel->provider_code,
            'certificate_reference' => $configModel->certificate_reference,
            'certificate_expires_at' => $configModel->certificate_expires_at,
            'signature_mode' => $configModel->signature_mode,
            'api_url' => $settings['api_url'] ?? '',
            'client_id' => $settings['client_id'] ?? '',
            'has_client_secret' => ! empty($settings['client_secret']),
            'has_webhook_secret' => ! empty($settings['webhook_secret']),
        ];

        return Inertia::render('Finance/ETax', [
            'canManage' => $request->user()->hasPermissionCode('e_tax.manage'),
            'canSubmit' => $request->user()->hasPermissionCode('e_tax.submit'),
            'config' => $configData,
            'documents' => ETaxDocument::where('org_id', $orgId)->with('attempts')->latest()->take(100)->get(),
            'invoices' => Invoice::where('org_id', $orgId)->whereIn('status', ['sent', 'partially_paid', 'paid', 'overdue'])->latest()->take(100)->get(['id', 'invoice_no', 'status', 'issue_date', 'total']),
            'payments' => Payment::where('org_id', $orgId)->where('entry_type', 'receipt')->latest('payment_date')->take(100)->get(['id', 'invoice_id', 'amount', 'payment_date']),
            'notes' => CreditDebitNote::where('org_id', $orgId)->latest('issue_date')->take(100)->get(['id', 'note_no', 'type', 'issue_date', 'total']),
        ]);
    }

    public function updateConfig(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'mode' => ['required', Rule::in(ETaxConfig::MODES)],
            'provider_code' => ['nullable', 'string', 'max:50'],
            'certificate_reference' => ['nullable', 'string', 'max:255'],
            'certificate_expires_at' => ['nullable', 'date'],
            'signature_mode' => ['required', Rule::in(['external', 'none', 'xml_dsig'])],
            'api_url' => ['nullable', 'url', 'max:255'],
            'client_id' => ['nullable', 'string', 'max:100'],
            'client_secret' => ['nullable', 'string', 'max:255'],
            'webhook_secret' => ['nullable', 'string', 'max:255'],
        ]);

        if ($data['mode'] === 'mock') {
            $data['provider_code'] = 'mock';
            $data['signature_mode'] = 'none';
        }

        $existingConfig = ETaxConfig::where('org_id', $request->user()->org_id)->first();
        $currentSettings = (array) ($existingConfig?->provider_settings ?? []);

        $newSettings = [
            'api_url' => $data['api_url'] ?? ($currentSettings['api_url'] ?? null),
            'client_id' => $data['client_id'] ?? ($currentSettings['client_id'] ?? null),
            'client_secret' => ! empty($data['client_secret']) ? $data['client_secret'] : ($currentSettings['client_secret'] ?? null),
            'webhook_secret' => ! empty($data['webhook_secret']) ? $data['webhook_secret'] : ($currentSettings['webhook_secret'] ?? null),
        ];

        $config = ETaxConfig::updateOrCreate(
            ['org_id' => $request->user()->org_id],
            [
                'mode' => $data['mode'],
                'provider_code' => $data['provider_code'] ?? null,
                'certificate_reference' => $data['certificate_reference'] ?? null,
                'certificate_expires_at' => $data['certificate_expires_at'] ?? null,
                'signature_mode' => $data['signature_mode'],
                'provider_settings' => $newSettings,
                'updated_by' => $request->user()->id,
                'created_by' => $existingConfig ? $existingConfig->created_by : $request->user()->id,
            ]
        );

        AuditLog::create([
            'org_id' => $config->org_id,
            'actor_user_id' => $request->user()->id,
            'action' => 'e_tax.config_update',
            'entity_type' => 'e_tax_config',
            'entity_id' => $config->id,
            'after_json' => [
                'mode' => $config->mode,
                'provider_code' => $config->provider_code,
                'signature_mode' => $config->signature_mode,
                'certificate_reference' => $config->certificate_reference,
                'certificate_expires_at' => $config->certificate_expires_at,
                'api_url' => $newSettings['api_url'],
                'client_id' => $newSettings['client_id'],
            ],
        ]);

        return back()->with('success', 'e-Tax configuration updated.');
    }

    public function generate(Request $request, ETaxDocumentService $service): RedirectResponse
    {
        $data = $request->validate([
            'source_type' => ['required', Rule::in(['invoice', 'payment', 'credit_debit_note'])],
            'source_id' => ['required', 'uuid'],
            'document_type' => ['required', Rule::in(ETaxDocument::TYPES)],
        ]);
        $model = match ($data['source_type']) {
            'invoice' => Invoice::query(),
            'payment' => Payment::query(),
            default => CreditDebitNote::query()
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

    public function sign(Request $request, ETaxDocument $document, ETaxProviderGatewayService $gateway): RedirectResponse
    {
        abort_unless($document->org_id === $request->user()->org_id, 403);
        $gateway->sign($document, $request->user()->id);

        return back()->with('success', "e-Tax XML {$document->document_no} digitally signed.");
    }

    public function submit(Request $request, ETaxDocument $document): RedirectResponse
    {
        abort_unless($document->org_id === $request->user()->org_id, 403);
        DB::transaction(function () use ($request, $document) {
            $document = ETaxDocument::whereKey($document->id)->lockForUpdate()->firstOrFail();
            abort_unless(
                ETaxConfig::where('org_id', $document->org_id)->whereIn('mode', ['mock', 'provider'])->exists(),
                422,
                'Enable mock or provider mode first.'
            );
            if ($document->status === 'queued') {
                return;
            }
            abort_unless(
                in_array($document->status, ['generated', 'signed', 'error', 'rejected'], true) && $document->attempts()->count() < 3,
                422,
                'Document is complete or the three-attempt limit was reached.'
            );
            $document->update(['status' => 'queued', 'last_error' => null]);
            SubmitETaxDocument::dispatch($document->id)->afterCommit();
            AuditLog::create([
                'org_id' => $document->org_id,
                'actor_user_id' => $request->user()->id,
                'action' => 'e_tax.submit_queued',
                'entity_type' => 'e_tax_document',
                'entity_id' => $document->id,
            ]);
        });

        $mode = ETaxConfig::where('org_id', $document->org_id)->value('mode');
        $msg = $mode === 'provider' ? 'e-Tax provider submission queued.' : 'e-Tax mock simulation queued.';

        return back()->with('success', $msg);
    }

    public function inquire(Request $request, ETaxDocument $document, ETaxProviderGatewayService $gateway): RedirectResponse
    {
        abort_unless($document->org_id === $request->user()->org_id, 403);
        $result = $gateway->inquire($document, $request->user()->id);

        return back()->with('success', 'e-Tax status inquiry: '.$result['message']);
    }

    public function rdPrep(Request $request, RDPrepExportService $service)
    {
        $data = $request->validate([
            'form' => ['required', Rule::in(['pnd3', 'pnd53'])],
            'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date', 'after_or_equal:date_from'],
        ]);
        $contents = $service->export($request->user()->organization, $data['form'], $data['date_from'] ?? null, $data['date_to'] ?? null);
        AuditLog::create([
            'org_id' => $request->user()->org_id,
            'actor_user_id' => $request->user()->id,
            'action' => 'e_tax.rd_prep_export',
            'entity_type' => 'organization',
            'entity_id' => $request->user()->org_id,
            'after_json' => $data,
        ]);

        return response($contents, 200, [
            'Content-Type' => 'text/plain; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="rd-prep-'.$data['form'].'.txt"',
        ]);
    }
}
