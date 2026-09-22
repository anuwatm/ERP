<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ETaxConfig;
use App\Services\ETax\ETaxProviderGatewayService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ETaxWebhookController extends Controller
{
    public function handle(Request $request, string|ETaxConfig $config, ETaxProviderGatewayService $gateway): JsonResponse
    {
        $configModel = $config instanceof ETaxConfig && $config->exists
            ? $config
            : ETaxConfig::where('id', (string) $config)->firstOrFail();

        return $this->processWebhook($request, $configModel, $gateway);
    }

    public function handleDefault(Request $request, ETaxProviderGatewayService $gateway): JsonResponse
    {
        $orgId = $request->header('X-Org-Id') ?? $request->input('org_id');
        if (! $orgId) {
            return response()->json(['error' => 'Missing organization identifier.'], 400);
        }

        $config = ETaxConfig::where('org_id', $orgId)->first();
        if (! $config) {
            return response()->json(['error' => 'e-Tax configuration not found for organization.'], 404);
        }

        return $this->processWebhook($request, $config, $gateway);
    }

    private function processWebhook(Request $request, ETaxConfig $config, ETaxProviderGatewayService $gateway): JsonResponse
    {
        $settings = (array) ($config->provider_settings ?? []);
        $secret = $settings['webhook_secret'] ?? $settings['client_secret'] ?? config('services.etax.webhook_secret');

        $signatureHeader = $request->header('X-ETax-Signature')
            ?? $request->header('X-Signature')
            ?? $request->header('Signature')
            ?? $request->header('x-etax-signature');

        if (! empty($secret)) {
            if (! $signatureHeader) {
                return response()->json(['error' => 'Missing webhook signature header.'], 401);
            }

            $rawContent = (string) $request->getContent();
            // If raw content is empty, fallback to json_encode of all input
            if ($rawContent === '') {
                $rawContent = json_encode($request->all());
            }

            $computedSignature = hash_hmac('sha256', $rawContent, (string) $secret);
            $expectedVariants = [
                $computedSignature,
                'sha256='.$computedSignature,
            ];

            $matched = false;
            foreach ($expectedVariants as $expected) {
                if (hash_equals($expected, (string) $signatureHeader)) {
                    $matched = true;
                    break;
                }
            }

            if (! $matched) {
                return response()->json(['error' => 'Invalid webhook signature.'], 401);
            }
        }

        $payload = $request->all();
        $result = $gateway->handleWebhook($config, $payload);

        if (! $result['success']) {
            return response()->json($result, 422);
        }

        return response()->json($result, 200);
    }
}
