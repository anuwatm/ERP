<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\Document;
use App\Models\DocumentCategory;
use App\Models\DocumentVersion;
use App\Models\RetentionPolicy;
use App\Services\DocumentService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class DocumentController extends Controller
{
    public function index(Request $request): Response
    {
        $orgId = $request->user()->org_id;

        return Inertia::render('Documents/Index', ['documents' => Document::where('org_id', $orgId)->with(['category:id,name', 'currentVersion', 'versions:id,document_id,version_no,original_name,scan_status,created_at', 'links:id,document_id,linkable_type,linkable_id,role'])->latest()->paginate(30), 'categories' => DocumentCategory::where('org_id', $orgId)->orderBy('name')->get(), 'retentionPolicies' => RetentionPolicy::where('org_id', $orgId)->latest('effective_from')->get(), 'can' => ['manage' => $request->user()->hasPermissionCode('documents.manage'), 'retentionManage' => $request->user()->hasPermissionCode('documents.retention.manage')]]);
    }

    public function store(Request $request, DocumentService $documents): RedirectResponse
    {
        $data = $request->validate(['title' => ['required', 'string', 'max:255'], 'category_id' => ['nullable', 'uuid', Rule::exists('document_categories', 'id')->where('org_id', $request->user()->org_id)], 'sensitivity' => ['required', Rule::in(['org_internal', 'department_restricted', 'finance_confidential', 'hr_confidential', 'executive_confidential'])], 'expires_at' => ['nullable', 'date'], 'renewal_alert_days' => ['nullable', 'integer', 'min:1', 'max:365'], 'file' => ['required', 'file', 'max:5120'], 'change_note' => ['nullable', 'string', 'max:500']]);
        $data['document_no'] = 'DOC-'.now()->format('YmdHis').'-'.strtoupper(substr((string) Str::uuid(), 0, 6));
        $category = ! empty($data['category_id']) ? DocumentCategory::where('org_id', $request->user()->org_id)->findOrFail($data['category_id']) : null;
        $data['retention_policy_id'] = $category?->retention_policy_id;
        $data['sensitivity'] = $category?->default_sensitivity ?? $data['sensitivity'];
        if (! $category?->expiry_tracking_enabled) {
            $data['expires_at'] = null;
            $data['renewal_alert_days'] = null;
        }
        $document = $documents->create($request->user(), $data, $request->file('file'));
        $this->audit($request, 'document.create', $document->id);

        return back()->with('success', 'Document uploaded.');
    }

    public function addVersion(Request $request, Document $document, DocumentService $documents): RedirectResponse
    {
        $data = $request->validate(['file' => ['required', 'file', 'max:5120'], 'change_note' => ['nullable', 'string', 'max:500']]);
        $version = $documents->appendVersion($request->user(), $document, $request->file('file'), $data['change_note'] ?? null);
        $document->update(['current_version_id' => $version->id]);
        $this->audit($request, 'document.version.create', $document->id);

        return back()->with('success', 'Document version uploaded.');
    }

    public function storeLink(Request $request, Document $document, DocumentService $documents): RedirectResponse
    {
        $data = $request->validate(['linkable_type' => ['required', 'string', 'max:80'], 'linkable_id' => ['required', 'uuid'], 'role' => ['required', Rule::in(['primary', 'supporting', 'generated'])]]);
        $documents->link($request->user(), $document, $data['linkable_type'], $data['linkable_id'], $data['role']);
        $this->audit($request, 'document.link.create', $document->id);

        return back()->with('success', 'Document linked.');
    }

    public function storeRetentionPolicy(Request $request): RedirectResponse
    {
        $data = $request->validate(['code' => ['required', 'alpha_dash', 'max:50'], 'name' => ['required', 'string', 'max:150'], 'minimum_retention_days' => ['required', 'integer', 'min:0', 'max:36500'], 'effective_from' => ['required', 'date'], 'legal_hold_required' => ['boolean']]);
        RetentionPolicy::updateOrCreate(['org_id' => $request->user()->org_id, 'code' => $data['code'], 'effective_from' => $data['effective_from']], $data + ['org_id' => $request->user()->org_id, 'legal_hold_required' => $request->boolean('legal_hold_required')]);

        return back()->with('success', 'Retention policy saved.');
    }

    public function storeCategory(Request $request): RedirectResponse
    {
        $orgId = $request->user()->org_id;
        $data = $request->validate(['code' => ['required', 'alpha_dash', 'max:50'], 'name' => ['required', 'string', 'max:150'], 'retention_policy_id' => ['nullable', 'uuid', Rule::exists('retention_policies', 'id')->where('org_id', $orgId)], 'default_sensitivity' => ['required', Rule::in(['org_internal', 'department_restricted', 'finance_confidential', 'hr_confidential', 'executive_confidential'])], 'expiry_tracking_enabled' => ['boolean'], 'default_renewal_alert_days' => ['nullable', 'integer', 'min:1', 'max:365']]);
        DocumentCategory::updateOrCreate(['org_id' => $orgId, 'code' => $data['code']], $data + ['org_id' => $orgId, 'status' => true, 'expiry_tracking_enabled' => $request->boolean('expiry_tracking_enabled')]);

        return back()->with('success', 'Document category saved.');
    }

    public function download(Request $request, DocumentVersion $version): StreamedResponse
    {
        $version->load('document');
        abort_unless($version->org_id === $request->user()->org_id && $version->scan_status === 'clean', 404);
        abort_unless($this->mayAccess($request, $version->document), 403);
        abort_unless(Storage::disk('local')->exists($version->storage_key), 404);
        $this->audit($request, 'document.download', $version->document_id);

        return Storage::disk('local')->download($version->storage_key, $version->original_name);
    }

    private function mayAccess(Request $request, Document $document): bool
    {
        $user = $request->user();
        if (! $user->hasPermissionCode('documents.download')) {
            return false;
        }
        if ($document->owner_user_id === $user->id || in_array($document->sensitivity, ['org_internal', 'department_restricted'], true)) {
            return true;
        }

        return $user->roles()->whereIn('code', ['owner', 'admin', 'finance'])->exists() && $document->sensitivity === 'finance_confidential' || $user->roles()->whereIn('code', ['owner', 'admin'])->exists() && in_array($document->sensitivity, ['hr_confidential', 'executive_confidential'], true);
    }

    private function audit(Request $request, string $action, string $documentId): void
    {
        AuditLog::create(['org_id' => $request->user()->org_id, 'actor_user_id' => $request->user()->id, 'action' => $action, 'entity_type' => 'document', 'entity_id' => $documentId]);
    }
}
