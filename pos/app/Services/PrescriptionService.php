<?php

namespace Pos\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Pos\Models\Staff;
use Pos\Support\LocalFeatureGate;

/** Local synthetic fixtures only; real documents are not authorized by this implementation. */
class PrescriptionService
{
    private const MIME = ['application/pdf' => 'pdf', 'image/jpeg' => 'jpg', 'image/png' => 'png'];

    public function upload(Staff $staff, UploadedFile $file): array
    {
        $this->pharmacist($staff);
        $days = config('pos.prescription_retention_days');
        abort_unless(is_int($days) && $days > 0 && $days <= 3650, 503, 'Prescription fixture retention is not configured.');
        abort_unless($file->isValid() && $file->getSize() > 0 && $file->getSize() <= 5242880 && isset(self::MIME[$file->getMimeType()]), 422, 'Unsupported prescription attachment.');
        $hash = hash_file('sha256', $file->getRealPath());
        abort_unless(in_array($hash, config('pos.synthetic_prescription_hashes', []), true), 422, 'Only explicitly allowlisted synthetic fixtures may be uploaded.');
        $id = (string) Str::uuid();
        $path = $file->storeAs('quarantine/'.$staff->branch_id, $id.'.bin', 'prescription_fixtures');
        if (! $path) {
            throw new \RuntimeException('Prescription fixture storage failed.');
        }
        try {
            DB::transaction(function () use ($staff, $file, $id, $path, $days, $hash) {
                DB::table('pos_prescriptions')->insert(['id' => $id, 'branch_id' => $staff->branch_id, 'uploaded_by' => $staff->id, 'private_path' => $path, 'mime' => $file->getMimeType(), 'sha256' => $hash, 'size' => $file->getSize(), 'status' => 'quarantined', 'retained_until' => now()->addDays($days), 'created_at' => now(), 'updated_at' => now()]);
                $this->audit($staff, 'prescription.synthetic_upload', $id);
            });
        } catch (\Throwable $e) {
            Storage::disk('prescription_fixtures')->delete($path);
            throw $e;
        }

        return ['id' => $id, 'status' => 'quarantined'];
    }

    public function scan(string $id): string
    {
        LocalFeatureGate::ensure();
        $row = DB::table('pos_prescriptions')->where('id', $id)->firstOrFail();
        abort_unless($row->status === 'quarantined', 409, 'Prescription fixture is not in quarantine.');
        $path = $this->verifiedPath($row);
        $clean = app(PrescriptionScanner::class)->isClean($path);
        DB::transaction(function () use ($id, $row, $clean) {
            DB::table('pos_prescriptions')->where('id', $id)->lockForUpdate()->firstOrFail();
            DB::table('pos_prescriptions')->where('id', $id)->where('status', 'quarantined')->update(['status' => $clean ? 'clean' : 'rejected', 'updated_at' => now()]);
            DB::table('pos_audit')->insert(['actor_id' => null, 'branch_id' => $row->branch_id, 'action' => 'prescription.synthetic_scan', 'source_id' => $id, 'metadata' => json_encode(['clean' => $clean]), 'created_at' => now()]);
        });

        return $clean ? 'clean' : 'rejected';
    }

    public function attach(Staff $staff, array $ids, string $orderId): void
    {
        if ($ids === []) {
            return;
        }
        $this->pharmacist($staff);
        DB::transaction(function () use ($staff, $ids, $orderId) {
            $order = DB::table('pos_orders')->where('id', $orderId)->where('branch_id', $staff->branch_id)->lockForUpdate()->firstOrFail();
            $ids = array_values(array_unique($ids));
            sort($ids, SORT_STRING);
            foreach ($ids as $id) {
                $row = DB::table('pos_prescriptions')->where('id', $id)->where('branch_id', $order->branch_id)->lockForUpdate()->firstOrFail();
                abort_unless($row->status === 'clean' && ($row->order_id === null || $row->order_id === $orderId) && ($row->legal_hold || now()->lt($row->retained_until)), 422, 'Prescription fixture is not attachable.');
                $this->verifiedPath($row);
                DB::table('pos_prescriptions')->where('id', $id)->update(['order_id' => $orderId, 'updated_at' => now()]);
                $this->audit($staff, 'prescription.synthetic_attach', $id);
            }
        });
    }

    public function download(Staff $staff, string $id)
    {
        $this->pharmacist($staff);
        $row = DB::table('pos_prescriptions')->where('id', $id)->where('branch_id', $staff->branch_id)->firstOrFail();
        abort_unless($row->status === 'clean' && ($row->legal_hold || now()->lt($row->retained_until)), 403, 'Prescription fixture download unavailable.');
        $this->verifiedPath($row);
        $this->audit($staff, 'prescription.synthetic_download', $id);

        return Storage::disk('prescription_fixtures')->download($row->private_path, 'synthetic-prescription-'.$row->id.'.'.self::MIME[$row->mime], ['Content-Type' => $row->mime, 'Cache-Control' => 'private, no-store', 'X-Content-Type-Options' => 'nosniff', 'Content-Security-Policy' => 'sandbox']);
    }

    private function verifiedPath(object $row): string
    {
        $expected = 'quarantine/'.$row->branch_id.'/'.$row->id.'.bin';
        abort_unless($row->private_path === $expected && isset(self::MIME[$row->mime]), 409, 'Prescription fixture storage identity changed.');
        $path = Storage::disk('prescription_fixtures')->path($expected);
        abort_unless(is_file($path) && hash_equals($row->sha256, hash_file('sha256', $path)), 409, 'Prescription fixture evidence changed.');

        return $path;
    }

    private function pharmacist(Staff $staff): void
    {
        LocalFeatureGate::ensure();
        abort_unless($staff->active && $staff->role === 'pharmacist' && DB::table('pos_branches')->where('id', $staff->branch_id)->where('business_type', 'pharmacy')->where('active', true)->exists(), 403, 'Assigned pharmacist required.');
    }

    private function audit(Staff $staff, string $action, string $id): void
    {
        DB::table('pos_audit')->insert(['actor_id' => $staff->id, 'branch_id' => $staff->branch_id, 'action' => $action, 'source_id' => $id, 'metadata' => '{}', 'created_at' => now()]);
    }
}
