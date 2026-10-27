<?php

namespace Pos\Services;

use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Pos\Support\CanonicalJson;

class OutboxArchiveStore
{
    public function store(object $row): array
    {
        abort_unless(Str::isUuid($row->event_id), 422);
        $path = $row->event_id.'.gz.enc';
        $hash = hash('sha256', CanonicalJson::encode(json_decode($row->envelope, true, flags: JSON_THROW_ON_ERROR)));
        $disk = Storage::disk('pos_archives');
        if (! $disk->exists($path)) {
            $compressed = gzencode($row->envelope, 9);
            if ($compressed === false) {
                throw new \RuntimeException('Archive compression failed.');
            }
            $disk->put($path, Crypt::encryptString($compressed));
        }
        $metadata = ['envelope' => '', 'archive_path' => $path, 'envelope_hash' => $hash];
        $stream = fopen($disk->path($path), 'r+b');
        if (! $stream) {
            throw new \RuntimeException('Archive durability check failed.');
        }
        try {
            if (! fflush($stream) || (function_exists('fsync') && ! fsync($stream))) {
                throw new \RuntimeException('Archive fsync failed.');
            }
        } finally {
            fclose($stream);
        }
        $check = (object) array_merge((array) $row, $metadata);
        abort_unless($this->envelope($check) === $row->envelope, 409, 'Private archive verification failed.');

        return $metadata;
    }

    public function envelope(object $row): string
    {
        if ($row->envelope !== '') {
            return $row->envelope;
        }
        abort_unless(isset($row->event_id, $row->archive_path, $row->envelope_hash) && Str::isUuid($row->event_id) && $row->archive_path === $row->event_id.'.gz.enc', 409, 'Archive metadata is incomplete.');
        $bytes = Storage::disk('pos_archives')->get($row->archive_path);
        abort_if(strlen($bytes) > 1048576, 413, 'Archive exceeds size limit.');
        $plain = gzdecode(Crypt::decryptString($bytes), 262144);
        abort_unless(is_string($plain), 409, 'Archive could not be decoded.');
        $event = json_decode($plain, true, 64, JSON_THROW_ON_ERROR);
        abort_unless(hash_equals($row->envelope_hash, hash('sha256', CanonicalJson::encode($event))) && $event['event_id'] === $row->event_id && $event['source_sequence'] === (int) $row->sequence && $event['event_type'] === $row->event_type && $event['payload']['branch_id'] === $row->branch_id, 409, 'Archive integrity conflict.');

        return $plain;
    }
}
