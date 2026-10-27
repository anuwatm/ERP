<?php

namespace Pos\Services;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Pos\Support\CanonicalJson;

class ProvisioningService
{
    public function catalog(string $version, array $input): void
    {
        $this->branch($input['branch_id'] ?? null);
        $data = validator($input, ['branch_id' => 'required|uuid|exists:pos_branches,id', 'valid_until' => 'required|date|after:now', 'items' => 'required|array|max:5000', 'items.*.product_id' => 'required|uuid|distinct', 'items.*.name' => 'required|string|max:255', 'items.*.barcode' => 'nullable|string|max:100', 'items.*.unit' => 'required|string|max:30', 'items.*.price' => ['required', 'string', 'regex:/^\d{1,10}(\.\d{1,2})?$/D'], 'items.*.tax_rate' => ['required', 'string', 'regex:/^\d{1,2}(\.\d{1,2})?$/D'], 'items.*.active' => 'required|boolean'])->validate();
        $data['items'] = array_map(fn ($item) => array_intersect_key($item, array_flip(['product_id', 'name', 'barcode', 'unit', 'price', 'tax_rate', 'active'])), $data['items']);
        foreach ($input['items'] as $index => $item) {
            $visibility = validator($item, ['online_visible' => 'sometimes|boolean'])->validate();
            $data['items'][$index]['online_visible'] = $visibility['online_visible'] ?? false;
            $discount = validator($item, ['max_manager_discount_pct' => ['sometimes', 'string', 'regex:/^\d{1,3}(\.\d{1,2})?$/D']])->validate();
            $limit = bcadd($discount['max_manager_discount_pct'] ?? '0', '0', 2);
            abort_if(bccomp($limit, '100', 2) > 0, 422, 'Invalid discount ceiling.');
            $data['items'][$index]['max_manager_discount_pct'] = $limit;
            if (isset($item['restaurant'])) {
                if (isset($item['restaurant_variant'])) {
                    $variant = validator($item['restaurant_variant'], ['parent_product_id' => 'required|uuid', 'label' => 'required|string|max:100', 'size' => 'required|string|max:60', 'modifiers' => 'present|array|max:10', 'modifiers.*' => 'required|string|max:60|distinct'])->validate();
                    $parent = collect($input['items'])->firstWhere('product_id', $variant['parent_product_id']);
                    abort_unless($parent && $parent['product_id'] !== $item['product_id'] && isset($parent['restaurant']) && ! isset($parent['restaurant_variant']), 422, 'Restaurant option parent is invalid.');
                    $data['items'][$index]['restaurant_variant'] = $variant;
                }
                abort_unless(DB::table('pos_branches')->where('id', $data['branch_id'])->where('business_type', 'restaurant')->exists(), 422, 'Restaurant capability required.');
                $recipe = validator($item['restaurant'], ['kind' => 'required|in:recipe,stocked', 'recipe_id' => 'nullable|uuid|required_if:kind,recipe', 'recipe_version' => 'required|string|max:80', 'yield_quantity' => ['required', 'string', 'regex:/^\d{1,10}\.\d{4}$/D'], 'yield_unit' => 'required|string|max:30', 'recipe_hash' => 'required|string|size:64', 'ingredients' => 'required|array|min:1|max:100', 'ingredients.*.product_id' => 'required|uuid|distinct', 'ingredients.*.unit' => 'required|string|max:30', 'ingredients.*.quantity' => ['required', 'string', 'regex:/^\d{1,10}\.\d{4}$/D']])->validate();
                $body = $recipe;
                unset($body['recipe_hash']);
                abort_unless(bccomp($recipe['yield_quantity'], '0', 4) > 0 && $recipe['yield_unit'] === $item['unit'] && hash_equals($recipe['recipe_hash'], hash('sha256', CanonicalJson::encode($body))), 422, 'Recipe snapshot invalid.');
                foreach ($recipe['ingredients'] as $ingredient) {
                    abort_unless(bccomp($ingredient['quantity'], '0', 4) > 0, 422, 'Recipe demand must be positive.');
                }
                $data['items'][$index]['restaurant'] = $recipe;
            }
            if (isset($item['pharmacy'])) {
                $policy = validator($item['pharmacy'], [
                    'requires_pharmacist' => 'required|boolean',
                    'base_step' => ['required', 'string', 'regex:/^\d{1,8}(\.\d{1,4})?$/D'],
                    'units' => 'present|array|max:20',
                    'units.*' => 'array:unit,factor,price,conversion_version',
                    'units.*.unit' => 'required|string|max:30|distinct',
                    'units.*.factor' => ['required', 'string', 'regex:/^\d{1,8}(\.\d{1,4})?$/D'],
                    'units.*.price' => ['required', 'string', 'regex:/^\d{1,10}(\.\d{1,2})?$/D'],
                    'units.*.conversion_version' => 'required|string|max:80',
                ])->validate();
                abort_unless(bccomp($policy['base_step'], '0', 4) > 0, 422, 'Base quantity step must be positive.');
                foreach ($policy['units'] as $unit) {
                    abort_unless(bccomp($unit['factor'], '0', 4) > 0 && $unit['unit'] !== $item['unit'], 422, 'Invalid selling unit factor.');
                }
                $data['items'][$index]['pharmacy'] = $policy;
            }
        }
        $hash = hash('sha256', CanonicalJson::encode($data));
        DB::transaction(function () use ($version, $data, $hash) {
            DB::table('pos_branches')->where('id', $data['branch_id'])->lockForUpdate()->firstOrFail();
            $existing = DB::table('pos_catalogs')->where('branch_id', $data['branch_id'])->where('version', $version)->first();
            if ($existing) {
                abort_unless(hash_equals($existing->payload_hash, $hash), 409, 'Catalog version conflict.');

                return;
            }
            DB::table('pos_catalogs')->insert(['id' => (string) Str::uuid(), 'branch_id' => $data['branch_id'], 'version' => $version, 'payload_hash' => $hash, 'items' => CanonicalJson::encode($data['items']), 'valid_until' => Carbon::parse($data['valid_until'])->utc(), 'created_at' => now(), 'updated_at' => now()]);
            DB::table('pos_branches')->where('id', $data['branch_id'])->update(['active_catalog_version' => $version, 'updated_at' => now()]);
        });
    }

    public function grant(string $id, array $input): void
    {
        $this->branch($input['branch_id'] ?? null);
        $data = validator($input, ['branch_id' => 'required|uuid|exists:pos_branches,id', 'product_id' => 'required|uuid', 'lot_id' => 'nullable|uuid', 'expires_at' => 'nullable|date_format:Y-m-d', 'epoch' => 'required|integer|min:1', 'quantity' => ['required', 'string', 'regex:/^\d{1,10}(\.\d{1,4})?$/D']])->validate();
        abort_unless(bccomp($data['quantity'], '0', 4) > 0, 422);
        $hash = hash('sha256', CanonicalJson::encode($data));
        DB::transaction(function () use ($id, $data, $hash) {
            DB::table('pos_branches')->where('id', $data['branch_id'])->lockForUpdate()->firstOrFail();
            $existing = DB::table('pos_grants')->where('id', $id)->first();
            if ($existing) {
                abort_unless(hash_equals($existing->payload_hash, $hash), 409, 'Grant identity conflict.');

                return;
            }
            DB::table('pos_grants')->insert(['id' => $id, 'branch_id' => $data['branch_id'], 'product_id' => $data['product_id'], 'lot_id' => $data['lot_id'] ?? null, 'expires_at' => $data['expires_at'] ?? null, 'epoch' => $data['epoch'], 'granted_quantity' => $data['quantity'], 'consumed_quantity' => '0', 'status' => 'active', 'payload_hash' => $hash, 'created_at' => now(), 'updated_at' => now()]);
        });
    }

    public function freezeGrant(string $id, int $epoch): array
    {
        abort_unless(app()->environment(['local', 'testing']) && config('pos.local_quota_recovery_enabled') === true, 503, 'Local quota recovery disabled.');

        return DB::transaction(function () use ($id, $epoch) {
            $reference = DB::table('pos_grants')->where('id', $id)->firstOrFail();
            $this->branch($reference->branch_id);
            DB::table('pos_branches')->where('id', $reference->branch_id)->lockForUpdate()->firstOrFail();
            $grant = DB::table('pos_grants')->where('id', $id)->lockForUpdate()->firstOrFail();
            abort_unless($grant->epoch === $epoch, 409, 'Grant epoch mismatch.');
            if ($grant->freeze_evidence) {
                return json_decode($grant->freeze_evidence, true, flags: JSON_THROW_ON_ERROR);
            }
            abort_unless($grant->status === 'active', 409, 'Grant cannot be frozen.');
            abort_if(bccomp((string) $grant->reserved_quantity, '0', 4) !== 0, 409, 'Grant has active or inconsistent kitchen reservations.');
            $proof = ['grant_id' => $id, 'epoch' => $epoch, 'status' => 'frozen', 'granted_quantity' => bcadd((string) $grant->granted_quantity, '0', 4), 'consumed_quantity' => bcadd((string) $grant->consumed_quantity, '0', 4), 'reserved_quantity' => '0.0000', 'source_sequence' => max((int) DB::table('pos_outbox')->max('sequence'), (int) DB::table('pos_outbox_archive')->max('sequence'))];
            DB::table('pos_grants')->where('id', $id)->update(['status' => 'frozen', 'freeze_evidence' => CanonicalJson::encode($proof), 'updated_at' => now()]);
            DB::table('pos_audit')->insert(['branch_id' => $grant->branch_id, 'actor_id' => null, 'action' => 'quota.local_freeze', 'source_id' => $id, 'metadata' => CanonicalJson::encode($proof), 'created_at' => now()]);

            return $proof;
        });
    }

    private function branch(mixed $id): void
    {
        abort_unless(is_string($id) && in_array($id, config('pos.connector_branch_ids'), true), 403, 'Branch outside connector scope.');
    }
}
