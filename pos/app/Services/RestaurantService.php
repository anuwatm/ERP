<?php

namespace Pos\Services;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Pos\Models\Staff;
use Pos\Support\CanonicalJson;

class RestaurantService
{
    public function fromCustomerRequest(Staff $staff, string $id, string $terminalId): array
    {
        return DB::transaction(function () use ($staff, $id, $terminalId) {
            $branch = $this->actor($staff, ['cashier', 'manager'], $terminalId);
            $row = DB::table('pos_customer_requests')->where('id', $id)->where('branch_id', $branch->id)->lockForUpdate()->firstOrFail();
            if ($row->restaurant_order_id) {
                $old = DB::table('pos_kitchen_orders')->where('id', $row->restaurant_order_id)->where('branch_id', $branch->id)->firstOrFail();
                abort_unless($old->terminal_id === $terminalId, 409, 'Customer request already assigned to another terminal.');

                return json_decode($old->snapshot, true);
            }
            $menu = app(PublicMenuService::class);
            if ($row->status === 'pending') {
                $menu->accept($staff, $id);
            }
            $items = json_decode($row->items, true);
            $menu->claim($staff, $id, $row->catalog_version, $items);
            $token = $menu->activeRequestToken($row);
            $requested = [];
            foreach ($items as $item) {
                abort_unless(bccomp(bcmod($item['quantity'], '1', 4), '0', 4) === 0 && bccomp($item['quantity'], '50', 4) <= 0, 422);
                $requested[] = ['product_id' => $item['product_id'], 'quantity' => (int) $item['quantity']];
            }
            $result = $this->createOrder($staff, ['terminal_id' => $terminalId, 'catalog_version' => $row->catalog_version, 'request_key' => 'menu-'.$id, 'channel' => $token->table_session_id ? 'dine_in' : 'takeaway', 'table_session_id' => $token->table_session_id, 'items' => $requested]);
            DB::table('pos_kitchen_lines')->where('order_id', $result['order_id'])->where('status', 'reserved')
                ->update(['status' => 'sent', 'updated_at' => now()]);
            DB::table('pos_customer_requests')->where('id', $id)->update(['restaurant_order_id' => $result['order_id'], 'status' => 'ordered', 'updated_at' => now()]);
            $this->audit($staff, 'restaurant.customer_request.claim', $id);

            return $result;
        }, 3);
    }

    private function refreshCustomerRequest(string $orderId): void
    {
        $rows = DB::table('pos_kitchen_lines')->where('order_id', $orderId)->get(['status', 'billed_sale_id']);
        if ($rows->contains(fn ($line) => $line->billed_sale_id === null && ! in_array($line->status, ['cancelled', 'wasted'], true))) {
            return;
        }
        DB::table('pos_customer_requests')->where('restaurant_order_id', $orderId)->update(['status' => $rows->contains(fn ($line) => $line->billed_sale_id !== null) ? 'completed' : 'cancelled', 'updated_at' => now()]);
    }

    private function guard(): void
    {
        abort_unless(app()->environment(['local', 'testing']) && config('pos.local_restaurant_enabled') === true, 503, 'Local Restaurant disabled.');
    }

    private function actor(Staff $staff, array $roles, ?string $terminal = null): object
    {
        $this->guard();
        $current = Staff::where('id', $staff->id)->lockForUpdate()->firstOrFail();
        abort_unless($current->active && $current->branch_id === $staff->branch_id && $current->role === $staff->role && in_array($current->role, $roles, true), 403);
        if ($terminal) {
            DB::table('pos_terminals')->where('id', $terminal)->where('branch_id', $staff->branch_id)->where('active', true)->lockForUpdate()->firstOrFail();
        }

        return DB::table('pos_branches')->where('id', $staff->branch_id)->where('business_type', 'restaurant')->where('active', true)->lockForUpdate()->firstOrFail();
    }

    public function createTable(Staff $staff, string $label, int $capacity = 4): string
    {
        validator(['label' => $label, 'capacity' => $capacity], ['label' => 'required|string|max:60', 'capacity' => 'required|integer|min:1|max:100'])->validate();

        return DB::transaction(function () use ($staff, $label, $capacity) {
            $this->actor($staff, ['manager']);
            $id = (string) Str::uuid();
            DB::table('pos_restaurant_tables')->insert(['id' => $id, 'branch_id' => $staff->branch_id, 'label' => $label, 'capacity' => $capacity, 'active' => true, 'created_at' => now(), 'updated_at' => now()]);
            $this->audit($staff, 'restaurant.table.create', $id);

            return $id;
        }, 3);
    }

    public function openTable(Staff $staff, string $tableId): string
    {
        return DB::transaction(function () use ($staff, $tableId) {
            $this->actor($staff, ['cashier', 'manager']);
            $table = $this->seatingTable($staff, $tableId);
            $old = DB::table('pos_table_sessions')->where('table_id', $tableId)->whereNotNull('active_slot')->first();
            if ($old) {
                return $old->id;
            }
            $this->checkWalkInTable($staff, $table, null);

            return $this->startTableSession($staff, $tableId);
        }, 3);
    }

    public function seating(Staff $staff): array
    {
        return DB::transaction(function () use ($staff) {
            $this->actor($staff, ['cashier', 'manager']);

            return DB::table('pos_seating_entries')->where('branch_id', $staff->branch_id)
                ->whereIn('status', ['waiting', 'called', 'confirmed', 'arrived'])
                ->orderBy('queue_number')->get()->map(fn ($entry) => [
                    'id' => $entry->id,
                    'number' => (int) $entry->queue_number,
                    'kind' => $entry->kind,
                    'status' => $entry->status,
                    'party_size' => (int) $entry->party_size,
                    'table_id' => $entry->table_id,
                    'booked_at' => $entry->booked_at === null ? null : Carbon::parse($entry->booked_at, 'UTC')->toIso8601String(),
                    'joined_at' => Carbon::parse($entry->created_at, 'UTC')->toIso8601String(),
                    'contact' => $entry->contact === null ? null : Crypt::decryptString($entry->contact),
                ])->all();
        });
    }

    public function addSeating(Staff $staff, array $input): array
    {
        $data = validator($input, [
            'request_key' => 'required|uuid',
            'kind' => 'required|in:walk_in,reservation',
            'party_size' => 'required|integer|min:1|max:100',
            'table_id' => 'required_if:kind,reservation|prohibited_if:kind,walk_in|nullable|uuid',
            'booked_at' => 'required_if:kind,reservation|prohibited_if:kind,walk_in|nullable|date_format:Y-m-d\TH:i:s.v\Z',
            'contact' => 'required_if:kind,reservation|prohibited_if:kind,walk_in|nullable|string|max:160',
        ])->validate();
        $data['party_size'] = (int) $data['party_size'];
        $hash = hash_hmac('sha256', CanonicalJson::encode($data), (string) config('app.key'));

        return DB::transaction(function () use ($staff, $data, $hash) {
            $branch = $this->actor($staff, ['cashier', 'manager']);
            $entries = DB::table('pos_seating_entries')->where('branch_id', $branch->id);
            $old = (clone $entries)->where('request_key', $data['request_key'])->first();
            if ($old) {
                abort_unless(hash_equals($old->request_hash, $hash), 409, 'Seating request changed.');

                return ['id' => $old->id, 'number' => (int) $old->queue_number];
            }
            $booked = null;
            $tableId = $data['table_id'] ?? null;
            if ($data['kind'] === 'reservation') {
                $booked = Carbon::parse($data['booked_at'])->utc();
                abort_if($booked->lte(now()), 422, 'Reservation time must be in the future.');
                $table = $this->seatingTable($staff, $tableId);
                abort_if($table->capacity < $data['party_size'], 409, 'Table capacity is too small.');
                $conflict = (clone $entries)->where('table_id', $tableId)
                    ->whereIn('status', ['confirmed', 'arrived'])
                    ->where('booked_at', '>', $booked->copy()->subMinutes(self::TABLE_WINDOW))
                    ->where('booked_at', '<', $booked->copy()->addMinutes(self::TABLE_WINDOW))->exists();
                abort_if($conflict, 409, 'Table already has a reservation in this time window.');
            } else {
                abort_unless(DB::table('pos_restaurant_tables')->where('branch_id', $branch->id)
                    ->where('active', true)->where('capacity', '>=', $data['party_size'])->exists(), 422, 'No table can seat this party.');
            }

            $last = (clone $entries)->orderByDesc('queue_number')->lockForUpdate()->value('queue_number');
            $id = (string) Str::uuid();
            $number = (int) $last + 1;
            DB::table('pos_seating_entries')->insert([
                'id' => $id, 'branch_id' => $branch->id, 'table_id' => $tableId, 'request_key' => $data['request_key'],
                'request_hash' => $hash, 'queue_number' => $number, 'kind' => $data['kind'],
                'status' => $booked ? 'confirmed' : 'waiting', 'party_size' => $data['party_size'], 'booked_at' => $booked,
                'contact' => $booked ? Crypt::encryptString($data['contact']) : null, 'created_at' => now(), 'updated_at' => now(),
            ]);
            $this->audit($staff, 'restaurant.seating.create', $id, ['kind' => $data['kind'], 'number' => $number]);

            return ['id' => $id, 'number' => $number];
        }, 3);
    }

    public function changeSeating(Staff $staff, string $id, string $action, ?string $tableId): array
    {
        return DB::transaction(function () use ($staff, $id, $action, $tableId) {
            $this->actor($staff, ['cashier', 'manager']);
            $entry = DB::table('pos_seating_entries')->where('branch_id', $staff->branch_id)->where('id', $id)->lockForUpdate()->firstOrFail();
            $targets = ['call' => 'called', 'arrive' => 'arrived', 'seat' => 'seated', 'cancel' => 'cancelled', 'no_show' => 'no_show'];
            abort_unless(isset($targets[$action]), 422);
            $target = $targets[$action];
            if ($entry->status === $target) {
                abort_if($action === 'seat' && $tableId !== $entry->table_id, 409, 'Seating request already used another table.');

                return ['status' => $target, 'session_id' => $entry->session_id];
            }
            $allowed = match ($action) {
                'call' => $entry->status === 'waiting',
                'arrive' => $entry->status === 'confirmed',
                'seat' => in_array($entry->status, ['waiting', 'called', 'arrived'], true),
                'cancel' => in_array($entry->status, ['waiting', 'called', 'confirmed', 'arrived'], true),
                'no_show' => $entry->status === 'called' || ($entry->status === 'confirmed' && now()->gte(Carbon::parse($entry->booked_at, 'UTC')->addMinutes(15))),
            };
            abort_unless($allowed, 409, 'Invalid seating transition.');
            $session = null;
            $update = ['status' => $target, 'updated_at' => now()];
            if ($action === 'seat') {
                abort_unless($tableId, 422, 'Select a table.');
                $table = $this->seatingTable($staff, $tableId);
                abort_if($table->capacity < $entry->party_size, 409, 'Table capacity is too small.');
                abort_if(DB::table('pos_table_sessions')->where('table_id', $tableId)->whereNotNull('active_slot')->exists(), 409, 'Table is already occupied.');
                if ($entry->kind === 'reservation') {
                    abort_unless($entry->table_id === $tableId && now()->gte(Carbon::parse($entry->booked_at, 'UTC')), 409, 'Use the reserved table at the booked time.');
                } else {
                    $this->checkWalkInTable($staff, $table, $entry->id);
                }
                $session = $this->startTableSession($staff, $tableId);
                $update += ['table_id' => $tableId, 'session_id' => $session];
            }
            DB::table('pos_seating_entries')->where('id', $id)->update($update);
            $this->audit($staff, 'restaurant.seating.'.$action, $id, ['session_id' => $session]);

            return ['status' => $target, 'session_id' => $session];
        }, 3);
    }

    private const TABLE_WINDOW = 90;

    private function seatingTable(Staff $staff, string $id): object
    {
        return DB::table('pos_restaurant_tables')->where('id', $id)->where('branch_id', $staff->branch_id)
            ->where('active', true)->lockForUpdate()->firstOrFail();
    }

    private function checkWalkInTable(Staff $staff, object $table, ?string $entryId): void
    {
        $entries = DB::table('pos_seating_entries')->where('branch_id', $staff->branch_id);
        abort_if((clone $entries)->where('table_id', $table->id)->whereIn('status', ['confirmed', 'arrived'])
            ->where('booked_at', '<', now()->addMinutes(self::TABLE_WINDOW))->exists(), 409, 'Table is held for a reservation.');
        $first = $entries->where('kind', 'walk_in')->whereIn('status', ['waiting', 'called'])
            ->where('party_size', '<=', $table->capacity)->orderBy('queue_number')->value('id');
        abort_if($first !== null && $first !== $entryId, 409, 'Seat the oldest party that fits this table first.');
    }

    private function startTableSession(Staff $staff, string $tableId): string
    {
        $id = (string) Str::uuid();
        DB::table('pos_table_sessions')->insert(['id' => $id, 'table_id' => $tableId, 'branch_id' => $staff->branch_id, 'opened_by' => $staff->id, 'active_slot' => 1, 'created_at' => now(), 'updated_at' => now()]);
        $this->audit($staff, 'restaurant.table.open', $id);

        return $id;
    }

    public function closeTable(Staff $staff, string $sessionId): void
    {
        DB::transaction(function () use ($staff, $sessionId) {
            $this->actor($staff, ['cashier', 'manager']);
            $session = DB::table('pos_table_sessions')->where('id', $sessionId)->where('branch_id', $staff->branch_id)->lockForUpdate()->firstOrFail();
            if ($session->active_slot === null) {
                return;
            }
            abort_if(DB::table('pos_kitchen_lines')->whereIn('order_id', DB::table('pos_kitchen_orders')->where('table_session_id', $sessionId)->select('id'))->whereNull('billed_sale_id')->whereNotIn('status', ['cancelled', 'wasted'])->exists(), 409, 'Table still has unsettled kitchen lines.');
            DB::table('pos_table_sessions')->where('id', $sessionId)->update(['active_slot' => null, 'closed_at' => now(), 'updated_at' => now()]);
            $this->audit($staff, 'restaurant.table.close', $sessionId);
        }, 3);
    }

    public function createOrder(Staff $staff, array $input): array
    {
        $d = validator($input, ['terminal_id' => 'required|uuid', 'catalog_version' => 'required|string|max:80', 'request_key' => 'required|string|max:100', 'channel' => 'required|in:dine_in,takeaway', 'table_session_id' => 'nullable|uuid|required_if:channel,dine_in', 'items' => 'required|array|min:1|max:50', 'items.*.product_id' => 'required|uuid|distinct', 'items.*.quantity' => 'required|integer|min:1|max:50'])->validate();
        abort_if(array_sum(array_column($d['items'], 'quantity')) > 100 || ($d['channel'] === 'takeaway' && ! empty($d['table_session_id'])), 422, 'Unsupported restaurant order size or table scope.');
        $d['table_session_id'] = $d['table_session_id'] ?? null;
        foreach ($d['items'] as &$item) {
            $item['quantity'] = (int) $item['quantity'];
        } unset($item);
        usort($d['items'], fn ($a, $b) => strcmp($a['product_id'], $b['product_id']));
        $hash = hash('sha256', CanonicalJson::encode($d));

        return DB::transaction(function () use ($staff, $d, $hash) {
            $branch = $this->actor($staff, ['cashier', 'manager'], $d['terminal_id']);
            $old = DB::table('pos_kitchen_orders')->where('branch_id', $branch->id)->where('request_key', $d['request_key'])->first();
            if ($old) {
                abort_unless($old->staff_id === $staff->id && hash_equals($old->request_hash, $hash), 409, 'Kitchen order identity conflict.');

                return json_decode($old->snapshot, true);
            }
            if ($d['channel'] === 'dine_in') {
                DB::table('pos_table_sessions')->where('id', $d['table_session_id'])->where('branch_id', $branch->id)->whereNotNull('active_slot')->lockForUpdate()->firstOrFail();
            }
            abort_unless($branch->active_catalog_version === $d['catalog_version'], 409, 'Use the current restaurant catalog.');
            $catalog = DB::table('pos_catalogs')->where('branch_id', $branch->id)->where('version', $d['catalog_version'])->where('valid_until', '>', now())->firstOrFail();
            $products = collect(json_decode($catalog->items, true))->keyBy('product_id');
            $id = (string) Str::uuid();
            $lineIds = [];
            // actor() holds the branch lock, so every terminal allocates in the same order.
            $lastNumber = DB::table('pos_kitchen_orders')->where('branch_id', $branch->id)
                ->whereNotNull('queue_number')->orderByDesc('queue_number')->lockForUpdate()->value('queue_number');
            $queueNumber = (int) $lastNumber + 1;
            $snapshot = ['order_id' => $id, 'queue_number' => $queueNumber, 'channel' => $d['channel'], 'table_session_id' => $d['table_session_id'] ?? null, 'catalog_version' => $d['catalog_version'], 'ordered_at' => now()->utc()->toIso8601String()];
            DB::table('pos_kitchen_orders')->insert(['id' => $id, 'branch_id' => $branch->id, 'terminal_id' => $d['terminal_id'], 'staff_id' => $staff->id, 'queue_number' => $queueNumber, 'table_session_id' => $d['table_session_id'] ?? null, 'channel' => $d['channel'], 'request_key' => $d['request_key'], 'request_hash' => $hash, 'catalog_version' => $d['catalog_version'], 'snapshot' => CanonicalJson::encode($snapshot), 'created_at' => now(), 'updated_at' => now()]);
            foreach ($d['items'] as $requested) {
                $product = $products->get($requested['product_id']);
                abort_unless($product && $product['active'] && isset($product['restaurant']), 422, 'Published restaurant item required.');
                app(PharmacyPolicy::class)->resolve($staff, 'restaurant', $product, ['quantity' => '1.0000']);
                for ($portion = 0; $portion < $requested['quantity']; $portion++) {
                    $line = (string) Str::uuid();
                    $lineIds[] = $line;
                    DB::table('pos_kitchen_lines')->insert(['id' => $line, 'order_id' => $id, 'product_id' => $product['product_id'], 'status' => 'reserved', 'snapshot' => CanonicalJson::encode(['product' => $product, 'quantity' => '1.0000']), 'created_at' => now(), 'updated_at' => now()]);
                    foreach ($product['restaurant']['ingredients'] as $ingredient) {
                        app(EntitlementAllocator::class)->take($branch->id, $ingredient['product_id'], $ingredient['quantity'], $branch->timezone, $line);
                    }
                }
            }
            $snapshot['line_ids'] = $lineIds;
            DB::table('pos_kitchen_orders')->where('id', $id)->update(['snapshot' => CanonicalJson::encode($snapshot)]);
            $this->audit($staff, 'restaurant.order.reserve', $id);

            return $snapshot;
        }, 3);
    }

    public function advance(Staff $staff, string $lineId, string $action, ?string $overrideReason = null): array
    {
        $sequence = ['reserved', 'sent', 'acknowledged', 'preparing', 'ready', 'served'];
        abort_unless(in_array($action, array_slice($sequence, 1), true), 422);

        return DB::transaction(function () use ($staff, $lineId, $action, $sequence, $overrideReason) {
            $branch = $this->actor($staff, in_array($action, ['sent', 'served'], true) ? ['cashier', 'manager'] : ['kitchen', 'manager']);
            [$order, $line] = $this->line($staff, $lineId);
            $position = array_search($line->status, $sequence, true);
            $target = array_search($action, $sequence, true);
            abort_unless($position !== false && $line->billed_sale_id === null, 409, 'Kitchen line is closed.');
            if ($position >= $target) {
                return ['id' => $line->id, 'status' => $line->status];
            }
            abort_unless($target === $position + 1, 409, 'Kitchen transition must follow the preparation workflow.');
            if ($action === 'preparing') {
                abort_if($order->queue_held, 409, 'Resume this held order before preparation.');
                $first = DB::table('pos_kitchen_orders as o')->where('o.branch_id', $branch->id)
                    ->where('o.queue_held', false)
                    ->whereExists(fn ($lines) => $lines->selectRaw('1')->from('pos_kitchen_lines as k')
                        ->whereColumn('k.order_id', 'o.id')->whereIn('k.status', ['reserved', 'sent', 'acknowledged']))
                    ->orderByRaw('CASE WHEN o.queue_number IS NULL THEN 0 ELSE 1 END')
                    ->orderBy('o.queue_number')->orderBy('o.created_at')->orderBy('o.id')->value('o.id');
                if ($overrideReason !== null) {
                    abort_unless($staff->role === 'manager', 403);
                    $this->audit($staff, 'restaurant.queue.skip', $lineId, ['reason' => $overrideReason, 'first_order_id' => $first]);
                } else {
                    abort_unless($first === $order->id, 409, 'Start the oldest unheld order first.');
                }
                $this->consume($staff, $branch, $order, $line, 'preparation');
            } else {
                DB::table('pos_kitchen_lines')->where('id', $line->id)->update(['status' => $action, 'updated_at' => now()]);
            }
            $this->audit($staff, 'restaurant.line.'.$action, $line->id);

            return ['id' => $line->id, 'status' => $action];
        }, 3);
    }

    public function holdOrder(Staff $staff, string $id, bool $held, string $reason): array
    {
        return DB::transaction(function () use ($staff, $id, $held, $reason) {
            $this->actor($staff, ['manager']);
            $order = DB::table('pos_kitchen_orders')->where('branch_id', $staff->branch_id)
                ->where('id', $id)->lockForUpdate()->firstOrFail();
            if ((bool) $order->queue_held !== $held) {
                abort_unless(DB::table('pos_kitchen_lines')->where('order_id', $id)
                    ->whereIn('status', ['reserved', 'sent', 'acknowledged'])->exists(), 409, 'No waiting portions on this order.');
                DB::table('pos_kitchen_orders')->where('id', $id)->update(['queue_held' => $held, 'updated_at' => now()]);
                $this->audit($staff, $held ? 'restaurant.queue.hold' : 'restaurant.queue.resume', $id, ['reason' => $reason]);
            }

            return ['held' => $held];
        }, 3);
    }

    public function cancelCommand(array $input): array
    {
        $d = validator($input, ['line_id' => 'required|uuid', 'reason' => 'required|in:customer_cancel,preparation_error,spoiled', 'physical_waste' => 'required|boolean'])->validate();
        $d['physical_waste'] = (bool) $d['physical_waste'];

        return $d + ['operation' => 'restaurant.cancel'];
    }

    public function cancel(Staff $manager, array $command, ManagerApproval $approval): array
    {
        $command = $this->cancelCommand($command);

        return DB::transaction(function () use ($manager, $command, $approval) {
            $branch = $this->actor($manager, ['manager']);
            $approval->assertFor($manager, $command);
            [$order, $line] = $this->line($manager, $command['line_id']);
            $commandHash = hash('sha256', CanonicalJson::encode($command));
            if (in_array($line->status, ['cancelled', 'wasted'], true)) {
                abort_unless($line->cancel_command_hash && hash_equals($line->cancel_command_hash, $commandHash), 409, 'Kitchen cancellation identity conflict.');

                return ['id' => $line->id, 'status' => $line->status];
            }
            abort_unless($line->billed_sale_id === null, 409, 'Paid kitchen lines require the original POS refund workflow.');
            $used = $line->consumption_hash !== null;
            if (! $used && ! $command['physical_waste']) {
                app(EntitlementAllocator::class)->finishReservation($branch->id, $line->id, $branch->timezone, false);
                DB::table('pos_kitchen_lines')->where('id', $line->id)->update(['status' => 'cancelled', 'updated_at' => now()]);
            } else {
                abort_unless($command['physical_waste'], 422, 'Consumed ingredients require explicit waste evidence.');
                $wasteId = (string) Str::uuid();
                if (! $used) {
                    $payload = $this->consume($manager, $branch, $order, $line, 'waste_before_preparation', $wasteId, $command['reason']);
                } else {
                    $payload = ['waste_id' => $wasteId, 'consumption_id' => $line->id, 'consumption_hash' => $line->consumption_hash, 'branch_id' => $branch->id, 'terminal_id' => $order->terminal_id, 'staff_id' => $manager->id, 'reason' => $command['reason'], 'quantity' => '1.0000', 'occurred_at' => now()->utc()->toIso8601String(), 'currency' => $branch->currency, 'business_timezone' => $branch->timezone];
                    app(OutboxService::class)->append('kitchen.wasted', $wasteId, $payload);
                }
                DB::table('pos_kitchen_waste')->insert(['id' => $wasteId, 'line_id' => $line->id, 'manager_id' => $manager->id, 'reason' => $command['reason'], 'snapshot' => CanonicalJson::encode($payload), 'created_at' => now(), 'updated_at' => now()]);
                DB::table('pos_kitchen_lines')->where('id', $line->id)->update(['status' => 'wasted', 'updated_at' => now()]);
            }
            DB::table('pos_kitchen_lines')->where('id', $line->id)->update(['cancel_command_hash' => $commandHash]);
            $this->refreshCustomerRequest($order->id);
            $this->audit($manager, 'restaurant.line.cancel', $line->id, ['reason' => $command['reason'], 'physical_waste' => $command['physical_waste'], 'previous_status' => $line->status, 'command_hash' => $commandHash]);

            return ['id' => $line->id, 'status' => $used || $command['physical_waste'] ? 'wasted' : 'cancelled'];
        });
    }

    public function billingInput(Staff $staff, array $input): array
    {
        $d = validator($input, ['terminal_id' => 'required|uuid', 'request_key' => 'required|string|max:100', 'cash_tendered' => 'required|string|max:20', 'kitchen_line_ids' => 'required|array|min:1|max:100', 'kitchen_line_ids.*' => 'required|uuid|distinct', 'discount_approval_id' => 'sometimes|uuid', 'member_id' => 'sometimes|nullable|uuid', 'promotion_id' => 'sometimes|nullable|uuid', 'membership_publication_id' => 'sometimes|nullable|uuid'])->validate();

        return DB::transaction(function () use ($staff, $d) {
            $this->actor($staff, ['cashier', 'manager'], $d['terminal_id']);
            sort($d['kitchen_line_ids'], SORT_STRING);
            $existing = DB::table('pos_orders')->where('branch_id', $staff->branch_id)->where('staff_id', $staff->id)->where('request_key', $d['request_key'])->first();
            $bill = $this->bill($staff, $d['kitchen_line_ids'], $existing?->id);

            return ['terminal_id' => $d['terminal_id'], 'request_key' => $d['request_key'], 'cash_tendered' => $d['cash_tendered'], 'catalog_version' => $bill['catalog_version'], 'items' => $bill['items'], 'kitchen_line_ids' => $d['kitchen_line_ids']] + array_intersect_key($d, array_flip(['member_id', 'promotion_id', 'membership_publication_id'])) + (isset($d['discount_approval_id']) ? ['discount_approval_id' => $d['discount_approval_id']] : []);
        });
    }

    public function claimBill(Staff $staff, array $checkout): array
    {
        $this->guard();
        abort_unless(DB::transactionLevel() > 0, 500);
        $bill = $this->bill($staff, $checkout['kitchen_line_ids']);
        $items = array_map(fn ($line) => ['product_id' => $line['product_id'], 'quantity' => bcadd($line['quantity'], '0', 4), 'unit' => $line['unit'] ?? ''], $checkout['items']);
        usort($items, fn ($a, $b) => strcmp($a['product_id'], $b['product_id']));
        abort_unless($checkout['catalog_version'] === $bill['catalog_version'] && CanonicalJson::encode($items) === CanonicalJson::encode($bill['items']), 409, 'Kitchen bill differs from frozen served portions.');

        return $bill['refs'];
    }

    public function markBilled(Staff $staff, array $refs, string $saleId): void
    {
        $this->guard();
        if (DB::transactionLevel() === 0) {
            throw new \LogicException('Kitchen billing requires a transaction.');
        }
        $ids = array_column(array_merge(...array_values($refs)), 'line_id');
        $changed = DB::table('pos_kitchen_lines')->whereIn('id', $ids)->whereIn('order_id', DB::table('pos_kitchen_orders')->where('branch_id', $staff->branch_id)->select('id'))->where('status', 'served')->whereNull('billed_sale_id')->update(['billed_sale_id' => $saleId, 'updated_at' => now()]);
        abort_unless($changed === count($ids), 409, 'Kitchen portions already billed.');
        $this->audit($staff, 'restaurant.bill.claim', $saleId);
        foreach (DB::table('pos_kitchen_lines')->whereIn('id', $ids)->distinct()->pluck('order_id') as $orderId) {
            $this->refreshCustomerRequest($orderId);
        }
    }

    private function bill(Staff $staff, array $ids, ?string $alreadyBilledSale = null): array
    {
        abort_if(Schema::hasTable('pos_delivery_kitchen_lines') && DB::table('pos_delivery_kitchen_lines')->whereIn('line_id', $ids)->exists(), 409, 'COD delivery portions are held for financial review and cannot be billed again.');
        $lines = DB::table('pos_kitchen_lines')->whereIn('id', $ids)->whereIn('order_id', DB::table('pos_kitchen_orders')->where('branch_id', $staff->branch_id)->select('id'))->orderBy('id')->lockForUpdate()->get();
        abort_unless($lines->count() === count($ids), 404);
        $version = null;
        $items = [];
        $refs = [];
        foreach ($lines as $line) {
            abort_unless($line->status === 'served' && $line->billed_sale_id === $alreadyBilledSale && $line->consumption_purpose === 'preparation' && $line->consumption_hash !== null, 409, 'Only served unbilled portions can be settled.');
            $order = DB::table('pos_kitchen_orders')->where('id', $line->order_id)->firstOrFail();
            abort_if($version !== null && $version !== $order->catalog_version, 422, 'Settle different catalog versions on separate receipts.');
            $version = $order->catalog_version;
            $product = $this->frozen($staff->branch_id, $order, $line, false);
            $sku = $line->product_id;
            if (! isset($items[$sku])) {
                $items[$sku] = ['product_id' => $sku, 'quantity' => '0.0000', 'unit' => $product['unit']];
            }
            $items[$sku]['quantity'] = bcadd($items[$sku]['quantity'], '1', 4);
            $refs[$sku][] = ['line_id' => $line->id, 'consumption_hash' => $line->consumption_hash];
        }
        ksort($items);

        return ['catalog_version' => $version, 'items' => array_values($items), 'refs' => $refs];
    }

    public function preview(Staff $staff, array $input): array
    {
        $d = validator($input, ['terminal_id' => 'required|uuid', 'kitchen_line_ids' => 'required|array|min:1|max:100', 'kitchen_line_ids.*' => 'required|uuid|distinct'])->validate();
        $bill = $this->billingInput($staff, $d + ['request_key' => 'preview-'.Str::uuid(), 'cash_tendered' => '0.00']);
        $catalog = DB::table('pos_catalogs')->where('branch_id', $staff->branch_id)->where('version', $bill['catalog_version'])->firstOrFail();
        $all = collect(json_decode($catalog->items, true))->keyBy('product_id');
        $products = [];
        $total = '0.00';
        foreach ($bill['items'] as $line) {
            $product = $all[$line['product_id']];
            $products[] = array_intersect_key($product, array_flip(['product_id', 'name', 'barcode', 'unit', 'price', 'tax_rate', 'active', 'max_manager_discount_pct']));
            $amount = bcadd(bcmul($product['price'], $line['quantity'], 8), '0.005', 2);
            $tax = bcadd(bcdiv(bcmul($amount, $product['tax_rate'], 8), '100', 8), '0.005', 2);
            $total = bcadd($total, bcadd($amount, $tax, 2), 2);
        }

        return ['catalog_version' => $bill['catalog_version'], 'items' => $bill['items'], 'products' => $products, 'total' => $total];
    }

    private function consume(Staff $staff, object $branch, object $order, object $line, string $purpose, ?string $wasteId = null, ?string $reason = null): array
    {
        $product = $this->frozen($branch->id, $order, $line, $purpose === 'preparation');
        $allocations = app(EntitlementAllocator::class)->finishReservation($branch->id, $line->id, $branch->timezone, true, $purpose === 'waste_before_preparation');
        $ingredients = [];
        foreach ($product['restaurant']['ingredients'] as $ingredient) {
            $parts = array_values(array_filter($allocations, fn ($a) => $a['product_id'] === $ingredient['product_id']));
            $sum = array_reduce($parts, fn ($sum, $a) => bcadd($sum, $a['quantity'], 4), '0.0000');
            abort_unless(bccomp($sum, $ingredient['quantity'], 4) === 0, 409, 'Frozen recipe reservation differs.');
            $ingredients[] = ['product_id' => $ingredient['product_id'], 'quantity' => $ingredient['quantity'], 'allocations' => array_map(fn ($a) => array_intersect_key($a, array_flip(['grant_id', 'epoch', 'lot_id', 'quantity'])), $parts)];
        }
        $payload = ['consumption_id' => $line->id, 'order_id' => $order->id, 'product_id' => $line->product_id, 'branch_id' => $branch->id, 'terminal_id' => $order->terminal_id, 'staff_id' => $staff->id, 'catalog_version' => $order->catalog_version, 'recipe_hash' => $product['restaurant']['recipe_hash'], 'purpose' => $purpose, 'quantity' => '1.0000', 'unit' => $product['unit'], 'ingredients' => $ingredients, 'occurred_at' => now()->utc()->toIso8601String(), 'currency' => $branch->currency, 'business_timezone' => $branch->timezone];
        if ($wasteId) {
            $payload += ['waste_id' => $wasteId, 'reason' => $reason];
        }
        app(OutboxService::class)->append('kitchen.consumed', $line->id, $payload);
        DB::table('pos_kitchen_lines')->where('id', $line->id)->update(['status' => $purpose === 'preparation' ? 'preparing' : 'wasted', 'consumption_hash' => hash('sha256', CanonicalJson::encode($payload)), 'consumption_purpose' => $purpose, 'updated_at' => now()]);

        return $payload;
    }

    private function line(Staff $staff, string $id): array
    {
        $line = DB::table('pos_kitchen_lines')->where('id', $id)->whereIn('order_id', DB::table('pos_kitchen_orders')->where('branch_id', $staff->branch_id)->select('id'))->lockForUpdate()->firstOrFail();

        return [DB::table('pos_kitchen_orders')->where('id', $line->order_id)->firstOrFail(), $line];
    }

    private function frozen(string $branchId, object $order, object $line, bool $validNow = true): array
    {
        $catalog = DB::table('pos_catalogs')->where('branch_id', $branchId)->where('version', $order->catalog_version)->when($validNow, fn ($q) => $q->where('valid_until', '>', now()))->firstOrFail();
        $published = collect(json_decode($catalog->items, true))->firstWhere('product_id', $line->product_id);
        $snapshot = json_decode($line->snapshot, true);
        abort_unless($published && CanonicalJson::encode($published) === CanonicalJson::encode($snapshot['product']), 409, 'Kitchen catalog evidence conflict.');

        return $snapshot['product'];
    }

    private function audit(Staff $staff, string $action, string $id, array $metadata = []): void
    {
        DB::table('pos_audit')->insert(['actor_id' => $staff->id, 'branch_id' => $staff->branch_id, 'action' => $action, 'source_id' => $id, 'metadata' => CanonicalJson::encode($metadata), 'created_at' => now()]);
    }
}
