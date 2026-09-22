<?php

namespace App\Services\Ocr;

use App\Models\AiConfiguration;
use Illuminate\Support\Facades\Http;

class AiConnectionTesterService
{
    /**
     * Resolve the chat completions endpoint from various URL formats.
     */
    public static function resolveChatEndpoint(string $rawUrl): string
    {
        $url = trim($rawUrl);
        $url = rtrim($url, '/');

        if (str_ends_with($url, '/chat/completions')) {
            return $url;
        }

        // Handle OpenAI official host missing /v1
        if (str_contains($url, 'api.openai.com') && ! str_contains($url, '/v1')) {
            $url .= '/v1';
        }

        // Handle Ollama host missing /v1
        if (str_contains($url, '11434') && ! str_contains($url, '/v1')) {
            $url .= '/v1';
        }

        return "{$url}/chat/completions";
    }

    /**
     * Test connection to the AI endpoint configured in the AiConfiguration model.
     */
    public function testConnection(AiConfiguration $config): array
    {
        if (UniversalOpenAiVisionAdapter::isCloudProvider($config) && ! UniversalOpenAiVisionAdapter::isCloudAllowed($config->org_id)) {
            $policyMsg = 'Connection blocked: Cloud AI processing is disabled for this organization by compliance policy.';
            $config->update([
                'last_tested_at' => now(),
                'last_test_status' => 'failed',
                'last_test_message' => $policyMsg,
            ]);

            return [
                'success' => false,
                'latency_ms' => 0,
                'message' => $policyMsg,
            ];
        }

        $endpoint = self::resolveChatEndpoint($config->base_url ?? '');

        $headers = [
            'Content-Type' => 'application/json',
        ];

        $apiKey = trim((string) ($config->api_key ?? ''));
        if (! empty($apiKey)) {
            $headers['Authorization'] = 'Bearer '.$apiKey;
        }

        $model = trim((string) ($config->model_name ?: 'gpt-4o-mini'));
        $isReasoningModel = str_starts_with($model, 'o1') || str_starts_with($model, 'o3');

        $payload = [
            'model' => $model,
            'messages' => [
                [
                    'role' => 'user',
                    'content' => 'Ping. Reply with "pong" only.',
                ],
            ],
        ];

        if ($isReasoningModel) {
            $payload['max_completion_tokens'] = 10;
        } else {
            $payload['max_tokens'] = 10;
        }

        $start = microtime(true);

        try {
            $response = Http::withHeaders($headers)
                ->timeout(min((int) ($config->timeout_seconds ?? 15), 15))
                ->post($endpoint, $payload);

            $latencyMs = (int) round((microtime(true) - $start) * 1000);

            if ($response->successful()) {
                $data = $response->json();
                $reply = trim($data['choices'][0]['message']['content'] ?? 'Connected successfully');

                $config->update([
                    'last_tested_at' => now(),
                    'last_test_status' => 'success',
                    'last_test_message' => "Connected in {$latencyMs}ms. Response: {$reply}",
                ]);

                return [
                    'success' => true,
                    'latency_ms' => $latencyMs,
                    'message' => "Connection successful ({$latencyMs}ms). AI replied: {$reply}",
                ];
            }

            // Extract detailed error message from OpenAI / provider JSON response
            $json = $response->json();
            $errMsg = null;
            if (is_array($json) && isset($json['error'])) {
                if (is_array($json['error']) && isset($json['error']['message'])) {
                    $errMsg = $json['error']['message'];
                } elseif (is_string($json['error'])) {
                    $errMsg = $json['error'];
                }
            }

            if (! $errMsg) {
                $errMsg = substr($response->body(), 0, 200);
            }

            $sanitizedErrMsg = OcrProcessingService::sanitizeErrorMessage((string) $errMsg);
            $fullErrMsg = "HTTP {$response->status()}: {$sanitizedErrMsg}";

            $config->update([
                'last_tested_at' => now(),
                'last_test_status' => 'failed',
                'last_test_message' => $fullErrMsg,
            ]);

            return [
                'success' => false,
                'latency_ms' => $latencyMs,
                'message' => "Connection failed: {$fullErrMsg}",
            ];
        } catch (\Throwable $e) {
            $latencyMs = (int) round((microtime(true) - $start) * 1000);
            $sanitizedErrMsg = OcrProcessingService::sanitizeErrorMessage($e->getMessage());

            $config->update([
                'last_tested_at' => now(),
                'last_test_status' => 'failed',
                'last_test_message' => $sanitizedErrMsg,
            ]);

            return [
                'success' => false,
                'latency_ms' => $latencyMs,
                'message' => "Connection error: {$sanitizedErrMsg}",
            ];
        }
    }
}
