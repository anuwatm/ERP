import PrimaryButton from '@/Components/PrimaryButton';
import SecondaryButton from '@/Components/SecondaryButton';
import TextInput from '@/Components/TextInput';
import Badge from '@/Components/UI/Badge';
import Card from '@/Components/UI/Card';
import DataTable from '@/Components/UI/DataTable';
import PageHeader from '@/Components/UI/PageHeader';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { money } from '@/Utils/format';
import { Head, router, useForm } from '@inertiajs/react';
import { FormEvent } from 'react';

type Customer = { id: string; customer_code: string; company_name: string };
type Deal = { id: string; title: string; customer_id: string };
type Product = {
    id: string;
    sku: string;
    name: string;
    unit?: string | null;
    price: string;
};
type Quotation = {
    id: string;
    quotation_no: string;
    status: string;
    issue_date: string;
    valid_until?: string | null;
    total: string;
    currency: string;
    customer?: Customer | null;
    deal?: Deal | null;
    converted_invoice?: { id: string; invoice_no: string } | null;
};
type QuotationForm = {
    customer_id: string;
    deal_id: string;
    status: string;
    tax_mode: string;
    issue_date: string;
    valid_until: string;
    discount_amount: string;
    currency: string;
    notes: string;
    items: Array<{
        product_id: string;
        description: string;
        quantity: string;
        unit: string;
        unit_price: string;
        discount_amount: string;
        tax_rate: string;
    }>;
};

const today = new Date().toISOString().slice(0, 10);
const emptyItem = {
    product_id: '',
    description: '',
    quantity: '1',
    unit: '',
    unit_price: '0.00',
    discount_amount: '0.00',
    tax_rate: '7.00',
};
const emptyForm: QuotationForm = {
    customer_id: '',
    deal_id: '',
    status: 'draft',
    tax_mode: 'exclusive',
    issue_date: today,
    valid_until: '',
    discount_amount: '0.00',
    currency: 'THB',
    notes: '',
    items: [{ ...emptyItem }],
};

export default function Quotations({
    quotations,
    customers,
    deals,
    products,
    taxModes,
}: {
    quotations: Quotation[];
    customers: Customer[];
    deals: Deal[];
    products: Product[];
    statuses: string[];
    taxModes: string[];
}) {
    const form = useForm<QuotationForm>(emptyForm);

    const submit = (event: FormEvent) => {
        event.preventDefault();
        form.post(route('quotations.store'), {
            preserveScroll: true,
            onSuccess: () => form.setData(emptyForm),
        });
    };

    const setItem = (
        index: number,
        key: keyof QuotationForm['items'][number],
        value: string,
    ) => {
        const items = [...form.data.items];
        items[index] = { ...items[index], [key]: value };
        form.setData('items', items);
    };

    const selectProduct = (index: number, productId: string) => {
        const product = products.find((item) => item.id === productId);
        const items = [...form.data.items];
        items[index] = {
            ...items[index],
            product_id: productId,
            description: product?.name ?? items[index].description,
            unit: product?.unit ?? '',
            unit_price: product?.price ?? items[index].unit_price,
        };
        form.setData('items', items);
    };

    return (
        <AuthenticatedLayout>
            <Head title="Quotations" />
            <div className="space-y-6">
                <PageHeader
                    title="Quotations"
                    description="Sales offers before invoice conversion."
                />
                <div className="grid gap-6 xl:grid-cols-[minmax(0,1fr)_430px]">
                    <Card
                        title="Quotation List"
                        description="Commercial documents"
                    >
                        <DataTable
                            data={quotations}
                            keyExtractor={(row) => row.id}
                            columns={[
                                {
                                    header: 'Quotation',
                                    accessor: (row) => (
                                        <div>
                                            <div className="font-mono font-semibold text-slate-900 dark:text-white">
                                                {row.quotation_no}
                                            </div>
                                            <div className="text-xs text-slate-500">
                                                {row.customer?.company_name}
                                            </div>
                                            {row.converted_invoice && (
                                                <div className="text-xs text-emerald-600">
                                                    Invoice:{' '}
                                                    {
                                                        row.converted_invoice
                                                            .invoice_no
                                                    }
                                                </div>
                                            )}
                                        </div>
                                    ),
                                },
                                {
                                    header: 'Status',
                                    accessor: (row) => (
                                        <Badge size="sm">{row.status}</Badge>
                                    ),
                                },
                                {
                                    header: 'Issue',
                                    accessor: (row) =>
                                        row.issue_date?.slice(0, 10),
                                },
                                {
                                    header: 'Total',
                                    accessor: (row) =>
                                        `${money(row.total)} ${row.currency}`,
                                },
                                {
                                    header: 'Actions',
                                    accessor: (row) => (
                                        <div className="flex flex-wrap gap-2">
                                            {['draft', 'sent'].includes(
                                                row.status,
                                            ) && (
                                                <>
                                                    <SecondaryButton
                                                        type="button"
                                                        onClick={() =>
                                                            router.post(
                                                                route(
                                                                    'quotations.approve',
                                                                    row.id,
                                                                ),
                                                                {},
                                                                {
                                                                    preserveScroll: true,
                                                                },
                                                            )
                                                        }
                                                    >
                                                        Approve
                                                    </SecondaryButton>
                                                    <SecondaryButton
                                                        type="button"
                                                        onClick={() =>
                                                            router.post(
                                                                route(
                                                                    'quotations.reject',
                                                                    row.id,
                                                                ),
                                                                {},
                                                                {
                                                                    preserveScroll: true,
                                                                },
                                                            )
                                                        }
                                                    >
                                                        Reject
                                                    </SecondaryButton>
                                                </>
                                            )}
                                            {row.status === 'approved' && (
                                                <SecondaryButton
                                                    type="button"
                                                    onClick={() =>
                                                        router.post(
                                                            route(
                                                                'quotations.convert-to-invoice',
                                                                row.id,
                                                            ),
                                                            {},
                                                            {
                                                                preserveScroll: true,
                                                            },
                                                        )
                                                    }
                                                >
                                                    Convert
                                                </SecondaryButton>
                                            )}
                                        </div>
                                    ),
                                },
                            ]}
                        />
                    </Card>
                    <Card title="New Quotation" description="Draft or sent">
                        <form onSubmit={submit} className="space-y-3">
                            <select
                                className="block w-full rounded-md border-slate-300 text-sm shadow-sm"
                                value={form.data.customer_id}
                                onChange={(event) =>
                                    form.setData(
                                        'customer_id',
                                        event.target.value,
                                    )
                                }
                            >
                                <option value="">Select customer</option>
                                {customers.map((customer) => (
                                    <option
                                        key={customer.id}
                                        value={customer.id}
                                    >
                                        {customer.customer_code} -{' '}
                                        {customer.company_name}
                                    </option>
                                ))}
                            </select>
                            <select
                                className="block w-full rounded-md border-slate-300 text-sm shadow-sm"
                                value={form.data.deal_id}
                                onChange={(event) =>
                                    form.setData('deal_id', event.target.value)
                                }
                            >
                                <option value="">No deal</option>
                                {deals
                                    .filter(
                                        (deal) =>
                                            !form.data.customer_id ||
                                            deal.customer_id ===
                                                form.data.customer_id,
                                    )
                                    .map((deal) => (
                                        <option key={deal.id} value={deal.id}>
                                            {deal.title}
                                        </option>
                                    ))}
                            </select>
                            <div className="grid grid-cols-2 gap-2">
                                <TextInput
                                    type="date"
                                    value={form.data.issue_date}
                                    onChange={(event) =>
                                        form.setData(
                                            'issue_date',
                                            event.target.value,
                                        )
                                    }
                                />
                                <TextInput
                                    type="date"
                                    value={form.data.valid_until}
                                    onChange={(event) =>
                                        form.setData(
                                            'valid_until',
                                            event.target.value,
                                        )
                                    }
                                />
                            </div>
                            <div className="grid grid-cols-2 gap-2">
                                <select
                                    className="rounded-md border-slate-300 text-sm shadow-sm"
                                    value={form.data.tax_mode}
                                    onChange={(event) =>
                                        form.setData(
                                            'tax_mode',
                                            event.target.value,
                                        )
                                    }
                                >
                                    {taxModes.map((mode) => (
                                        <option key={mode} value={mode}>
                                            {mode}
                                        </option>
                                    ))}
                                </select>
                                <TextInput
                                    value={form.data.discount_amount}
                                    onChange={(event) =>
                                        form.setData(
                                            'discount_amount',
                                            event.target.value,
                                        )
                                    }
                                />
                            </div>
                            {form.data.items.map((item, index) => (
                                <div
                                    key={index}
                                    className="space-y-2 rounded-md bg-slate-50 p-3 dark:bg-slate-900"
                                >
                                    <select
                                        className="block w-full rounded-md border-slate-300 text-sm shadow-sm"
                                        value={item.product_id}
                                        onChange={(event) =>
                                            selectProduct(
                                                index,
                                                event.target.value,
                                            )
                                        }
                                    >
                                        <option value="">Manual line</option>
                                        {products.map((product) => (
                                            <option
                                                key={product.id}
                                                value={product.id}
                                            >
                                                {product.sku} - {product.name}
                                            </option>
                                        ))}
                                    </select>
                                    <TextInput
                                        className="block w-full"
                                        placeholder="Description"
                                        value={item.description}
                                        onChange={(event) =>
                                            setItem(
                                                index,
                                                'description',
                                                event.target.value,
                                            )
                                        }
                                    />
                                    <div className="grid grid-cols-2 gap-2">
                                        <TextInput
                                            type="number"
                                            value={item.quantity}
                                            onChange={(event) =>
                                                setItem(
                                                    index,
                                                    'quantity',
                                                    event.target.value,
                                                )
                                            }
                                        />
                                        <TextInput
                                            type="number"
                                            value={item.unit_price}
                                            onChange={(event) =>
                                                setItem(
                                                    index,
                                                    'unit_price',
                                                    event.target.value,
                                                )
                                            }
                                        />
                                    </div>
                                </div>
                            ))}
                            <SecondaryButton
                                type="button"
                                onClick={() =>
                                    form.setData('items', [
                                        ...form.data.items,
                                        { ...emptyItem },
                                    ])
                                }
                            >
                                Add Line
                            </SecondaryButton>
                            <div className="flex justify-end">
                                <PrimaryButton disabled={form.processing}>
                                    Save Quotation
                                </PrimaryButton>
                            </div>
                        </form>
                    </Card>
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
