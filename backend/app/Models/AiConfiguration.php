<?php

namespace App\Models;

use App\Models\Concerns\UsesOrderedUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AiConfiguration extends Model
{
    use UsesOrderedUuid;

    public const PROVIDERS = [
        'ollama' => [
            'name' => 'Ollama (Local AI)',
            'default_url' => 'http://localhost:11434/v1',
            'default_model' => 'qwen2.5-vl:7b',
            'requires_key' => false,
            'description' => '100% On-Premise Data Privacy (Zero external data leak). Recommended model: qwen2.5-vl:7b or llama3.2-vision.',
        ],
        'lm_studio' => [
            'name' => 'LM Studio (Local AI)',
            'default_url' => 'http://localhost:1234/v1',
            'default_model' => 'qwen2.5-vl-7b-instruct',
            'requires_key' => false,
            'description' => 'Local vision model loaded in LM Studio local developer server.',
        ],
        'openrouter' => [
            'name' => 'OpenRouter (Multi-Model Cloud)',
            'default_url' => 'https://openrouter.ai/api/v1',
            'default_model' => 'anthropic/claude-3.5-sonnet',
            'requires_key' => true,
            'description' => 'One unified API key for Claude 3.5, GPT-4o, Gemini Flash, and DeepSeek.',
        ],
        'openai' => [
            'name' => 'OpenAI (ChatGPT)',
            'default_url' => 'https://api.openai.com/v1',
            'default_model' => 'gpt-4o-mini',
            'requires_key' => true,
            'description' => 'Official OpenAI Vision API (GPT-4o, GPT-4o-mini).',
        ],
        'gemini' => [
            'name' => 'Google Gemini (OpenAI Compatible)',
            'default_url' => 'https://generativelanguage.googleapis.com/v1beta/openai',
            'default_model' => 'gemini-2.0-flash',
            'requires_key' => true,
            'description' => 'Google Gemini Flash vision via OpenAI-compatible endpoint.',
        ],
        'custom' => [
            'name' => 'Custom OpenAI-Compatible Endpoint',
            'default_url' => 'http://localhost:8000/v1',
            'default_model' => 'vllm-vision',
            'requires_key' => false,
            'description' => 'Any self-hosted vLLM, TGI, or custom OpenAI-compatible vision server.',
        ],
    ];

    protected $fillable = [
        'org_id',
        'provider_type',
        'base_url',
        'api_key',
        'model_name',
        'temperature',
        'timeout_seconds',
        'is_active',
        'last_tested_at',
        'last_test_status',
        'last_test_message',
    ];

    protected function casts(): array
    {
        return [
            'api_key' => 'encrypted',
            'temperature' => 'decimal:2',
            'timeout_seconds' => 'integer',
            'is_active' => 'boolean',
            'last_tested_at' => 'datetime',
        ];
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class, 'org_id');
    }
}
