import InputError from '@/Components/InputError';
import InputLabel from '@/Components/InputLabel';
import PrimaryButton from '@/Components/PrimaryButton';
import TextInput from '@/Components/TextInput';
import Badge from '@/Components/UI/Badge';
import Card from '@/Components/UI/Card';
import PageHeader from '@/Components/UI/PageHeader';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, useForm } from '@inertiajs/react';
import { ChangeEventHandler, FormEventHandler, useState } from 'react';

type Organization = {
    name: string;
    legal_name?: string | null;
    tax_id?: string | null;
    email?: string | null;
    phone?: string | null;
    address?: string | null;
    logo_url?: string | null;
    currency: string;
    timezone: string;
    status: string;
};

type OrganizationForm = {
    _method: 'patch';
    name: string;
    legal_name: string;
    tax_id: string;
    email: string;
    phone: string;
    address: string;
    logo: File | null;
};
type NumberingFormat = {
    enabled: boolean;
    format: string;
    reset: string;
    scope: string;
};

export default function Organization({
    organization,
    numberingFormats,
    numberingPreviews,
}: {
    organization: Organization;
    numberingFormats: Record<string, NumberingFormat>;
    numberingPreviews: Record<string, string>;
}) {
    const [selectedLogoFile, setSelectedLogoFile] = useState<File | null>(null);
    const logoPreview = selectedLogoFile
        ? URL.createObjectURL(selectedLogoFile)
        : (organization.logo_url ?? null);

    const { data, setData, post, processing, errors } =
        useForm<OrganizationForm>({
            _method: 'patch',
            name: organization.name ?? '',
            legal_name: organization.legal_name ?? '',
            tax_id: organization.tax_id ?? '',
            email: organization.email ?? '',
            phone: organization.phone ?? '',
            address: organization.address ?? '',
            logo: null,
        });
    const numberingForm = useForm({ formats: numberingFormats });

    const updateLogo: ChangeEventHandler<HTMLInputElement> = (event) => {
        const file = event.target.files?.[0] ?? null;
        setData('logo', file);
        setSelectedLogoFile(file);
    };

    const submit: FormEventHandler = (event) => {
        event.preventDefault();
        post(route('settings.organization.update'), {
            forceFormData: true,
            preserveScroll: true,
        });
    };

    return (
        <AuthenticatedLayout>
            <Head title="Organization Profile" />

            <div className="space-y-6">
                <PageHeader
                    title="Organization Profile Settings"
                    description="Manage company details, tax registration info, and contact profile."
                    actions={
                        <Badge variant="success" dot>
                            {organization.currency} ({organization.timezone})
                        </Badge>
                    }
                />

                <Card
                    title="Company Identity & Information"
                    description="Legal profile, company logo, and official registration data"
                >
                    <form onSubmit={submit} className="max-w-2xl space-y-4">
                        <div>
                            <InputLabel
                                htmlFor="logo"
                                value="Company Logo"
                                className="mb-1 text-xs font-semibold uppercase text-slate-700"
                            />
                            <div className="flex items-center gap-4">
                                <div className="flex h-20 w-20 items-center justify-center overflow-hidden rounded-md border border-slate-200 bg-slate-50 text-xs font-semibold text-slate-500">
                                    {logoPreview ? (
                                        <img
                                            src={logoPreview}
                                            alt="Company logo preview"
                                            className="h-full w-full object-contain"
                                        />
                                    ) : (
                                        'Logo'
                                    )}
                                </div>
                                <input
                                    id="logo"
                                    type="file"
                                    accept="image/png,image/jpeg,image/webp"
                                    className="block w-full text-sm text-slate-700 file:mr-4 file:rounded-md file:border-0 file:bg-indigo-50 file:px-4 file:py-2 file:text-sm file:font-semibold file:text-indigo-700 hover:file:bg-indigo-100"
                                    onChange={updateLogo}
                                />
                            </div>
                            <p className="mt-1 text-xs text-slate-500">
                                JPG, PNG, or WebP. Maximum 2MB.
                            </p>
                            <InputError
                                message={errors.logo}
                                className="mt-1"
                            />
                        </div>

                        {(
                            [
                                {
                                    key: 'name',
                                    label: 'Organization Display Name',
                                    placeholder: 'e.g. Local Devine Co., Ltd.',
                                },
                                {
                                    key: 'legal_name',
                                    label: 'Legal Registered Name',
                                    placeholder:
                                        'e.g. Local Devine Thailand Company Limited',
                                },
                                {
                                    key: 'tax_id',
                                    label: 'Tax Identification Number (Tax ID)',
                                    placeholder: '13 digits',
                                },
                                {
                                    key: 'email',
                                    label: 'Official Contact Email',
                                    placeholder: 'contact@company.com',
                                },
                                {
                                    key: 'phone',
                                    label: 'Official Phone Number',
                                    placeholder: '+66 2 123 4567',
                                },
                                {
                                    key: 'address',
                                    label: 'Registered Business Address',
                                    placeholder: 'Full address details',
                                },
                            ] as const
                        ).map((field) => (
                            <div key={field.key}>
                                <InputLabel
                                    htmlFor={field.key}
                                    value={field.label}
                                    className="mb-1 text-xs font-semibold uppercase text-slate-700"
                                />
                                <TextInput
                                    id={field.key}
                                    className="block w-full rounded-md border-slate-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                                    value={data[field.key]}
                                    placeholder={field.placeholder}
                                    onChange={(event) =>
                                        setData(field.key, event.target.value)
                                    }
                                    required={field.key === 'name'}
                                />
                                <InputError
                                    message={errors[field.key]}
                                    className="mt-1"
                                />
                            </div>
                        ))}

                        <div className="flex justify-end pt-2">
                            <PrimaryButton
                                disabled={processing}
                                icon={
                                    <svg
                                        className="h-4 w-4"
                                        fill="none"
                                        viewBox="0 0 24 24"
                                        stroke="currentColor"
                                    >
                                        <path
                                            strokeLinecap="round"
                                            strokeLinejoin="round"
                                            strokeWidth="2"
                                            d="M5 13l4 4L19 7"
                                        />
                                    </svg>
                                }
                            >
                                Save Organization Profile
                            </PrimaryButton>
                        </div>
                    </form>
                </Card>

                <Card
                    title="Document Numbering"
                    description="Configure document number formats and reset periods"
                >
                    <form
                        className="space-y-4"
                        onSubmit={(event) => {
                            event.preventDefault();
                            numberingForm.patch(
                                route('settings.organization.numbering.update'),
                                { preserveScroll: true },
                            );
                        }}
                    >
                        <div className="grid gap-3 lg:grid-cols-2">
                            {Object.entries(numberingForm.data.formats).map(
                                ([docType, config]) => (
                                    <div
                                        key={docType}
                                        className="rounded-md border border-slate-200 p-3 dark:border-slate-800"
                                    >
                                        <div className="mb-3 flex items-center justify-between gap-3">
                                            <div className="font-semibold text-slate-900 dark:text-white">
                                                {docType.replaceAll('_', ' ')}
                                            </div>
                                            <label className="flex items-center gap-2 text-sm text-slate-600 dark:text-slate-300">
                                                <input
                                                    type="checkbox"
                                                    checked={config.enabled}
                                                    onChange={(event) =>
                                                        numberingForm.setData(
                                                            'formats',
                                                            {
                                                                ...numberingForm
                                                                    .data
                                                                    .formats,
                                                                [docType]: {
                                                                    ...config,
                                                                    enabled:
                                                                        event
                                                                            .target
                                                                            .checked,
                                                                },
                                                            },
                                                        )
                                                    }
                                                />
                                                enabled
                                            </label>
                                        </div>
                                        <TextInput
                                            className="block w-full"
                                            value={config.format}
                                            onChange={(event) =>
                                                numberingForm.setData(
                                                    'formats',
                                                    {
                                                        ...numberingForm.data
                                                            .formats,
                                                        [docType]: {
                                                            ...config,
                                                            format: event.target
                                                                .value,
                                                        },
                                                    },
                                                )
                                            }
                                        />
                                        <div className="mt-2 grid grid-cols-2 gap-2">
                                            <select
                                                className="rounded-md border-slate-300 text-sm shadow-sm dark:border-slate-700 dark:bg-slate-900 dark:text-white"
                                                value={config.reset}
                                                onChange={(event) =>
                                                    numberingForm.setData(
                                                        'formats',
                                                        {
                                                            ...numberingForm
                                                                .data.formats,
                                                            [docType]: {
                                                                ...config,
                                                                reset: event
                                                                    .target
                                                                    .value,
                                                            },
                                                        },
                                                    )
                                                }
                                            >
                                                <option value="none">
                                                    none
                                                </option>
                                                <option value="yearly">
                                                    yearly
                                                </option>
                                                <option value="monthly">
                                                    monthly
                                                </option>
                                                <option value="daily">
                                                    daily
                                                </option>
                                            </select>
                                            <select
                                                className="rounded-md border-slate-300 text-sm shadow-sm dark:border-slate-700 dark:bg-slate-900 dark:text-white"
                                                value={config.scope}
                                                onChange={(event) =>
                                                    numberingForm.setData(
                                                        'formats',
                                                        {
                                                            ...numberingForm
                                                                .data.formats,
                                                            [docType]: {
                                                                ...config,
                                                                scope: event
                                                                    .target
                                                                    .value,
                                                            },
                                                        },
                                                    )
                                                }
                                            >
                                                <option value="organization">
                                                    organization
                                                </option>
                                                <option value="branch">
                                                    branch
                                                </option>
                                            </select>
                                        </div>
                                        <div className="mt-2 text-xs text-slate-500 dark:text-slate-300">
                                            Preview:{' '}
                                            <span className="font-mono font-semibold">
                                                {numberingPreviews[docType] ??
                                                    '-'}
                                            </span>
                                        </div>
                                    </div>
                                ),
                            )}
                        </div>
                        <div className="flex justify-end">
                            <PrimaryButton disabled={numberingForm.processing}>
                                Save Numbering
                            </PrimaryButton>
                        </div>
                    </form>
                </Card>
            </div>
        </AuthenticatedLayout>
    );
}
