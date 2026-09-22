import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, useForm } from '@inertiajs/react';
import { useState } from 'react';

type ProviderPreset = {
    name: string;
    default_url: string;
    default_model: string;
    requires_key: boolean;
    description: string;
};

type ConfigData = {
    id?: string;
    provider_type: string;
    base_url: string;
    model_name: string;
    temperature: number;
    timeout_seconds: number;
    is_active: boolean;
    has_api_key: boolean;
    last_tested_at?: string | null;
    last_test_status?: string | null;
    last_test_message?: string | null;
};

export default function AiConfiguration({
    configuration,
    providers,
}: {
    configuration: ConfigData | null;
    providers: Record<string, ProviderPreset>;
}) {
    const defaultProvider = configuration?.provider_type ?? 'ollama';

    const form = useForm({
        provider_type: defaultProvider,
        base_url:
            configuration?.base_url ??
            providers[defaultProvider]?.default_url ??
            'http://localhost:11434/v1',
        api_key: '',
        model_name:
            configuration?.model_name ??
            providers[defaultProvider]?.default_model ??
            'qwen2.5-vl:7b',
        temperature: configuration?.temperature ?? 0.1,
        timeout_seconds: configuration?.timeout_seconds ?? 60,
        is_active: configuration?.is_active ?? true,
    });

    const [testLoading, setTestLoading] = useState(false);
    const [testResult, setTestResult] = useState<{
        success: boolean;
        message: string;
        latency_ms?: number;
    } | null>(null);

    const handleSelectPreset = (key: string) => {
        const preset = providers[key];
        if (!preset) return;
        form.setData({
            ...form.data,
            provider_type: key,
            base_url: preset.default_url,
            model_name: preset.default_model,
        });
        setTestResult(null);
    };

    const handleTestConnection = async () => {
        setTestLoading(true);
        setTestResult(null);
        try {
            const csrfToken =
                (
                    document.querySelector(
                        'meta[name="csrf-token"]',
                    ) as HTMLMetaElement
                )?.content ?? '';

            const res = await fetch(route('settings.ai-configuration.test'), {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': csrfToken,
                    Accept: 'application/json',
                },
                body: JSON.stringify({
                    provider_type: form.data.provider_type,
                    base_url: form.data.base_url,
                    api_key: form.data.api_key,
                    model_name: form.data.model_name,
                    timeout_seconds: 15,
                }),
            });

            const data = await res.json();
            setTestResult(data);
        } catch (err: unknown) {
            setTestResult({
                success: false,
                message:
                    err instanceof Error
                        ? err.message
                        : 'Network error during connection test.',
            });
        } finally {
            setTestLoading(false);
        }
    };

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        form.post(route('settings.ai-configuration.store'), {
            preserveScroll: true,
            onSuccess: () => {
                form.reset('api_key');
            },
        });
    };

    return (
        <AuthenticatedLayout
            header={
                <div>
                    <h2 className="text-xl font-bold leading-tight text-gray-800 dark:text-gray-100">
                        AI Configuration & Vision Engine
                    </h2>
                    <p className="text-xs text-gray-500 dark:text-gray-400">
                        Configure local AI (Ollama / LM Studio) for 100% data
                        privacy, or cloud OpenAI-compatible providers.
                    </p>
                </div>
            }
        >
            <Head title="AI Configuration" />

            <div className="mx-auto max-w-5xl space-y-6 p-6">
                {/* Information Card for Local AI & Privacy */}
                <div className="rounded-xl border border-blue-200 bg-blue-50/70 p-4 text-sm text-blue-900 shadow-sm dark:border-blue-900/50 dark:bg-blue-950/40 dark:text-blue-200">
                    <div className="flex items-start gap-3">
                        <span className="text-2xl">🛡️</span>
                        <div className="space-y-1">
                            <h4 className="font-semibold">
                                100% On-Premise Privacy Guarantee (Zero Data
                                Leakage)
                            </h4>
                            <p className="text-xs leading-relaxed text-blue-800 dark:text-blue-300">
                                When using <strong>Ollama</strong> or{' '}
                                <strong>LM Studio</strong> on your local server,
                                financial receipts and bank transfer slips stay
                                strictly inside your company network. No files
                                or metadata are transmitted over the internet.
                                For bilingual Thai/English receipt scanning, we
                                recommend running{' '}
                                <code className="rounded bg-blue-100 px-1 py-0.5 font-mono text-xs dark:bg-blue-900">
                                    ollama run qwen2.5-vl:7b
                                </code>
                                .
                            </p>
                        </div>
                    </div>
                </div>

                {/* Preset Selector */}
                <div className="rounded-xl border border-gray-200 bg-white p-6 shadow-sm dark:border-gray-800 dark:bg-gray-900">
                    <h3 className="mb-3 text-sm font-semibold text-gray-700 dark:text-gray-300">
                        Select AI Provider Preset:
                    </h3>
                    <div className="grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-6">
                        {Object.entries(providers).map(([key, item]) => {
                            const isSelected = form.data.provider_type === key;
                            return (
                                <button
                                    key={key}
                                    type="button"
                                    onClick={() => handleSelectPreset(key)}
                                    className={`flex flex-col items-center justify-center rounded-lg border p-3 text-center transition-all ${
                                        isSelected
                                            ? 'border-indigo-600 bg-indigo-50/60 font-semibold text-indigo-700 shadow-sm dark:border-indigo-500 dark:bg-indigo-950/40 dark:text-indigo-300'
                                            : 'border-gray-200 bg-white text-gray-700 hover:border-gray-300 hover:bg-gray-50 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-300 dark:hover:bg-gray-700'
                                    }`}
                                >
                                    <span className="text-xl">
                                        {key === 'ollama' && '🏠'}
                                        {key === 'lm_studio' && '💻'}
                                        {key === 'openrouter' && '🌐'}
                                        {key === 'openai' && '🤖'}
                                        {key === 'gemini' && '⚡'}
                                        {key === 'custom' && '⚙️'}
                                    </span>
                                    <span className="mt-1 text-xs">
                                        {item.name.split(' (')[0]}
                                    </span>
                                </button>
                            );
                        })}
                    </div>
                    {providers[form.data.provider_type] && (
                        <p className="mt-3 text-xs text-gray-500 dark:text-gray-400">
                            ℹ️ {providers[form.data.provider_type].description}
                        </p>
                    )}
                </div>

                {/* Configuration Form */}
                <form
                    onSubmit={handleSubmit}
                    className="space-y-6 rounded-xl border border-gray-200 bg-white p-6 shadow-sm dark:border-gray-800 dark:bg-gray-900"
                >
                    <div className="grid grid-cols-1 gap-6 md:grid-cols-2">
                        {/* Base URL */}
                        <div className="md:col-span-2">
                            <label className="block text-xs font-semibold text-gray-700 dark:text-gray-300">
                                Base URL (OpenAI-compatible endpoint) *
                            </label>
                            <input
                                type="text"
                                value={form.data.base_url}
                                onChange={(e) =>
                                    form.setData('base_url', e.target.value)
                                }
                                placeholder="http://localhost:11434/v1"
                                className="mt-1 block w-full rounded-lg border-gray-300 font-mono text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-100"
                                required
                            />
                            <p className="mt-1 text-xs text-gray-400">
                                Standard endpoints expect `/chat/completions`
                                for vision payload.
                            </p>
                        </div>

                        {/* Model Name */}
                        <div>
                            <label className="block text-xs font-semibold text-gray-700 dark:text-gray-300">
                                Model Name *
                            </label>
                            <input
                                type="text"
                                value={form.data.model_name}
                                onChange={(e) =>
                                    form.setData('model_name', e.target.value)
                                }
                                placeholder="qwen2.5-vl:7b"
                                className="mt-1 block w-full rounded-lg border-gray-300 font-mono text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-100"
                                required
                            />
                            {form.data.provider_type === 'openai' && (
                                <p className="mt-1 text-[11px] text-indigo-600 dark:text-indigo-400">
                                    💡 Recommended: <strong>gpt-4o-mini</strong> (fast & low cost) or <strong>gpt-4o</strong>. (Legacy gpt-4 & gpt-3.5 do not support vision).
                                </p>
                            )}
                        </div>

                        {/* API Key */}
                        <div>
                            <label className="block text-xs font-semibold text-gray-700 dark:text-gray-300">
                                API Key (Encrypted at rest)
                                {configuration?.has_api_key && (
                                    <span className="ml-2 inline-flex items-center rounded-full bg-green-100 px-2 py-0.5 text-[10px] font-medium text-green-800 dark:bg-green-900/50 dark:text-green-300">
                                        ✓ Key Stored
                                    </span>
                                )}
                            </label>
                            <input
                                type="password"
                                value={form.data.api_key}
                                onChange={(e) =>
                                    form.setData('api_key', e.target.value)
                                }
                                placeholder={
                                    configuration?.has_api_key
                                        ? '•••••••••••••••• (Leave blank to keep existing key)'
                                        : 'Leave blank for local Ollama / LM Studio'
                                }
                                className="mt-1 block w-full rounded-lg border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-100"
                            />
                        </div>

                        {/* Temperature */}
                        <div>
                            <label className="block text-xs font-semibold text-gray-700 dark:text-gray-300">
                                Temperature (0.00 = Deterministic, default 0.10)
                            </label>
                            <input
                                type="number"
                                step="0.05"
                                min="0"
                                max="1"
                                value={form.data.temperature}
                                onChange={(e) =>
                                    form.setData(
                                        'temperature',
                                        parseFloat(e.target.value) || 0,
                                    )
                                }
                                className="mt-1 block w-full rounded-lg border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-100"
                            />
                        </div>

                        {/* Timeout Seconds */}
                        <div>
                            <label className="block text-xs font-semibold text-gray-700 dark:text-gray-300">
                                Timeout (Seconds, recommended 60 for Vision)
                            </label>
                            <input
                                type="number"
                                min="5"
                                max="300"
                                value={form.data.timeout_seconds}
                                onChange={(e) =>
                                    form.setData(
                                        'timeout_seconds',
                                        parseInt(e.target.value) || 60,
                                    )
                                }
                                className="mt-1 block w-full rounded-lg border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-100"
                            />
                        </div>
                    </div>

                    {/* Enable Checkbox */}
                    <div className="flex items-center gap-2 pt-2">
                        <input
                            type="checkbox"
                            id="is_active"
                            checked={form.data.is_active}
                            onChange={(e) =>
                                form.setData('is_active', e.target.checked)
                            }
                            className="rounded border-gray-300 text-indigo-600 shadow-sm focus:ring-indigo-500 dark:border-gray-700 dark:bg-gray-800"
                        />
                        <label
                            htmlFor="is_active"
                            className="text-xs font-medium text-gray-700 dark:text-gray-300"
                        >
                            Enable this AI configuration for document OCR and
                            financial extractions
                        </label>
                    </div>

                    {/* Connection Test Banner */}
                    {testResult && (
                        <div
                            className={`rounded-lg border p-3 text-xs ${
                                testResult.success
                                    ? 'border-green-200 bg-green-50 text-green-800 dark:border-green-900/50 dark:bg-green-950/40 dark:text-green-300'
                                    : 'border-red-200 bg-red-50 text-red-800 dark:border-red-900/50 dark:bg-red-950/40 dark:text-red-300'
                            }`}
                        >
                            <span className="font-semibold">
                                {testResult.success ? '✅ ' : '❌ '}
                            </span>
                            {testResult.message}
                            {testResult.latency_ms && (
                                <span className="ml-2 font-mono text-[10px]">
                                    ({testResult.latency_ms}ms)
                                </span>
                            )}
                        </div>
                    )}

                    {/* Footer Actions */}
                    <div className="flex flex-wrap items-center justify-between gap-3 border-t border-gray-100 pt-4 dark:border-gray-800">
                        <button
                            type="button"
                            onClick={handleTestConnection}
                            disabled={testLoading}
                            className="inline-flex items-center gap-2 rounded-lg border border-gray-300 bg-white px-4 py-2 text-xs font-medium text-gray-700 shadow-sm transition hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-indigo-500 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-200 dark:hover:bg-gray-700"
                        >
                            {testLoading ? (
                                <>
                                    <svg
                                        className="h-3.5 w-3.5 animate-spin"
                                        viewBox="0 0 24 24"
                                    >
                                        <circle
                                            className="opacity-25"
                                            cx="12"
                                            cy="12"
                                            r="10"
                                            stroke="currentColor"
                                            strokeWidth="4"
                                            fill="none"
                                        />
                                        <path
                                            className="opacity-75"
                                            fill="currentColor"
                                            d="M4 12a8 8 0 018-8v8H4z"
                                        />
                                    </svg>
                                    Testing Connection...
                                </>
                            ) : (
                                <>🔌 Test Connection</>
                            )}
                        </button>

                        <button
                            type="submit"
                            disabled={form.processing}
                            className="inline-flex items-center gap-2 rounded-lg bg-indigo-600 px-5 py-2 text-xs font-semibold text-white shadow-sm transition hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-indigo-500 disabled:opacity-50"
                        >
                            {form.processing
                                ? 'Saving...'
                                : 'Save Configuration'}
                        </button>
                    </div>
                </form>
            </div>
        </AuthenticatedLayout>
    );
}
