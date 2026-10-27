<?php

namespace Pos\Services;

use Endroid\QrCode\QrCode;
use Endroid\QrCode\Writer\SvgWriter;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Pos\Models\Staff;
use Pos\Support\CanonicalJson;

class PublicMenuService
{
    public function guard(): void
    {
        abort_unless(app()->environment(['local', 'testing']) && config('pos.local_public_menu_enabled') === true, 503, 'Local public menu is disabled.');
    }

    public function issue(Staff $staff, int $minutes, ?string $tableSession = null): array
    {
        $this->staff($staff);
        abort_unless($staff->role === 'manager' && $minutes >= 1 && $minutes <= 1440, 403);
        $branch = DB::table('pos_branches')->where('id', $staff->branch_id)->whereIn('business_type', ['retail', 'restaurant'])->where('active', true)->firstOrFail();
        abort_if($branch->business_type === 'restaurant' && config('pos.local_restaurant_enabled') !== true, 503, 'Restaurant capability is disabled.');
        abort_if($tableSession !== null && $branch->business_type !== 'restaurant', 422, 'Table QR requires Restaurant capability.');
        $origin = rtrim(config('app.url'), '/');
        $parts = parse_url($origin);
        abort_unless(is_array($parts) && in_array($parts['scheme'] ?? '', ['http', 'https'], true) && in_array($parts['host'] ?? '', ['localhost', '127.0.0.1', '[::1]'], true) && ! isset($parts['user']) && ! isset($parts['pass']) && ! isset($parts['query']) && ! isset($parts['fragment']) && in_array($parts['path'] ?? '', ['', '/'], true), 503, 'Loopback APP_URL required for local QR.');
        $token = bin2hex(random_bytes(32));
        $id = (string) Str::uuid();
        DB::transaction(function () use ($staff, $branch, $token, $id, $minutes, $tableSession) {
            DB::table('pos_branches')->where('id', $branch->id)->lockForUpdate()->firstOrFail();
            if ($tableSession) {
                DB::table('pos_table_sessions')->where('id', $tableSession)->where('branch_id', $branch->id)->whereNotNull('active_slot')->lockForUpdate()->firstOrFail();
            }
            DB::table('pos_menu_tokens')->insert(['id' => $id, 'branch_id' => $branch->id, 'table_session_id' => $tableSession, 'token_hash' => hash('sha256', $token), 'expires_at' => now()->addMinutes($minutes), 'created_at' => now(), 'updated_at' => now()]);
            $this->audit($staff, 'menu.token.issue', $id);
        });
        $path = '/menu#'.$token;
        $qr = (new SvgWriter)->write(new QrCode(data: $origin.$path, size: 240, margin: 12))->getDataUri();

        return ['id' => $id, 'token' => $token, 'menu_path' => $path, 'qr_data_uri' => $qr];
    }

    public function revoke(Staff $staff, string $id): void
    {
        $this->staff($staff);
        abort_unless($staff->role === 'manager', 403);
        DB::transaction(function () use ($staff, $id) {
            DB::table('pos_menu_tokens')->where('id', $id)->where('branch_id', $staff->branch_id)->lockForUpdate()->firstOrFail();
            DB::table('pos_menu_tokens')->where('id', $id)->update(['revoked_at' => now(), 'updated_at' => now()]);
            $this->audit($staff, 'menu.token.revoke', $id);
        });
    }

    public function catalog(string $token): array
    {
        $t = $this->token($token);
        [$catalog, $items] = $this->published($t->branch_id);

        return ['version' => $catalog->version, 'items' => array_map(fn ($p) => array_intersect_key($p, array_flip(['product_id', 'name', 'barcode', 'unit', 'price', 'tax_rate'])), array_values($items))];
    }

    public function submit(string $token, string $key, array $input): array
    {
        $this->guard();
        validator(['key' => $key, 'items' => $input], ['key' => 'required|string|max:100', 'items' => 'required|array|min:1|max:100', 'items.*.product_id' => 'required|uuid|distinct', 'items.*.quantity' => ['required', 'string', 'regex:/^\d{1,10}(\.\d{1,4})?$/D']])->validate();

        return DB::transaction(function () use ($token, $key, $input) {
            $t = $this->token($token, true);
            [$catalog, $published] = $this->published($t->branch_id);
            $items = $this->normalize($input, $published);
            $hash = hash('sha256', CanonicalJson::encode($items));
            $old = DB::table('pos_customer_requests')->where('token_id', $t->id)->where('request_key', $key)->first();
            if ($old) {
                abort_unless(hash_equals($old->request_hash, $hash), 409, 'Customer request identity conflict.');

                return ['id' => $old->id, 'status' => $old->status];
            }
            $id = (string) Str::uuid();
            DB::table('pos_customer_requests')->insert(['id' => $id, 'branch_id' => $t->branch_id, 'token_id' => $t->id, 'request_key' => $key, 'request_hash' => $hash, 'items' => CanonicalJson::encode($items), 'catalog_version' => $catalog->version, 'status' => 'pending', 'created_at' => now(), 'updated_at' => now()]);

            return ['id' => $id, 'status' => 'pending'];
        });
    }

    public function accept(Staff $staff, string $id): array
    {
        $this->staff($staff);

        return DB::transaction(function () use ($staff, $id) {
            $row = DB::table('pos_customer_requests')->where('id', $id)->where('branch_id', $staff->branch_id)->lockForUpdate()->firstOrFail();
            abort_unless(in_array($row->status, ['pending', 'accepted'], true) && $row->sale_id === null, 409, 'Customer request is not reviewable.');
            [$catalog, $published] = $this->published($row->branch_id);
            $items = $this->normalize(json_decode($row->items, true), $published);
            DB::table('pos_customer_requests')->where('id', $id)->update(['status' => 'accepted', 'accepted_by' => $staff->id, 'items' => CanonicalJson::encode($items), 'catalog_version' => $catalog->version, 'updated_at' => now()]);
            $this->audit($staff, 'menu.request.accept', $id);

            return ['id' => $id, 'status' => 'accepted'];
        });
    }

    public function reject(Staff $staff, string $id): void
    {
        $this->staff($staff);
        DB::transaction(function () use ($staff, $id) {
            $row = DB::table('pos_customer_requests')->where('id', $id)->where('branch_id', $staff->branch_id)->lockForUpdate()->firstOrFail();
            if ($row->status === 'rejected') {
                return;
            }
            abort_unless(in_array($row->status, ['pending', 'accepted'], true) && $row->sale_id === null, 409, 'Completed customer request cannot be rejected.');
            DB::table('pos_customer_requests')->where('id', $id)->update(['status' => 'rejected', 'updated_at' => now()]);
            $this->audit($staff, 'menu.request.reject', $id);
        });
    }

    public function claim(Staff $staff, string $id, string $catalogVersion, array $input): void
    {
        $this->staff($staff);
        abort_unless(DB::transactionLevel() > 0, 500);
        $row = DB::table('pos_customer_requests')->where('id', $id)->where('branch_id', $staff->branch_id)->lockForUpdate()->firstOrFail();
        abort_unless($row->status === 'accepted' && $row->sale_id === null && $row->catalog_version === $catalogVersion, 409, 'Customer request is unavailable for checkout.');
        [$catalog, $published] = $this->published($row->branch_id);
        abort_unless($catalog->version === $catalogVersion && CanonicalJson::encode($this->normalize($input, $published)) === CanonicalJson::encode(json_decode($row->items, true)), 409, 'Accepted customer request changed; staff review required.');
    }

    public function status(string $token, string $id): array
    {
        $t = $this->token($token);
        $row = DB::table('pos_customer_requests')->where('id', $id)->where('token_id', $t->id)->firstOrFail();

        return ['id' => $row->id, 'status' => $row->status];
    }

    private function token(string $token, bool $lock = false): object
    {
        $this->guard();
        abort_unless(preg_match('/^[a-f0-9]{64}$/D', $token), 404);
        $query = DB::table('pos_menu_tokens')->where('token_hash', hash('sha256', $token))->whereNull('revoked_at')->where('expires_at', '>', now());

        $row = ($lock ? $query->lockForUpdate() : $query)->firstOrFail();
        if ($row->table_session_id) {
            DB::table('pos_table_sessions')->where('id', $row->table_session_id)->where('branch_id', $row->branch_id)->whereNotNull('active_slot')->firstOrFail();
        }

        return $row;
    }

    private function published(string $branch): array
    {
        $b = DB::table('pos_branches')->where('id', $branch)->whereIn('business_type', ['retail', 'restaurant'])->where('active', true)->firstOrFail();
        abort_if($b->business_type === 'restaurant' && config('pos.local_restaurant_enabled') !== true, 503, 'Restaurant capability is disabled.');
        $q = DB::table('pos_catalogs')->where('branch_id', $branch)->where('valid_until', '>', now());
        $catalog = ($b->active_catalog_version ? $q->where('version', $b->active_catalog_version) : $q->latest('created_at'))->firstOrFail();
        $items = collect(json_decode($catalog->items, true))->filter(fn ($p) => ($p['active'] ?? false) && ($p['online_visible'] ?? false) && ! ($p['pharmacy']['requires_pharmacist'] ?? false))->keyBy('product_id')->all();

        return [$catalog, $items];
    }

    private function normalize(array $items, array $published): array
    {
        $result = [];
        foreach ($items as $item) {
            $p = $published[$item['product_id']] ?? null;
            if (isset($p['restaurant'])) {
                abort_unless(bccomp(bcmod($item['quantity'], '1', 4), '0', 4) === 0 && bccomp($item['quantity'], '50', 4) <= 0, 422, 'Restaurant requests require whole portions up to 50.');
            }
            abort_unless($p && bccomp($item['quantity'], '0', 4) > 0 && (! isset($item['unit']) || $item['unit'] === $p['unit']), 422, 'Public product unavailable.');
            $result[] = ['product_id' => $p['product_id'], 'quantity' => bcadd($item['quantity'], '0', 4), 'unit' => $p['unit']];
        }
        usort($result, fn ($a, $b) => strcmp($a['product_id'], $b['product_id']));

        return $result;
    }

    private function staff(Staff $staff): void
    {
        $this->guard();
        $current = Staff::findOrFail($staff->id);
        abort_unless($current->active && $current->branch_id === $staff->branch_id && $current->role === $staff->role && in_array($current->role, ['cashier', 'manager'], true), 403);
    }

    private function audit(Staff $staff, string $action, string $id): void
    {
        DB::table('pos_audit')->insert(['actor_id' => $staff->id, 'branch_id' => $staff->branch_id, 'action' => $action, 'source_id' => $id, 'metadata' => '{}', 'created_at' => now()]);
    }
}
