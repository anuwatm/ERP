<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Models\AiConfiguration;
use App\Models\AuditLog;
use App\Services\Ocr\AiConnectionTesterService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AiConfigurationController extends Controller
{
    public function index(Request $request): Response
    {
        $user = $request->user();
        abort_unless($user->hasPermissionCode('ai_settings.manage'), 403);

        $config = AiConfiguration::where('org_id', $user->org_id)->first();

        return Inertia::render('Settings/AiConfiguration', [
            'configuration' => $config ? [
                'id' => $config->id,
                'provider_type' => $config->provider_type,
                'base_url' => $config->base_url,
                'model_name' => $config->model_name,
                'temperature' => (float) $config->temperature,
                'timeout_seconds' => (int) $config->timeout_seconds,
                'is_active' => (bool) $config->is_active,
                'has_api_key' => ! empty($config->api_key),
                'last_tested_at' => $config->last_tested_at?->toIso8601String(),
                'last_test_status' => $config->last_test_status,
                'last_test_message' => $config->last_test_message,
            ] : null,
            'providers' => AiConfiguration::PROVIDERS,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $user = $request->user();
        abort_unless($user->hasPermissionCode('ai_settings.manage'), 403);

        $validated = $request->validate([
            'provider_type' => ['required', 'string', 'in:'.implode(',', array_keys(AiConfiguration::PROVIDERS))],
            'base_url' => ['required', 'string', 'max:255'],
            'api_key' => ['nullable', 'string', 'max:500'],
            'model_name' => ['required', 'string', 'max:100'],
            'temperature' => ['required', 'numeric', 'between:0,1'],
            'timeout_seconds' => ['required', 'integer', 'between:5,300'],
            'is_active' => ['boolean'],
        ]);

        $config = AiConfiguration::where('org_id', $user->org_id)->first();

        $data = [
            'org_id' => $user->org_id,
            'provider_type' => $validated['provider_type'],
            'base_url' => $validated['base_url'],
            'model_name' => $validated['model_name'],
            'temperature' => $validated['temperature'],
            'timeout_seconds' => $validated['timeout_seconds'],
            'is_active' => $validated['is_active'] ?? true,
        ];

        // Only update API key if provided
        if (! empty($validated['api_key'])) {
            $data['api_key'] = $validated['api_key'];
        } elseif (! $config) {
            $data['api_key'] = null;
        }

        if ($config) {
            $config->update($data);
        } else {
            $config = AiConfiguration::create($data);
        }

        AuditLog::create([
            'org_id' => $user->org_id,
            'actor_user_id' => $user->id,
            'action' => 'ai_configuration.update',
            'entity_type' => 'ai_configuration',
            'entity_id' => $config->id,
            'after_json' => [
                'provider_type' => $config->provider_type,
                'base_url' => $config->base_url,
                'model_name' => $config->model_name,
                'is_active' => $config->is_active,
            ],
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
        ]);

        return back()->with('success', 'AI Configuration saved successfully.');
    }

    public function testConnection(Request $request, AiConnectionTesterService $tester): JsonResponse
    {
        $user = $request->user();
        abort_unless($user->hasPermissionCode('ai_settings.manage'), 403);

        $validated = $request->validate([
            'provider_type' => ['nullable', 'string'],
            'base_url' => ['required', 'string'],
            'api_key' => ['nullable', 'string'],
            'model_name' => ['required', 'string'],
            'timeout_seconds' => ['nullable', 'integer'],
        ]);

        $config = AiConfiguration::where('org_id', $user->org_id)->first();

        $apiKey = $validated['api_key'] ?? null;
        if (empty($apiKey) && $config) {
            $apiKey = $config->api_key;
        }

        $dummyConfig = new AiConfiguration([
            'org_id' => $user->org_id,
            'provider_type' => $validated['provider_type'] ?? 'custom',
            'base_url' => $validated['base_url'],
            'api_key' => $apiKey,
            'model_name' => $validated['model_name'],
            'timeout_seconds' => $validated['timeout_seconds'] ?? 15,
        ]);

        if ($config) {
            $dummyConfig->id = $config->id;
            $dummyConfig->exists = true;
        }

        $result = $tester->testConnection($dummyConfig);

        return response()->json($result);
    }
}
