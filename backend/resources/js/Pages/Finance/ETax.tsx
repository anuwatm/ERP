import Badge from '@/Components/UI/Badge';
import Card from '@/Components/UI/Card';
import DataTable from '@/Components/UI/DataTable';
import PageHeader from '@/Components/UI/PageHeader';
import PrimaryButton from '@/Components/PrimaryButton';
import SecondaryButton from '@/Components/SecondaryButton';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, router, useForm } from '@inertiajs/react';

type Config = {
    id?: string;
    mode: string;
    provider_code?: string | null;
    certificate_reference?: string | null;
    certificate_expires_at?: string | null;
    signature_mode: string;
    api_url?: string;
    client_id?: string;
    has_client_secret?: boolean;
    has_webhook_secret?: boolean;
};

type Source = {
    id: string;
    invoice_no?: string;
    note_no?: string;
    type?: string;
    amount?: string;
    total?: string;
    payment_date?: string;
    issue_date?: string;
    status?: string;
};

type Document = {
    id: string;
    document_no: string;
    document_type: string;
    status: string;
    xml_sha256: string;
    last_error?: string | null;
    created_at: string;
    attempts: {
        id: string;
        status: string;
        response_message?: string | null;
        external_reference?: string | null;
        created_at: string;
    }[];
};

export default function ETax({
    config,
    documents,
    invoices,
    payments,
    notes,
    canManage,
    canSubmit,
}: {
    config: Config;
    documents: Document[];
    invoices: Source[];
    payments: Source[];
    notes: Source[];
    canManage: boolean;
    canSubmit: boolean;
}) {
    const settings = useForm({
        _method: 'patch',
        mode: config.mode,
        provider_code: config.provider_code ?? 'rd_api',
        certificate_reference: config.certificate_reference ?? '',
        certificate_expires_at:
            config.certificate_expires_at?.slice(0, 10) ?? '',
        signature_mode: config.signature_mode ?? 'xml_dsig',
        api_url: config.api_url ?? '',
        client_id: config.client_id ?? '',
        client_secret: '',
        webhook_secret: '',
    });

    const generation = useForm({
        source_type: 'invoice',
        source_id: invoices[0]?.id ?? '',
        document_type: 'tax_invoice',
    });

    const sources =
        generation.data.source_type === 'invoice'
            ? invoices
            : generation.data.source_type === 'payment'
              ? payments
              : notes;

    const getStatusBadgeVariant = (status: string) => {
        switch (status) {
            case 'accepted':
                return 'success';
            case 'submitted':
            case 'signed':
                return 'info';
            case 'rejected':
            case 'error':
                return 'danger';
            case 'queued':
                return 'warning';
            default:
                return 'neutral';
        }
    };

    return (
        <AuthenticatedLayout>
            <Head title="e-Tax & RD Prep" />
            <div className="space-y-6">
                <PageHeader
                    title="e-Tax & RD Prep"
                    description="Generate provider-mapping XML, XML-DSig enveloped signing, provider gateway delivery, and export withholding staging text."
                    actions={
                        <Badge
                            variant={
                                config.mode === 'provider'
                                    ? 'success'
                                    : config.mode === 'mock'
                                      ? 'warning'
                                      : 'neutral'
                            }
                        >
                            {config.mode === 'provider'
                                ? `Provider (${config.provider_code || 'RD API'})`
                                : config.mode}
                        </Badge>
                    }
                />
                <Card
                    title="Submission Configuration"
                    description="Certificate private keys must NEVER be stored in the database. Store only a vault/KMS reference. Provider secrets are encrypted at rest."
                >
                    <form
                        className="grid gap-3 md:grid-cols-2"
                        onSubmit={(event) => {
                            event.preventDefault();
                            settings.patch(route('e-tax.config.update'), {
                                preserveScroll: true,
                            });
                        }}
                    >
                        <div>
                            <label className="block text-xs font-medium text-slate-700 dark:text-slate-300 mb-1">
                                Operating Mode
                            </label>
                            <select
                                value={settings.data.mode}
                                onChange={(event) =>
                                    settings.setData('mode', event.target.value)
                                }
                                className="w-full rounded-md border-slate-300 text-sm dark:border-slate-700 dark:bg-slate-900"
                            >
                                <option value="disabled">Disabled</option>
                                <option value="manual_export">
                                    Manual XML Export
                                </option>
                                <option value="mock">Mock (no delivery)</option>
                                <option value="provider">
                                    Live Provider Gateway (RD API / INET /
                                    Sandbox)
                                </option>
                            </select>
                        </div>

                        <div>
                            <label className="block text-xs font-medium text-slate-700 dark:text-slate-300 mb-1">
                                Provider Selection
                            </label>
                            <select
                                value={settings.data.provider_code}
                                onChange={(event) =>
                                    settings.setData(
                                        'provider_code',
                                        event.target.value,
                                    )
                                }
                                disabled={settings.data.mode === 'mock'}
                                className="w-full rounded-md border-slate-300 text-sm dark:border-slate-700 dark:bg-slate-900"
                            >
                                <option value="rd_api">
                                    Revenue Department Web Service (RD API)
                                </option>
                                <option value="inet">
                                    Certified Provider (INET Gateway)
                                </option>
                                <option value="sandbox">Sandbox Gateway</option>
                                <option value="mock">Mock Simulator</option>
                            </select>
                        </div>

                        <div>
                            <label className="block text-xs font-medium text-slate-700 dark:text-slate-300 mb-1">
                                Digital Signature Pipeline
                            </label>
                            <select
                                value={settings.data.signature_mode}
                                onChange={(event) =>
                                    settings.setData(
                                        'signature_mode',
                                        event.target.value,
                                    )
                                }
                                className="w-full rounded-md border-slate-300 text-sm dark:border-slate-700 dark:bg-slate-900"
                            >
                                <option value="xml_dsig">
                                    ETDA Enveloped XML-DSig (RSA-SHA256)
                                </option>
                                <option value="external">
                                    External HSM / Vault Signing
                                </option>
                                <option value="none">
                                    None (Unsigned / Mock)
                                </option>
                            </select>
                        </div>

                        <div>
                            <label className="block text-xs font-medium text-slate-700 dark:text-slate-300 mb-1">
                                Certificate Reference (Vault / ARN)
                            </label>
                            <input
                                value={settings.data.certificate_reference}
                                onChange={(event) =>
                                    settings.setData(
                                        'certificate_reference',
                                        event.target.value,
                                    )
                                }
                                placeholder="vault:secret/etax/cert-2026"
                                className="w-full rounded-md border-slate-300 text-sm dark:border-slate-700 dark:bg-slate-900"
                            />
                        </div>

                        <div>
                            <label className="block text-xs font-medium text-slate-700 dark:text-slate-300 mb-1">
                                Certificate Expiry Date
                            </label>
                            <input
                                type="date"
                                value={settings.data.certificate_expires_at}
                                onChange={(event) =>
                                    settings.setData(
                                        'certificate_expires_at',
                                        event.target.value,
                                    )
                                }
                                className="w-full rounded-md border-slate-300 text-sm dark:border-slate-700 dark:bg-slate-900"
                            />
                        </div>

                        {settings.data.mode === 'provider' && (
                            <>
                                <div>
                                    <label className="block text-xs font-medium text-slate-700 dark:text-slate-300 mb-1">
                                        Provider Gateway Endpoint URL
                                    </label>
                                    <input
                                        value={settings.data.api_url}
                                        onChange={(event) =>
                                            settings.setData(
                                                'api_url',
                                                event.target.value,
                                            )
                                        }
                                        placeholder="https://etax.rd.go.th/api/v1"
                                        className="w-full rounded-md border-slate-300 text-sm dark:border-slate-700 dark:bg-slate-900"
                                    />
                                </div>
                                <div>
                                    <label className="block text-xs font-medium text-slate-700 dark:text-slate-300 mb-1">
                                        Client ID / App Key
                                    </label>
                                    <input
                                        value={settings.data.client_id}
                                        onChange={(event) =>
                                            settings.setData(
                                                'client_id',
                                                event.target.value,
                                            )
                                        }
                                        placeholder="ETAX_CLIENT_ID"
                                        className="w-full rounded-md border-slate-300 text-sm dark:border-slate-700 dark:bg-slate-900"
                                    />
                                </div>
                                <div>
                                    <label className="block text-xs font-medium text-slate-700 dark:text-slate-300 mb-1">
                                        Client Secret (Encrypted)
                                    </label>
                                    <input
                                        type="password"
                                        value={settings.data.client_secret}
                                        onChange={(event) =>
                                            settings.setData(
                                                'client_secret',
                                                event.target.value,
                                            )
                                        }
                                        placeholder={
                                            config.has_client_secret
                                                ? '•••••••• (Stored securely. Enter to replace)'
                                                : 'Enter client secret'
                                        }
                                        className="w-full rounded-md border-slate-300 text-sm dark:border-slate-700 dark:bg-slate-900"
                                    />
                                </div>
                                <div>
                                    <label className="block text-xs font-medium text-slate-700 dark:text-slate-300 mb-1">
                                        Webhook HMAC Secret
                                    </label>
                                    <input
                                        type="password"
                                        value={settings.data.webhook_secret}
                                        onChange={(event) =>
                                            settings.setData(
                                                'webhook_secret',
                                                event.target.value,
                                            )
                                        }
                                        placeholder={
                                            config.has_webhook_secret
                                                ? '•••••••• (Stored securely. Enter to replace)'
                                                : 'Enter webhook HMAC secret'
                                        }
                                        className="w-full rounded-md border-slate-300 text-sm dark:border-slate-700 dark:bg-slate-900"
                                    />
                                </div>
                            </>
                        )}

                        <div className="md:col-span-2 mt-2">
                            <PrimaryButton
                                disabled={settings.processing || !canManage}
                            >
                                Save Configuration
                            </PrimaryButton>
                        </div>
                        {Object.values(settings.errors).map((error) => (
                            <p
                                role="alert"
                                className="text-sm text-red-600 md:col-span-2"
                                key={error}
                            >
                                {error}
                            </p>
                        ))}
                    </form>
                </Card>

                <Card
                    title="Prepare Internal XML"
                    description="XML uses the ETDA provider-mapping profile. Documents can be digitally signed before submission to the gateway."
                >
                    <form
                        className="grid gap-3 md:grid-cols-4"
                        onSubmit={(event) => {
                            event.preventDefault();
                            generation.post(route('e-tax.documents.generate'), {
                                preserveScroll: true,
                            });
                        }}
                    >
                        <select
                            value={generation.data.source_type}
                            onChange={(event) => {
                                const sourceType = event.target.value;
                                const list =
                                    sourceType === 'invoice'
                                        ? invoices
                                        : sourceType === 'payment'
                                          ? payments
                                          : notes;
                                generation.setData({
                                    ...generation.data,
                                    source_type: sourceType,
                                    source_id: list[0]?.id ?? '',
                                    document_type:
                                        sourceType === 'invoice'
                                            ? 'tax_invoice'
                                            : sourceType === 'payment'
                                              ? 'receipt'
                                              : 'credit_note',
                                });
                            }}
                            className="rounded-md border-slate-300 text-sm dark:border-slate-700 dark:bg-slate-900"
                        >
                            <option value="invoice">Invoice</option>
                            <option value="payment">Receipt Payment</option>
                            <option value="credit_debit_note">
                                Credit / Debit Note
                            </option>
                        </select>
                        <select
                            value={generation.data.source_id}
                            onChange={(event) =>
                                generation.setData(
                                    'source_id',
                                    event.target.value,
                                )
                            }
                            className="rounded-md border-slate-300 text-sm dark:border-slate-700 dark:bg-slate-900"
                        >
                            {sources.map((source) => (
                                <option key={source.id} value={source.id}>
                                    {source.invoice_no ??
                                        source.note_no ??
                                        `Receipt ${source.id.slice(0, 8)}`}
                                </option>
                            ))}
                        </select>
                        <select
                            value={generation.data.document_type}
                            onChange={(event) =>
                                generation.setData(
                                    'document_type',
                                    event.target.value,
                                )
                            }
                            className="rounded-md border-slate-300 text-sm dark:border-slate-700 dark:bg-slate-900"
                        >
                            <option value="tax_invoice">Tax Invoice</option>
                            <option value="receipt">Receipt</option>
                            <option value="credit_note">Credit Note</option>
                            <option value="debit_note">Debit Note</option>
                        </select>
                        <PrimaryButton
                            disabled={
                                generation.processing ||
                                !canManage ||
                                !generation.data.source_id
                            }
                        >
                            Generate XML
                        </PrimaryButton>
                        {Object.values(generation.errors).map((error) => (
                            <p
                                role="alert"
                                className="text-sm text-red-600 md:col-span-4"
                                key={error}
                            >
                                {error}
                            </p>
                        ))}
                    </form>
                </Card>

                <Card
                    title="RD Prep Staging Export"
                    description="Pipe-delimited draft for ภ.ง.ด. 3/53. Verify against the current official RD format before upload."
                >
                    <div className="flex gap-2">
                        <a href={route('e-tax.rd-prep', { form: 'pnd3' })}>
                            <SecondaryButton type="button">
                                Export ภ.ง.ด. 3
                            </SecondaryButton>
                        </a>
                        <a href={route('e-tax.rd-prep', { form: 'pnd53' })}>
                            <SecondaryButton type="button">
                                Export ภ.ง.ด. 53
                            </SecondaryButton>
                        </a>
                    </div>
                </Card>

                <Card
                    title="e-Tax Documents"
                    description="Monitor status lifecycle: Generated → Signed → Submitted → Accepted/Rejected."
                >
                    <DataTable
                        data={documents}
                        keyExtractor={(document) => document.id}
                        columns={[
                            {
                                header: 'Document',
                                accessor: (document) => (
                                    <div>
                                        <div className="font-mono font-semibold">
                                            {document.document_no}
                                        </div>
                                        <div className="text-xs text-slate-500">
                                            {document.document_type}
                                        </div>
                                    </div>
                                ),
                            },
                            {
                                header: 'Status',
                                accessor: (document) => (
                                    <Badge
                                        variant={getStatusBadgeVariant(
                                            document.status,
                                        )}
                                    >
                                        {document.status === 'simulated'
                                            ? 'Simulated (Mock)'
                                            : document.status}
                                    </Badge>
                                ),
                            },
                            {
                                header: 'Attempts & Reference',
                                accessor: (document) => (
                                    <div>
                                        <span className="text-xs font-semibold text-slate-700 dark:text-slate-300">
                                            {document.attempts.length} / 3
                                            attempts
                                        </span>
                                        {document.last_error && (
                                            <p className="max-w-xs break-words text-xs text-red-600">
                                                {document.last_error}
                                            </p>
                                        )}
                                        {document.attempts.map((attempt) => (
                                            <div
                                                className="text-xs text-slate-500 mt-1"
                                                key={attempt.id}
                                            >
                                                <span className="font-medium">
                                                    {attempt.status}
                                                </span>
                                                {attempt.external_reference && (
                                                    <span>
                                                        {' '}
                                                        [
                                                        {
                                                            attempt.external_reference
                                                        }
                                                        ]
                                                    </span>
                                                )}
                                                : {attempt.response_message}
                                            </div>
                                        ))}
                                    </div>
                                ),
                            },
                            {
                                header: 'Action',
                                accessor: (document) => (
                                    <div className="flex flex-wrap gap-2">
                                        <a
                                            href={route(
                                                'e-tax.documents.download',
                                                document.id,
                                            )}
                                        >
                                            <SecondaryButton type="button">
                                                XML
                                            </SecondaryButton>
                                        </a>

                                        {canManage &&
                                            document.status === 'generated' && (
                                                <SecondaryButton
                                                    type="button"
                                                    onClick={() =>
                                                        router.post(
                                                            route(
                                                                'e-tax.documents.sign',
                                                                document.id,
                                                            ),
                                                        )
                                                    }
                                                >
                                                    Sign XML
                                                </SecondaryButton>
                                            )}

                                        {canSubmit &&
                                            ['mock', 'provider'].includes(
                                                config.mode,
                                            ) &&
                                            document.attempts.length < 3 &&
                                            [
                                                'generated',
                                                'signed',
                                                'error',
                                                'rejected',
                                            ].includes(document.status) && (
                                                <SecondaryButton
                                                    type="button"
                                                    onClick={() =>
                                                        router.post(
                                                            route(
                                                                'e-tax.documents.submit',
                                                                document.id,
                                                            ),
                                                        )
                                                    }
                                                >
                                                    {document.status ===
                                                        'error' ||
                                                    document.status ===
                                                        'rejected'
                                                        ? 'Retry Submit'
                                                        : config.mode ===
                                                            'provider'
                                                          ? 'Submit Gateway'
                                                          : 'Run Mock'}
                                                </SecondaryButton>
                                            )}

                                        {canSubmit &&
                                            config.mode === 'provider' &&
                                            [
                                                'submitted',
                                                'queued',
                                                'error',
                                            ].includes(document.status) &&
                                            document.attempts.length > 0 && (
                                                <SecondaryButton
                                                    type="button"
                                                    onClick={() =>
                                                        router.post(
                                                            route(
                                                                'e-tax.documents.inquire',
                                                                document.id,
                                                            ),
                                                        )
                                                    }
                                                >
                                                    Inquire Status
                                                </SecondaryButton>
                                            )}
                                    </div>
                                ),
                            },
                        ]}
                    />
                </Card>
            </div>
        </AuthenticatedLayout>
    );
}
