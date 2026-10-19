<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Support\ExternalApiResponse;
use App\Support\ExternalDocumentAccess;
use App\Support\ExternalReadPagination;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class ExternalDocumentController extends Controller
{
    private const FIELDS = ['id', 'document_no', 'title', 'category_id', 'status', 'expires_at', 'current_version_id', 'updated_at'];

    public function index(Request $request)
    {
        $filters = ExternalReadPagination::filters($request);
        $query = ExternalDocumentAccess::query($request)->with('currentVersion:id,version_no,mime_type,size_bytes,scan_status');

        return ExternalReadPagination::page($request, $query, self::FIELDS, $filters, fn ($row) => $this->metadata($row));
    }

    public function show(Request $request, string $id)
    {
        ExternalReadPagination::filters($request, ['limit' => ['prohibited'], 'cursor' => ['prohibited'], 'updated_since' => ['prohibited']]);
        $document = ExternalDocumentAccess::query($request)->with('currentVersion:id,version_no,mime_type,size_bytes,scan_status')->whereKey($id)->firstOrFail(self::FIELDS);

        return response()->json(['data' => $this->metadata($document), 'meta' => ['api_version' => 'v1', 'request_id' => ExternalApiResponse::requestId($request)]]);
    }

    public function download(Request $request, string $id)
    {
        ExternalReadPagination::filters($request, ['limit' => ['prohibited'], 'cursor' => ['prohibited'], 'updated_since' => ['prohibited']]);
        $key = $request->attributes->get('integration_key');
        abort_unless(in_array('documents:read', $key->scopes, true), 403);
        $document = ExternalDocumentAccess::query($request, 'documents:download')->with('currentVersion')->whereKey($id)->firstOrFail();
        $version = $document->currentVersion;
        abort_unless($version && $version->document_id === $document->id && $version->org_id === $key->org_id && $version->scan_status === 'clean', 404);
        abort_unless(Storage::disk('local')->exists($version->storage_key), 404);
        AuditLog::create(['org_id' => $key->org_id, 'actor_user_id' => $key->created_by, 'action' => 'api_document.download',
            'entity_type' => 'document', 'entity_id' => $document->id,
            'after_json' => ['integration_key_id' => $key->id, 'version_id' => $version->id]]);

        return Storage::disk('local')->download($version->storage_key, basename($version->original_name), ['Cache-Control' => 'no-store, private', 'X-Content-Type-Options' => 'nosniff']);
    }

    private function metadata($row): array
    {
        return $row->only(['id', 'document_no', 'title', 'category_id', 'status', 'updated_at']) + [
            'expires_at' => $row->expires_at?->toDateString(),
            'current_version' => $row->currentVersion?->only(['version_no', 'mime_type', 'size_bytes', 'scan_status']),
        ];
    }
}
