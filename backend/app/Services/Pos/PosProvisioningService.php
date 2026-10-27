<?php

namespace App\Services\Pos;

use App\Models\AuditLog;
use App\Models\Branch;
use App\Models\InventoryLot;
use App\Models\Organization;
use App\Models\Product;
use App\Models\User;
use App\Models\Warehouse;
use App\Services\InventoryMovementService;
use App\Support\PosCanonicalJson;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class PosProvisioningService
{
    public function restaurantVariant(User $actor, array $input): void
    {
        abort_unless(app()->environment(['local', 'testing']) && $actor->status === 'active' && $actor->hasPermissionCode('pos.manage'), 403);
        $d = validator($input, ['product_id' => 'required|uuid', 'parent_product_id' => 'required|uuid|different:product_id', 'label' => 'required|string|max:100', 'size' => 'required|string|max:60', 'modifiers' => 'present|array|max:10', 'modifiers.*' => 'required|string|max:60|distinct'])->validate();
        DB::transaction(function () use ($actor, $d) {
            DB::table('organizations')->where('id', $actor->org_id)->lockForUpdate()->firstOrFail();
            foreach ([$d['product_id'], $d['parent_product_id']] as $id) {
                Product::where('org_id', $actor->org_id)->where('is_active', true)->findOrFail($id);
            }
            abort_if(DB::table('pos_restaurant_variants')->where('product_id', $d['parent_product_id'])->exists() || DB::table('pos_restaurant_variants')->where('parent_product_id', $d['product_id'])->exists(), 422, 'Restaurant option families must have one level.');
            $old = DB::table('pos_restaurant_variants')->where('product_id', $d['product_id'])->first();
            DB::table('pos_restaurant_variants')->updateOrInsert(['product_id' => $d['product_id']], ['org_id' => $actor->org_id, 'parent_product_id' => $d['parent_product_id'], 'label' => $d['label'], 'size' => $d['size'], 'modifiers' => PosCanonicalJson::encode($d['modifiers']), 'created_at' => $old?->created_at ?? now(), 'updated_at' => now()]);
            AuditLog::create(['org_id' => $actor->org_id, 'actor_user_id' => $actor->id, 'action' => 'pos.restaurant.variant', 'entity_type' => 'product', 'entity_id' => $d['product_id'], 'after_json' => $d, 'request_id' => (string) Str::uuid()]);
        });
    }

    public function taxProfile(User $actor, string $instanceId, string $version, string $branchNumber): array
    {
        abort_unless(app()->environment(['local', 'testing']) && config('pos.local_tax_documents_enabled') === true && $actor->status === 'active' && $actor->hasPermissionCode('pos.manage'), 403);
        validator(['version' => $version, 'branch_no' => $branchNumber], ['version' => 'required|string|max:80', 'branch_no' => ['required', 'string', 'regex:/^\d{5}$/D']])->validate();

        return DB::transaction(function () use ($actor, $instanceId, $version, $branchNumber) {
            $instance = DB::table('pos_instances')->where('id', $instanceId)->where('org_id', $actor->org_id)->lockForUpdate()->firstOrFail();
            $org = Organization::findOrFail($actor->org_id);
            $branch = Branch::where('org_id', $actor->org_id)->findOrFail($instance->branch_id);
            $profile = ['branch_id' => $branch->id, 'version' => $version, 'seller_name' => $org->legal_name ?: $org->name, 'seller_tax_id' => $org->tax_id, 'seller_address' => $branch->address ?: $org->address, 'seller_branch_no' => $branchNumber];
            validator($profile, ['seller_name' => 'required|string|max:255', 'seller_tax_id' => ['required', 'string', 'regex:/^\d{13}$/D'], 'seller_address' => 'required|string|max:1000'])->validate();
            $hash = hash('sha256', PosCanonicalJson::encode($profile));
            $old = DB::table('pos_tax_profile_publications')->where('pos_instance_id', $instanceId)->where('version', $version)->first();
            if ($old) {
                abort_unless(hash_equals($old->payload_hash, $hash), 409, 'Issuer profile version conflict.');
                DB::table('pos_instances')->where('id', $instanceId)->update(['current_tax_profile_version' => $version]);

                return $profile;
            }
            DB::table('pos_tax_profile_publications')->insert(['id' => (string) Str::uuid(), 'pos_instance_id' => $instanceId, 'version' => $version, 'snapshot' => PosCanonicalJson::encode($profile), 'payload_hash' => $hash, 'created_at' => now(), 'updated_at' => now()]);
            DB::table('pos_instances')->where('id', $instanceId)->update(['current_tax_profile_version' => $version]);
            AuditLog::create(['org_id' => $actor->org_id, 'actor_user_id' => $actor->id, 'action' => 'pos.tax_profile.provisioned', 'entity_type' => 'pos', 'entity_id' => $instanceId, 'after_json' => ['version' => $version, 'payload_hash' => $hash], 'request_id' => (string) Str::uuid()]);

            return $profile;
        });
    }

    public function grant(string $instanceId, string $productId, string $quantity, ?string $lotId = null): array
    {
        abort_unless(preg_match('/^\d{1,10}(\.\d{1,4})?$/D', $quantity) && bccomp($quantity, '0', 4) > 0, 422, 'Invalid entitlement quantity.');

        return DB::transaction(function () use ($instanceId, $productId, $quantity, $lotId) {
            $i = DB::table('pos_instances')->where('id', $instanceId)->where('active', true)->lockForUpdate()->firstOrFail();
            abort_if($i->recovery_fenced_at !== null, 423, 'Recovery-fenced instance cannot receive stock quota.');
            $inventory = app(InventoryMovementService::class);
            $inventory->lockProducts($i->org_id, [$productId]);
            Warehouse::where('org_id', $i->org_id)->findOrFail($i->warehouse_id);
            $expires = null;
            if ($lotId) {
                abort_if(DB::table('pos_lot_recalls')->where('org_id', $i->org_id)->where('inventory_lot_id', $lotId)->exists(), 422, 'Recalled lot cannot be allocated.');
                $lot = InventoryLot::where('org_id', $i->org_id)->where('product_id', $productId)->findOrFail($lotId);
                abort_if($lot->expires_at && $lot->expires_at->lt(today()), 422, 'Expired lot cannot be allocated.');
                $expires = $lot->expires_at?->toDateString();
            }
            $stock = $inventory->stock($i->org_id, $productId, $i->warehouse_id)->where('inventory_lot_id', $lotId)->sum('quantity');
            $reserved = DB::table('pos_stock_entitlements')->where('org_id', $i->org_id)->where('warehouse_id', $i->warehouse_id)->where('product_id', $productId)->where('inventory_lot_id', $lotId)->where('status', '!=', 'closed')->get()->reduce(fn ($sum, $g) => bcadd($sum, bcsub((string) $g->granted_quantity, (string) $g->consumed_quantity, 4), 4), '0.0000');
            abort_if(bccomp(bcsub((string) $stock, $reserved, 4), $quantity, 4) < 0, 422, 'Insufficient unallocated ERP stock.');
            $id = (string) Str::uuid();
            DB::table('pos_stock_entitlements')->insert(['id' => $id, 'org_id' => $i->org_id, 'pos_instance_id' => $i->id, 'warehouse_id' => $i->warehouse_id, 'product_id' => $productId, 'inventory_lot_id' => $lotId, 'epoch' => 1, 'granted_quantity' => $quantity, 'consumed_quantity' => '0', 'status' => 'pending', 'created_at' => now(), 'updated_at' => now()]);
            DB::table('pos_stock_entitlements')->where('id', $id)->update(['publication_snapshot' => PosCanonicalJson::encode(['branch_id' => $i->branch_id, 'product_id' => $productId, 'lot_id' => $lotId, 'expires_at' => $expires, 'epoch' => 1, 'quantity' => $quantity])]);

            return ['id' => $id, 'payload' => ['branch_id' => $i->branch_id, 'product_id' => $productId, 'lot_id' => $lotId, 'expires_at' => $expires, 'epoch' => 1, 'quantity' => $quantity]];
        });
    }

    public function catalog(string $instanceId, string $version, array $productIds, string $taxRate, \DateTimeInterface $validUntil, array $onlineProductIds = [], array $discountCeilings = [], array $restaurantRecipes = []): array
    {
        abort_unless(preg_match('/^[A-Za-z0-9_-]{1,80}$/D', $version) && preg_match('/^\d{1,2}(\.\d{1,2})?$/D', $taxRate) && count($productIds) <= 5000 && $validUntil > now(), 422, 'Invalid POS catalog policy.');

        abort_unless(array_diff($onlineProductIds, $productIds) === [], 422, 'Online products must belong to the published catalog.');
        abort_unless(array_diff(array_keys($discountCeilings), $productIds) === [], 422, 'Discount policy must belong to the published catalog.');
        foreach ($discountCeilings as $limit) {
            abort_unless(is_string($limit) && preg_match('/^\d{1,3}(\.\d{1,2})?$/D', $limit) && bccomp($limit, '100', 2) <= 0, 422, 'Invalid manager discount ceiling.');
        }

        abort_unless(array_diff(array_keys($restaurantRecipes), $productIds) === [], 422, 'Recipe menu must belong to the published catalog.');

        return DB::transaction(function () use ($instanceId, $version, $productIds, $taxRate, $validUntil, $onlineProductIds, $discountCeilings, $restaurantRecipes) {
            $i = DB::table('pos_instances')->where('id', $instanceId)->where('active', true)->lockForUpdate()->firstOrFail();
            $products = Product::where('org_id', $i->org_id)->whereIn('id', $productIds)->orderBy('id')->get();
            abort_if($restaurantRecipes !== [] && $i->business_type !== 'restaurant', 422, 'Restaurant capability required for recipes.');
            abort_unless($products->count() === count(array_unique($productIds)), 422, 'Catalog product scope mismatch.');
            $payload = ['branch_id' => $i->branch_id, 'valid_until' => Carbon::instance(\DateTime::createFromInterface($validUntil))->utc()->toIso8601String(), 'items' => $products->map(fn ($p) => ['product_id' => $p->id, 'name' => $p->name, 'barcode' => $p->barcode, 'unit' => $p->unit, 'price' => $p->price, 'tax_rate' => $taxRate, 'active' => $p->is_active])->all()];
            $policies = DB::table('pos_product_policies')->where('org_id', $i->org_id)->whereIn('product_id', $productIds)->get()->keyBy('product_id');
            $variants = $i->business_type === 'restaurant' ? DB::table('pos_restaurant_variants')->where('org_id', $i->org_id)->whereIn('product_id', $productIds)->get()->keyBy('product_id') : collect();
            foreach ($payload['items'] as &$item) {
                if (isset($policies[$item['product_id']])) {
                    $item['pharmacy'] = json_decode($policies[$item['product_id']]->pharmacy, true, flags: JSON_THROW_ON_ERROR);
                }
            }
            unset($item);
            foreach ($payload['items'] as &$item) {
                $item['online_visible'] = in_array($item['product_id'], $onlineProductIds, true) && ! ($item['pharmacy']['requires_pharmacist'] ?? false);
                $item['max_manager_discount_pct'] = bcadd($discountCeilings[$item['product_id']] ?? '0', '0', 2);
                if ($i->business_type === 'restaurant') {
                    $item['restaurant'] = app(PosRecipeSnapshotService::class)->build($i->org_id, $products->firstWhere('id', $item['product_id']), $restaurantRecipes[$item['product_id']] ?? null);
                    if (isset($variants[$item['product_id']])) {
                        $v = $variants[$item['product_id']];
                        abort_unless(in_array($v->parent_product_id, $productIds, true), 422, 'Publish the parent menu with its options.');
                        $item['restaurant_variant'] = ['parent_product_id' => $v->parent_product_id, 'label' => $v->label, 'size' => $v->size, 'modifiers' => json_decode($v->modifiers, true)];
                    }
                }
            }
            unset($item);
            $hash = hash('sha256', PosCanonicalJson::encode($payload));
            $existing = DB::table('pos_catalog_publications')->where('pos_instance_id', $instanceId)->where('version', $version)->first();
            if ($existing) {
                abort_unless(hash_equals($existing->payload_hash, $hash), 409, 'Catalog version conflict.');
                DB::table('pos_instances')->where('id', $instanceId)->update(['current_catalog_version' => $version]);

                return $payload;
            }
            DB::table('pos_catalog_publications')->insert(['id' => (string) Str::uuid(), 'pos_instance_id' => $instanceId, 'version' => $version, 'payload' => PosCanonicalJson::encode($payload), 'payload_hash' => $hash, 'valid_until' => $validUntil, 'created_at' => now(), 'updated_at' => now()]);
            DB::table('pos_instances')->where('id', $instanceId)->update(['current_catalog_version' => $version]);

            return $payload;
        });
    }

    public function setLocalPharmacyPolicy(User $actor, string $productId, array $policy): void
    {
        abort_unless(app()->environment(['local', 'testing']) && $actor->status === 'active' && $actor->hasPermissionCode('pos.manage'), 403, 'Local POS policy administration required.');
        $product = Product::where('org_id', $actor->org_id)->findOrFail($productId);
        $policy = validator($policy, ['requires_pharmacist' => 'required|boolean', 'base_step' => ['required', 'string', 'regex:/^\d{1,8}(\.\d{1,4})?$/D'], 'units' => 'present|array|max:20', 'units.*' => 'array:unit,factor,price,conversion_version', 'units.*.unit' => 'required|string|max:30|distinct', 'units.*.factor' => ['required', 'string', 'regex:/^\d{1,8}(\.\d{1,4})?$/D'], 'units.*.price' => ['required', 'string', 'regex:/^\d{1,10}(\.\d{1,2})?$/D'], 'units.*.conversion_version' => 'required|string|max:80'])->validate();
        abort_unless(bccomp($policy['base_step'], '0', 4) > 0, 422, 'Positive base quantity step required.');
        foreach ($policy['units'] as $unit) {
            abort_unless(bccomp($unit['factor'], '0', 4) > 0 && $unit['unit'] !== $product->unit, 422, 'Positive conversion factor and distinct selling unit required.');
        }
        DB::transaction(function () use ($actor, $productId, $policy) {
            app(InventoryMovementService::class)->lockProducts($actor->org_id, [$productId]);
            $existing = DB::table('pos_product_policies')->where('org_id', $actor->org_id)->where('product_id', $productId)->first();
            DB::table('pos_product_policies')->updateOrInsert(['org_id' => $actor->org_id, 'product_id' => $productId], ['id' => $existing?->id ?? (string) Str::uuid(), 'pharmacy' => PosCanonicalJson::encode($policy), 'created_at' => $existing?->created_at ?? now(), 'updated_at' => now()]);
            AuditLog::create(['org_id' => $actor->org_id, 'actor_user_id' => $actor->id, 'action' => 'pos.local_pharmacy_policy', 'entity_type' => 'product', 'entity_id' => $productId, 'after_json' => ['policy_hash' => hash('sha256', PosCanonicalJson::encode($policy))], 'request_id' => (string) Str::uuid()]);
        });
    }
}
