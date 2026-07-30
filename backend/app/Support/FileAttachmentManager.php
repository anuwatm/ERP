<?php

namespace App\Support;

use App\Models\AuditLog;
use App\Models\StoredFile;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class FileAttachmentManager
{
    public const MAX_KILOBYTES = 5120;

    public const MIMES = ['pdf', 'jpg', 'jpeg', 'png', 'webp'];

    public function store(Request $request, UploadedFile $upload, string $entityType, string $entityId, string $category = 'receipt'): StoredFile
    {
        abort_unless(in_array($entityType, ['payment', 'expense'], true), 422, 'Invalid file entity type.');

        $extension = strtolower($upload->extension() ?: $upload->getClientOriginalExtension());
        abort_unless(in_array($extension, self::MIMES, true), 422, 'Invalid attachment file type.');

        $mimeTypes = [
            'pdf' => ['application/pdf'],
            'jpg' => ['image/jpeg'],
            'jpeg' => ['image/jpeg'],
            'png' => ['image/png'],
            'webp' => ['image/webp'],
        ];
        abort_unless(in_array($upload->getMimeType(), $mimeTypes[$extension] ?? [], true), 422, 'Invalid attachment MIME type.');

        $now = now();
        $storageKey = sprintf(
            'tenants/%s/%s/%s/%s.%s',
            $request->user()->org_id,
            $now->format('Y'),
            $now->format('m'),
            (string) Str::uuid(),
            $extension
        );

        Storage::disk('local')->put($storageKey, file_get_contents($upload->getRealPath()));

        $file = StoredFile::create([
            'org_id' => $request->user()->org_id,
            'storage_key' => $storageKey,
            'file_name' => basename($upload->getClientOriginalName()),
            'mime_type' => $upload->getMimeType(),
            'size_bytes' => $upload->getSize(),
            'category' => $category,
            'entity_type' => $entityType,
            'entity_id' => $entityId,
            'uploaded_by' => $request->user()->id,
        ]);

        AuditLog::create([
            'org_id' => $file->org_id,
            'actor_user_id' => $request->user()->id,
            'action' => 'file.upload',
            'entity_type' => 'file',
            'entity_id' => $file->id,
            'before_json' => null,
            'after_json' => $file->only(['storage_key', 'file_name', 'mime_type', 'size_bytes', 'category', 'entity_type', 'entity_id']),
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
        ]);

        return $file;
    }

    public function delete(?StoredFile $file): void
    {
        if (! $file) {
            return;
        }

        Storage::disk('local')->delete($file->storage_key);
        $file->delete();
    }
}
