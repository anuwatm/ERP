<?php

namespace Pos\Http;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Pos\Models\Staff;

class SetupController
{
    public function show(Request $request)
    {
        $this->authorizeAccess($request);
        $ready = Schema::hasTable('pos_audit') && Schema::hasTable('pos_staff')
            && Schema::hasTable('pos_terminals') && Schema::hasTable('pos_branches');
        if ($ready) {
            $this->requireEmptyDatabase();
        }

        return response()->view('setup', ['ready' => $ready])->header('Cache-Control', 'private, no-store');
    }

    public function install(Request $request)
    {
        $token = $this->authorizeAccess($request);
        $submitted = $request->input('setup_token');
        abort_unless(is_string($submitted) && hash_equals($token, $submitted), 403);

        abort_unless(Schema::hasTable('pos_audit') && Schema::hasTable('pos_staff')
            && Schema::hasTable('pos_terminals') && Schema::hasTable('pos_branches'), 503, 'Run POS migrations first.');

        $code = ['required', 'string', 'regex:/^[A-Za-z0-9_-]{1,30}$/D'];
        $data = $request->validate([
            'branch_code' => $code,
            'branch_name' => 'required|string|max:255',
            'business_type' => 'required|in:retail,restaurant,pharmacy',
            'terminal_code' => $code,
            'name' => 'required|string|max:255',
            'email' => 'required|email|max:255',
            'password' => ['required', 'string', 'min:12', 'confirmed', function ($attribute, $value, $fail) {
                if (is_string($value) && (strlen($value) > 72 || str_contains($value, "\0"))) {
                    $fail('The password must fit within 72 bytes and contain no null bytes.');
                }
            }],
            'confirm_install' => 'required|in:INSTALL',
        ]);

        // The loopback installer has one local storage directory across web workers.
        $lock = fopen(storage_path('app/pos-setup.lock'), 'c');
        abort_if($lock === false, 503, 'POS setup storage is unavailable.');
        try {
            abort_unless(flock($lock, LOCK_EX | LOCK_NB), 409, 'POS setup is in progress.');
            DB::transaction(function () use ($data) {
                $this->requireEmptyDatabase();
                $branch = (string) Str::uuid();
                $terminal = (string) Str::uuid();
                $manager = (string) Str::uuid();
                $timestamps = ['created_at' => now(), 'updated_at' => now()];

                DB::table('pos_branches')->insert([
                    'id' => $branch, 'code' => $data['branch_code'], 'name' => $data['branch_name'],
                    'business_type' => $data['business_type'], 'active' => true,
                ] + $timestamps);
                DB::table('pos_terminals')->insert([
                    'id' => $terminal, 'branch_id' => $branch, 'code' => $data['terminal_code'], 'active' => true,
                ] + $timestamps);
                Staff::create([
                    'id' => $manager, 'branch_id' => $branch, 'name' => $data['name'],
                    'email' => $data['email'], 'password' => $data['password'], 'role' => 'manager', 'active' => true,
                ]);
                DB::table('pos_audit')->insert([
                    'actor_id' => $manager, 'branch_id' => $branch, 'action' => 'setup.install', 'source_id' => $terminal,
                    'metadata' => '{}', 'created_at' => now(),
                ]);
            });
        } finally {
            fclose($lock);
        }

        return response()->view('setup', ['ready' => true, 'installed' => true])
            ->header('Cache-Control', 'private, no-store');
    }

    private function authorizeAccess(Request $request): string
    {
        $token = config('pos.setup_token');
        abort_unless(app()->environment(['local', 'testing'])
            && in_array($request->ip(), ['127.0.0.1', '::1'], true)
            && in_array($request->getHost(), ['localhost', '127.0.0.1', '[::1]', '::1'], true)
            && is_string($token) && strlen($token) >= 32, 404);

        return $token;
    }

    private function requireEmptyDatabase(): void
    {
        foreach (['pos_branches', 'pos_terminals', 'pos_staff'] as $table) {
            abort_if(DB::table($table)->exists(), 409, 'POS is already configured. Sign in to manage it.');
        }
    }
}
