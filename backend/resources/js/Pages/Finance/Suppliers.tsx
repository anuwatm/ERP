import InputLabel from '@/Components/InputLabel';
import PrimaryButton from '@/Components/PrimaryButton';
import SecondaryButton from '@/Components/SecondaryButton';
import TextInput from '@/Components/TextInput';
import Badge from '@/Components/UI/Badge';
import Card from '@/Components/UI/Card';
import DataTable from '@/Components/UI/DataTable';
import PageHeader from '@/Components/UI/PageHeader';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, router, useForm } from '@inertiajs/react';
import { FormEvent, useState } from 'react';

type Supplier = {
    id: string;
    supplier_code: string;
    name: string;
    tax_id?: string | null;
    email?: string | null;
    phone?: string | null;
    address?: string | null;
    status: string;
};

type SupplierForm = {
    name: string;
    tax_id: string;
    email: string;
    phone: string;
    address: string;
    status: string;
};

const emptySupplier: SupplierForm = {
    name: '',
    tax_id: '',
    email: '',
    phone: '',
    address: '',
    status: 'active',
};

export default function Suppliers({ suppliers }: { suppliers: Supplier[] }) {
    const [editingSupplier, setEditingSupplier] = useState<Supplier | null>(
        null,
    );
    const form = useForm<SupplierForm>(emptySupplier);

    const submit = (event: FormEvent) => {
        event.preventDefault();
        const options = {
            preserveScroll: true,
            onSuccess: () => {
                form.setData(emptySupplier);
                setEditingSupplier(null);
            },
        };

        if (editingSupplier) {
            form.patch(route('suppliers.update', editingSupplier.id), options);
        } else {
            form.post(route('suppliers.store'), options);
        }
    };

    const edit = (supplier: Supplier) => {
        setEditingSupplier(supplier);
        form.setData({
            name: supplier.name,
            tax_id: supplier.tax_id ?? '',
            email: supplier.email ?? '',
            phone: supplier.phone ?? '',
            address: supplier.address ?? '',
            status: supplier.status,
        });
    };

    return (
        <AuthenticatedLayout>
            <Head title="Suppliers" />
            <div className="space-y-6">
                <PageHeader
                    title="Suppliers"
                    description="Vendor master data for purchase orders and expenses."
                />
                <div className="grid gap-6 xl:grid-cols-[minmax(0,1fr)_380px]">
                    <Card
                        title="Supplier List"
                        description="Org-scoped vendors"
                    >
                        <DataTable
                            data={suppliers}
                            keyExtractor={(row) => row.id}
                            columns={[
                                {
                                    header: 'Supplier',
                                    accessor: (row) => (
                                        <div>
                                            <div className="font-mono font-semibold text-slate-900 dark:text-white">
                                                {row.supplier_code}
                                            </div>
                                            <div>{row.name}</div>
                                        </div>
                                    ),
                                },
                                {
                                    header: 'Email',
                                    accessor: (row) => row.email ?? '-',
                                },
                                {
                                    header: 'Phone',
                                    accessor: (row) => row.phone ?? '-',
                                },
                                {
                                    header: 'Status',
                                    accessor: (row) => (
                                        <Badge
                                            variant={
                                                row.status === 'active'
                                                    ? 'success'
                                                    : 'neutral'
                                            }
                                            size="sm"
                                        >
                                            {row.status}
                                        </Badge>
                                    ),
                                },
                                {
                                    header: 'Actions',
                                    accessor: (row) => (
                                        <div className="flex flex-wrap gap-2">
                                            <SecondaryButton
                                                type="button"
                                                onClick={() => edit(row)}
                                            >
                                                Edit
                                            </SecondaryButton>
                                            <SecondaryButton
                                                type="button"
                                                onClick={() =>
                                                    router.delete(
                                                        route(
                                                            'suppliers.destroy',
                                                            row.id,
                                                        ),
                                                        {
                                                            preserveScroll: true,
                                                        },
                                                    )
                                                }
                                            >
                                                Delete
                                            </SecondaryButton>
                                        </div>
                                    ),
                                },
                            ]}
                        />
                    </Card>
                    <Card
                        title={
                            editingSupplier ? 'Edit Supplier' : 'New Supplier'
                        }
                        description="Code is generated by number sequence."
                    >
                        <form onSubmit={submit} className="space-y-3">
                            <Field
                                label="Name"
                                value={form.data.name}
                                onChange={(value) =>
                                    form.setData('name', value)
                                }
                            />
                            <Field
                                label="Tax ID"
                                value={form.data.tax_id}
                                onChange={(value) =>
                                    form.setData('tax_id', value)
                                }
                            />
                            <Field
                                label="Email"
                                value={form.data.email}
                                onChange={(value) =>
                                    form.setData('email', value)
                                }
                            />
                            <Field
                                label="Phone"
                                value={form.data.phone}
                                onChange={(value) =>
                                    form.setData('phone', value)
                                }
                            />
                            <div>
                                <InputLabel value="Status" />
                                <select
                                    className="mt-1 block w-full rounded-md border-slate-300 text-sm shadow-sm"
                                    value={form.data.status}
                                    onChange={(event) =>
                                        form.setData(
                                            'status',
                                            event.target.value,
                                        )
                                    }
                                >
                                    <option value="active">active</option>
                                    <option value="inactive">inactive</option>
                                </select>
                            </div>
                            <Field
                                label="Address"
                                value={form.data.address}
                                onChange={(value) =>
                                    form.setData('address', value)
                                }
                            />
                            <div className="flex justify-end gap-2">
                                {editingSupplier && (
                                    <SecondaryButton
                                        type="button"
                                        onClick={() => {
                                            setEditingSupplier(null);
                                            form.setData(emptySupplier);
                                        }}
                                    >
                                        Cancel
                                    </SecondaryButton>
                                )}
                                <PrimaryButton disabled={form.processing}>
                                    Save Supplier
                                </PrimaryButton>
                            </div>
                        </form>
                    </Card>
                </div>
            </div>
        </AuthenticatedLayout>
    );
}

function Field({
    label,
    value,
    onChange,
}: {
    label: string;
    value: string;
    onChange: (value: string) => void;
}) {
    return (
        <div>
            <InputLabel value={label} />
            <TextInput
                className="mt-1 block w-full"
                value={value}
                onChange={(event) => onChange(event.target.value)}
            />
        </div>
    );
}
