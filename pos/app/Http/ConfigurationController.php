<?php

namespace Pos\Http;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Pos\Models\Staff;

class ConfigurationController
{
    public function access(Request $request): void
    {
        abort_unless(app()->environment(['local', 'testing']) && in_array($request->ip(), ['127.0.0.1', '::1'], true), 403);
        abort_unless($request->user()->role === 'manager', 403);
        abort_unless(DB::table('pos_branches')->where('id', $request->user()->branch_id)->where('active', true)->exists(), 403);
    }

    public function show(Request $request)
    {
        $branch = DB::table('pos_branches')->where('id', $request->user()->branch_id)->firstOrFail();

        return response()->view('configuration', [
            'branch' => $branch,
            'terminals' => DB::table('pos_terminals')->where('branch_id', $branch->id)->orderBy('code')->get(),
            'staff' => Staff::where('branch_id', $branch->id)->orderBy('name')->get(['id', 'name', 'email', 'role', 'active', 'pharmacist_qualified']),
            'features' => collect(config('pos'))->filter(fn ($value, $key) => str_starts_with($key, 'local_') && is_bool($value)),
        ])->header('Cache-Control', 'private, no-store');
    }

    public function save(Request $request, string $section)
    {
        abort_unless(in_array($section, ['branch', 'terminal', 'staff'], true), 404);
        $request->validate(['manager_password' => 'required|string|max:1000']);
        abort_unless(Hash::check($request->input('manager_password'), $request->user()->password), 403, 'Manager password required.');

        DB::transaction(function () use ($request, $section) {
            $branch = DB::table('pos_branches')->where('id', $request->user()->branch_id)->lockForUpdate()->firstOrFail();
            $id = $branch->id;
            if ($section === 'branch') {
                $data = $request->validate(['name' => 'required|string|max:255', 'business_type' => 'required|in:retail,restaurant,pharmacy']);
                if ($data['business_type'] !== $branch->business_type) {
                    foreach (['pos_terminals', 'pos_catalogs', 'pos_grants', 'pos_orders', 'pos_tax_profiles'] as $table) {
                        abort_if(DB::table($table)->where('branch_id', $branch->id)->exists(), 409, 'Business type is locked after provisioning. Create a separate branch instead.');
                    }
                }
                DB::table('pos_branches')->where('id', $id)->update($data + ['updated_at' => now()]);
            } elseif ($section === 'terminal') {
                $data = $request->validate(['code' => ['required', 'string', 'regex:/^[A-Za-z0-9_-]{1,30}$/D']]);
                abort_if(DB::table('pos_terminals')->where('branch_id', $branch->id)->where('code', $data['code'])->exists(), 422, 'Terminal code already exists.');
                $id = (string) Str::uuid();
                DB::table('pos_terminals')->insert($data + ['id' => $id, 'branch_id' => $branch->id, 'active' => true, 'created_at' => now(), 'updated_at' => now()]);
            } else {
                $data = $request->validate(['name' => 'required|string|max:255', 'email' => 'required|email|max:255|unique:pos_staff,email', 'password' => 'required|string|min:12|max:1000|confirmed', 'role' => 'required|in:cashier,kitchen,pharmacist,manager']);
                abort_if(($data['role'] === 'kitchen' && $branch->business_type !== 'restaurant') || ($data['role'] === 'pharmacist' && $branch->business_type !== 'pharmacy'), 422, 'Role does not match branch business type.');
                $id = (string) Str::uuid();
                Staff::create(collect($data)->only(['name', 'email', 'password', 'role'])->all() + ['id' => $id, 'branch_id' => $branch->id, 'active' => true]);
            }
            DB::table('pos_audit')->insert(['actor_id' => $request->user()->id, 'branch_id' => $branch->id, 'action' => 'configuration.'.$section, 'source_id' => $id, 'metadata' => json_encode(['section' => $section]), 'created_at' => now()]);
        });

        return redirect('/config')->with('status', 'บันทึกเรียบร้อยแล้ว');
    }
}
