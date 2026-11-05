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
        $validOrigin = is_array($parts)
            && ! isset($parts['user']) && ! isset($parts['pass']) && ! isset($parts['query']) && ! isset($parts['fragment'])
            && in_array($parts['path'] ?? '', ['', '/'], true);
        $loopback = in_array($parts['scheme'] ?? '', ['http', 'https'], true)
            && in_array($parts['host'] ?? '', ['localhost', '127.0.0.1', '[::1]'], true);
        $privateLan = $branch->business_type === 'restaurant' && $this->allowsPrivateLan($origin, $parts['host'] ?? '');
        abort_unless($validOrigin && ($loopback || $privateLan), 503, 'Use a loopback or an explicitly configured private HTTPS origin.');
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

    public function allowsPrivateLan(string $origin, string $client): bool
    {
        if (config('pos.local_menu_private_lan_enabled') !== true || $origin !== rtrim(config('app.url'), '/')) {
            return false;
        }
        $host = parse_url($origin, PHP_URL_HOST);

        return parse_url($origin, PHP_URL_SCHEME) === 'https'
            && is_string($host) && $this->isPrivateIpv4($host) && $this->isPrivateIpv4($client);
    }

    private function isPrivateIpv4(string $address): bool
    {
        return filter_var($address, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) !== false
            && preg_match('/^(10\.|192\.168\.|172\.(1[6-9]|2[0-9]|3[01])\.)/', $address) === 1;
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
        [$catalog, $items, $branch] = $this->published($t->branch_id);
        $restaurant = $branch->business_type === 'restaurant';
        $stock = $restaurant ? $this->ingredientAvailability($branch, $items) : [];
        $products = array_map(function ($product) use ($restaurant, $stock, $items) {
            $visible = array_intersect_key($product, array_flip(['product_id', 'name', 'barcode', 'unit', 'price', 'tax_rate', 'restaurant_variant']));
            if ($restaurant) {
                $visible['available'] = $this->canPrepare([['product_id' => $product['product_id'], 'quantity' => '1.0000']], $items, $stock);
            }

            return $visible;
        }, array_values($items));

        return ['version' => $catalog->version, 'items' => $products] + ($restaurant ? ['restaurant' => true] : []);
    }

    public function submit(string $token, string $key, array $input, ?string $version = null): array
    {
        $this->guard();
        validator(['key' => $key, 'items' => $input], ['key' => 'required|string|max:100', 'items' => 'required|array|min:1|max:100', 'items.*.product_id' => 'required|uuid|distinct', 'items.*.quantity' => ['required', 'string', 'regex:/^\d{1,10}(\.\d{1,4})?$/D']])->validate();

        return DB::transaction(function () use ($token, $key, $input, $version) {
            $scope = $this->token($token);
            DB::table('pos_branches')->where('id', $scope->branch_id)->lockForUpdate()->firstOrFail();
            $t = $this->token($token, true);
            $old = DB::table('pos_customer_requests')->where('token_id', $t->id)->where('request_key', $key)->first();
            if ($old) {
                $previous = array_column(json_decode($old->items, true), null, 'product_id');
                $replayed = $this->normalize($input, $previous);
                abort_unless(hash_equals($old->request_hash, hash('sha256', CanonicalJson::encode($replayed))), 409, 'Customer request identity conflict.');

                return ['id' => $old->id, 'status' => $old->status];
            }
            [$catalog, $published, $branch] = $this->published($t->branch_id);
            abort_if($version !== null && $catalog->version !== $version, 409, 'Menu changed. Reload and review the current prices.');
            $items = $this->normalize($input, $published);
            if ($branch->business_type === 'restaurant') {
                abort_unless(array_sum(array_column($items, 'quantity')) <= 100 && count($items) <= 50, 422, 'Restaurant order is too large.');
                $requested = array_intersect_key($published, array_flip(array_column($items, 'product_id')));
                abort_unless($this->canPrepare($items, $requested, $this->ingredientAvailability($branch, $requested)), 422, 'Some dishes are unavailable.');
            }
            $hash = hash('sha256', CanonicalJson::encode($items));
            $id = (string) Str::uuid();
            DB::table('pos_customer_requests')->insert(['id' => $id, 'branch_id' => $t->branch_id, 'token_id' => $t->id, 'request_key' => $key, 'request_hash' => $hash, 'items' => CanonicalJson::encode($items), 'catalog_version' => $catalog->version, 'status' => 'pending', 'created_at' => now(), 'updated_at' => now()]);

            return ['id' => $id, 'status' => 'pending'];
        }, 3);
    }

    public function accept(Staff $staff, string $id): array
    {
        $this->staff($staff);

        return DB::transaction(function () use ($staff, $id) {
            $branch = DB::table('pos_branches')->where('id', $staff->branch_id)->lockForUpdate()->firstOrFail();
            $row = DB::table('pos_customer_requests')->where('id', $id)->where('branch_id', $staff->branch_id)->lockForUpdate()->firstOrFail();
            abort_unless(in_array($row->status, ['pending', 'accepted'], true) && $row->sale_id === null, 409, 'Customer request is not reviewable.');
            [$catalog, $published] = $this->published($row->branch_id);
            if ($branch->business_type === 'restaurant') {
                $this->activeRequestToken($row);
                abort_unless($catalog->version === $row->catalog_version, 409, 'Menu changed. Ask the customer to reload and submit again.');
            }
            $items = $this->normalize(json_decode($row->items, true), $published);
            DB::table('pos_customer_requests')->where('id', $id)->update(['status' => 'accepted', 'accepted_by' => $staff->id, 'items' => CanonicalJson::encode($items), 'catalog_version' => $catalog->version, 'updated_at' => now()]);
            $this->audit($staff, 'menu.request.accept', $id);

            return ['id' => $id, 'status' => 'accepted'];
        }, 3);
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

        $status = ['id' => $row->id, 'status' => $row->status];
        if ($row->restaurant_order_id === null) {
            if (! in_array($row->status, ['pending', 'accepted'], true)) {
                return $status;
            }
            $isRestaurant = DB::table('pos_branches')->where('id', $row->branch_id)->where('business_type', 'restaurant')->exists();
            if (! $isRestaurant) {
                return $status;
            }
            [$catalog, $items, $branch] = $this->published($row->branch_id);
            if ($catalog->version !== $row->catalog_version) {
                return $status + ['review' => 'menu_changed'];
            }
            $lines = json_decode($row->items, true);
            $requested = array_intersect_key($items, array_flip(array_column($lines, 'product_id')));
            if (! $this->canPrepare($lines, $requested, $this->ingredientAvailability($branch, $requested))) {
                return $status + ['review' => 'unavailable'];
            }

            return $status;
        }
        $queueNumber = DB::table('pos_kitchen_orders')->where('id', $row->restaurant_order_id)->value('queue_number');
        $progress = DB::table('pos_kitchen_lines')->where('order_id', $row->restaurant_order_id)
            ->selectRaw('status, COUNT(*) AS portions')->groupBy('status')->pluck('portions', 'status')->all();

        return $status + ['queue_number' => $queueNumber === null ? null : (int) $queueNumber, 'progress' => $progress];
    }

    public function activeRequestToken(object $request): object
    {
        $token = DB::table('pos_menu_tokens')->where('id', $request->token_id)->where('branch_id', $request->branch_id)
            ->whereNull('revoked_at')->where('expires_at', '>', now())->lockForUpdate()->firstOrFail();
        if ($token->table_session_id !== null) {
            DB::table('pos_table_sessions')->where('id', $token->table_session_id)->where('branch_id', $request->branch_id)
                ->whereNotNull('active_slot')->lockForUpdate()->firstOrFail();
        }

        return $token;
    }

    private function token(string $token, bool $lock = false): object
    {
        $this->guard();
        abort_unless(preg_match('/^[a-f0-9]{64}$/D', $token), 404);
        $query = DB::table('pos_menu_tokens')->where('token_hash', hash('sha256', $token))->whereNull('revoked_at')->where('expires_at', '>', now());

        $row = ($lock ? $query->lockForUpdate() : $query)->firstOrFail();
        if ($row->table_session_id) {
            $session = DB::table('pos_table_sessions')->where('id', $row->table_session_id)->where('branch_id', $row->branch_id)->whereNotNull('active_slot');
            ($lock ? $session->lockForUpdate() : $session)->firstOrFail();
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

        if ($b->business_type === 'restaurant') {
            $items = array_filter($items, fn ($p) => isset($p['restaurant']));
        }

        return [$catalog, $items, $b];
    }

    private function ingredientAvailability(object $branch, array $products): array
    {
        $ingredients = [];
        foreach ($products as $product) {
            foreach ($product['restaurant']['ingredients'] as $ingredient) {
                $ingredients[$ingredient['product_id']] = true;
            }
        }
        if ($ingredients === []) {
            return [];
        }

        $grants = DB::table('pos_grants as g')->where('g.branch_id', $branch->id)->where('g.status', 'active')
            ->when(count($ingredients) <= 500, fn ($query) => $query->whereIn('g.product_id', array_keys($ingredients)))
            ->where(fn ($q) => $q->whereNull('g.expires_at')->orWhere('g.expires_at', '>=', now($branch->timezone)->toDateString()))
            ->whereNotExists(fn ($q) => $q->selectRaw('1')->from('pos_lot_recalls as r')->whereColumn('r.branch_id', 'g.branch_id')->whereColumn('r.lot_id', 'g.lot_id'))
            ->get(['product_id', 'granted_quantity', 'consumed_quantity', 'reserved_quantity']);
        $stock = [];
        foreach ($grants as $grant) {
            $free = bcsub(bcsub((string) $grant->granted_quantity, (string) $grant->consumed_quantity, 4), (string) $grant->reserved_quantity, 4);
            if (bccomp($free, '0', 4) > 0) {
                $stock[$grant->product_id] = bcadd($stock[$grant->product_id] ?? '0', $free, 4);
            }
        }

        return $stock;
    }

    private function canPrepare(array $lines, array $products, array $stock): bool
    {
        foreach ($lines as $line) {
            $ingredients = $products[$line['product_id']]['restaurant']['ingredients'] ?? null;
            if ($ingredients === null) {
                return false;
            }
            foreach ($ingredients as $ingredient) {
                $id = $ingredient['product_id'];
                $stock[$id] = bcsub($stock[$id] ?? '0', bcmul($ingredient['quantity'], $line['quantity'], 4), 4);
                if (bccomp($stock[$id], '0', 4) < 0) {
                    return false;
                }
            }
        }

        return true;
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
