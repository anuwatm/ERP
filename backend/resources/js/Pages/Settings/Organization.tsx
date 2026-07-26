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

export default function Organization({
    organization,
}: {
    organization: Organization;
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
            </div>
        </AuthenticatedLayout>
    );
}
