import InputError from '@/Components/InputError';
import InputLabel from '@/Components/InputLabel';
import PrimaryButton from '@/Components/PrimaryButton';
import TextInput from '@/Components/TextInput';
import Badge from '@/Components/UI/Badge';
import Card from '@/Components/UI/Card';
import PageHeader from '@/Components/UI/PageHeader';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, useForm } from '@inertiajs/react';
import { FormEventHandler } from 'react';

type Organization = {
    name: string;
    legal_name?: string | null;
    tax_id?: string | null;
    email?: string | null;
    phone?: string | null;
    address?: string | null;
    currency: string;
    timezone: string;
    status: string;
};

export default function Organization({
    organization,
}: {
    organization: Organization;
}) {
    const { data, setData, patch, processing, errors } = useForm({
        name: organization.name ?? '',
        legal_name: organization.legal_name ?? '',
        tax_id: organization.tax_id ?? '',
        email: organization.email ?? '',
        phone: organization.phone ?? '',
        address: organization.address ?? '',
    });

    const submit: FormEventHandler = (event) => {
        event.preventDefault();
        patch(route('settings.organization.update'));
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
                    description="Legal profile and official registration data"
                >
                    <form onSubmit={submit} className="space-y-4 max-w-2xl">
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
                                    className="text-xs font-semibold uppercase text-slate-700 mb-1"
                                />
                                <TextInput
                                    id={field.key}
                                    className="block w-full border-slate-300 rounded-md shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm"
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

                        <div className="pt-2 flex justify-end">
                            <PrimaryButton
                                disabled={processing}
                                className="bg-indigo-600 hover:bg-indigo-700"
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
