import InputError from '@/Components/InputError';
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
import { money } from '@/Utils/format';

type Product = {
    id: string;
    sku?: string | null;
    name: string;
    type: string;
    category?: string | null;
    unit?: string | null;
    price: string;
    cost: string;
    is_active: boolean;
    description?: string | null;
};

type ProductForm = {
    sku: string;
    name: string;
    type: string;
    category: string;
    unit: string;
    price: string;
    cost: string;
    is_active: boolean;
    description: string;
};

const emptyProduct: ProductForm = {
    sku: '',
    name: '',
    type: 'service',
    category: '',
    unit: '',
    price: '0.00',
    cost: '0.00',
    is_active: true,
    description: '',
};

export default function Products({ products }: { products: Product[] }) {
    const [editingProduct, setEditingProduct] = useState<Product | null>(null);
    const form = useForm<ProductForm>(emptyProduct);

    const submit = (event: FormEvent) => {
        event.preventDefault();
        const options = {
            preserveScroll: true,
            onSuccess: () => {
                form.setData(emptyProduct);
                setEditingProduct(null);
            },
        };

        if (editingProduct) {
            form.patch(route('products.update', editingProduct.id), options);
        } else {
            form.post(route('products.store'), options);
        }
    };

    const editProduct = (product: Product) => {
        setEditingProduct(product);
        form.setData({
            sku: product.sku ?? '',
            name: product.name ?? '',
            type: product.type ?? 'service',
            category: product.category ?? '',
            unit: product.unit ?? '',
            price: product.price ?? '0.00',
            cost: product.cost ?? '0.00',
            is_active: product.is_active,
            description: product.description ?? '',
        });
    };

    return (
        <AuthenticatedLayout>
            <Head title="Products/Services" />

            <div className="space-y-6">
                <PageHeader
                    title="Products/Services"
                    description="Finance catalog for invoice line items."
                />

                <div className="grid gap-6 xl:grid-cols-[minmax(0,1fr)_360px]">
                    <Card
                        title="Catalog"
                        description="Items available for billing"
                    >
                        <DataTable
                            data={products}
                            keyExtractor={(row) => row.id}
                            columns={[
                                {
                                    header: 'Item',
                                    accessor: (row) => (
                                        <div>
                                            <div className="font-semibold text-slate-900 dark:text-white">
                                                {row.name}
                                            </div>
                                            <div className="font-mono text-xs text-slate-500">
                                                {row.sku || '-'}
                                            </div>
                                        </div>
                                    ),
                                },
                                {
                                    header: 'Type',
                                    accessor: (row) => (
                                        <Badge variant="info" size="sm">
                                            {row.type}
                                        </Badge>
                                    ),
                                },
                                {
                                    header: 'Category',
                                    accessor: (row) => row.category || '-',
                                },
                                {
                                    header: 'Unit',
                                    accessor: (row) => row.unit || '-',
                                },
                                {
                                    header: 'Price',
                                    accessor: (row) => money(row.price),
                                },
                                {
                                    header: 'Status',
                                    accessor: (row) => (
                                        <Badge
                                            variant={
                                                row.is_active
                                                    ? 'success'
                                                    : 'neutral'
                                            }
                                            size="sm"
                                        >
                                            {row.is_active
                                                ? 'active'
                                                : 'inactive'}
                                        </Badge>
                                    ),
                                },
                                {
                                    header: 'Actions',
                                    accessor: (row) => (
                                        <div className="flex flex-wrap gap-2">
                                            <SecondaryButton
                                                type="button"
                                                onClick={() => editProduct(row)}
                                            >
                                                Edit
                                            </SecondaryButton>
                                            <SecondaryButton
                                                type="button"
                                                onClick={() =>
                                                    router.delete(
                                                        route(
                                                            'products.destroy',
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
                        title={editingProduct ? 'Edit Item' : 'Create Item'}
                        description="Products, services, or packages"
                    >
                        <form onSubmit={submit} className="space-y-3">
                            <Field
                                label="SKU"
                                value={form.data.sku}
                                error={form.errors.sku}
                                onChange={(value) => form.setData('sku', value)}
                            />
                            <Field
                                label="Name"
                                value={form.data.name}
                                error={form.errors.name}
                                onChange={(value) =>
                                    form.setData('name', value)
                                }
                                required
                            />
                            <SelectField
                                label="Type"
                                value={form.data.type}
                                onChange={(value) =>
                                    form.setData('type', value)
                                }
                                options={['service', 'product', 'package']}
                            />
                            <Field
                                label="Category"
                                value={form.data.category}
                                error={form.errors.category}
                                onChange={(value) =>
                                    form.setData('category', value)
                                }
                            />
                            <Field
                                label="Unit"
                                value={form.data.unit}
                                error={form.errors.unit}
                                onChange={(value) =>
                                    form.setData('unit', value)
                                }
                            />
                            <Field
                                label="Price"
                                type="number"
                                value={form.data.price}
                                error={form.errors.price}
                                onChange={(value) =>
                                    form.setData('price', value)
                                }
                                required
                            />
                            <Field
                                label="Cost"
                                type="number"
                                value={form.data.cost}
                                error={form.errors.cost}
                                onChange={(value) =>
                                    form.setData('cost', value)
                                }
                            />
                            <label className="flex items-center gap-2 text-sm text-slate-700">
                                <input
                                    type="checkbox"
                                    checked={form.data.is_active}
                                    onChange={(event) =>
                                        form.setData(
                                            'is_active',
                                            event.target.checked,
                                        )
                                    }
                                />
                                Active
                            </label>
                            <div className="flex justify-end gap-2">
                                {editingProduct && (
                                    <SecondaryButton
                                        type="button"
                                        onClick={() => {
                                            setEditingProduct(null);
                                            form.setData(emptyProduct);
                                        }}
                                    >
                                        Cancel
                                    </SecondaryButton>
                                )}
                                <PrimaryButton disabled={form.processing}>
                                    Save Item
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
    error,
    onChange,
    type = 'text',
    required = false,
}: {
    label: string;
    value: string;
    error?: string;
    onChange: (value: string) => void;
    type?: string;
    required?: boolean;
}) {
    const id = label.toLowerCase().replaceAll(' ', '-');

    return (
        <div>
            <InputLabel
                htmlFor={id}
                value={label}
                className="mb-1 text-xs font-semibold uppercase text-slate-700"
            />
            <TextInput
                id={id}
                type={type}
                step={type === 'number' ? '0.01' : undefined}
                min={type === 'number' ? '0' : undefined}
                value={value}
                onChange={(event) => onChange(event.target.value)}
                required={required}
                className="block w-full text-sm"
            />
            <InputError message={error} className="mt-1" />
        </div>
    );
}

function SelectField({
    label,
    value,
    options,
    onChange,
}: {
    label: string;
    value: string;
    options: string[];
    onChange: (value: string) => void;
}) {
    return (
        <div>
            <InputLabel
                value={label}
                className="mb-1 text-xs font-semibold uppercase text-slate-700"
            />
            <select
                value={value}
                onChange={(event) => onChange(event.target.value)}
                className="block w-full rounded-xl border-slate-300 bg-white text-sm font-medium text-slate-900 shadow-sm transition-colors duration-150 focus:border-indigo-500 focus:ring-indigo-500 dark:border-slate-800 dark:bg-slate-900 dark:text-white"
            >
                {options.map((option) => (
                    <option key={option} value={option}>
                        {option}
                    </option>
                ))}
            </select>
        </div>
    );
}
