import CreateEditDialog from '@/Components/UI/CreateEditDialog';
import DiscardChangesDialog from '@/Components/UI/DiscardChangesDialog';
import { EditorField, EditorChoice } from '@/Components/UI/EditorFields';
import useDiscardGuard from '@/Hooks/useDiscardGuard';
import useSubmissionKey from '@/Hooks/useSubmissionKey';
import { PageProps } from '@/Types';
import PrimaryButton from '@/Components/PrimaryButton';
import SecondaryButton from '@/Components/SecondaryButton';
import Badge from '@/Components/UI/Badge';
import Card from '@/Components/UI/Card';
import DataTable from '@/Components/UI/DataTable';
import PageHeader from '@/Components/UI/PageHeader';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, router, useForm, usePage } from '@inertiajs/react';
import { FormEvent, useState } from 'react';
import { money } from '@/Utils/format';

type Product = {
    id: string;
    sku?: string | null;
    barcode?: string | null;
    reorder_point?: string;
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
    barcode: string;
    reorder_point: string;
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
    barcode: '',
    reorder_point: '0',
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
    const [open, setOpen] = useState(false);
    const [minimized, setMinimized] = useState(false);
    const { auth } = usePage<PageProps>().props;
    const canCreate = auth.permissions.includes('products.manage');
    const canEdit = auth.permissions.includes('products.manage');
    const canDelete = auth.permissions.includes('products.manage');
    const guard = useDiscardGuard(open && form.isDirty, form.processing);
    const submission = useSubmissionKey();
    const close = () => {
        setOpen(false);
        setMinimized(false);
        setEditingProduct(null);
        form.setDefaults(emptyProduct);
        form.setData(emptyProduct);
        form.clearErrors();
        submission.reset();
    };
    const create = () => {
        if (!canCreate || form.processing) return;
        guard.request(() => {
            close();
            setOpen(true);
        });
    };

    const submit = (event: FormEvent) => {
        event.preventDefault();
        if (form.processing || (editingProduct ? !canEdit : !canCreate)) return;
        const options = {
            preserveScroll: true,
            onSuccess: close,
        };

        if (editingProduct) {
            form.patch(route('products.update', editingProduct.id), options);
        } else {
            form.post(route('products.store'), {
                ...options,
                headers: submission.headers(),
            });
        }
    };

    const editProduct = (product: Product) => {
        if (!canEdit || form.processing) return;
        guard.request(() => {
            setOpen(true);
            setMinimized(false);
            form.clearErrors();
            submission.reset();
            setEditingProduct(product);
            const data = {
                sku: product.sku ?? '',
                barcode: product.barcode ?? '',
                reorder_point: product.reorder_point ?? '0',
                name: product.name ?? '',
                type: product.type ?? 'service',
                category: product.category ?? '',
                unit: product.unit ?? '',
                price: product.price ?? '0.00',
                cost: product.cost ?? '0.00',
                is_active: product.is_active,
                description: product.description ?? '',
            };
            form.setDefaults(data);
            form.setData(data);
        });
    };

    return (
        <AuthenticatedLayout>
            <Head title="Products/Services" />

            <div className="space-y-6">
                <PageHeader
                    title="Products/Services"
                    description="Finance catalog for invoice line items."
                    actions={
                        canCreate && (
                            <PrimaryButton onClick={create}>
                                + Create Item
                            </PrimaryButton>
                        )
                    }
                />

                <div className="space-y-6">
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
                                                disabled={
                                                    !canEdit || form.processing
                                                }
                                                onClick={() => editProduct(row)}
                                            >
                                                Edit
                                            </SecondaryButton>
                                            <SecondaryButton
                                                type="button"
                                                disabled={
                                                    !canDelete ||
                                                    form.processing
                                                }
                                                onClick={() =>
                                                    guard.request(() =>
                                                        router.delete(
                                                            route(
                                                                'products.destroy',
                                                                row.id,
                                                            ),
                                                            {
                                                                preserveScroll: true,
                                                            },
                                                        ),
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

                    <DiscardChangesDialog
                        open={guard.confirming}
                        onKeep={guard.keep}
                        onDiscard={guard.discard}
                    />
                    <CreateEditDialog
                        open={open}
                        minimized={minimized}
                        onMinimize={setMinimized}
                        onClose={() => guard.request(close)}
                        title={editingProduct ? 'Edit Item' : 'Create Item'}
                        breadcrumb="Products / Editor"
                        entityLabel="Item"
                        draftLabel={form.data.name || 'New item'}
                        storageKey="products-editor-size"
                        formId="products-editor"
                        processing={form.processing}
                        saveDisabled={editingProduct ? !canEdit : !canCreate}
                        saveLabel="Save Item"
                        status={
                            form.isDirty
                                ? 'Unsaved changes'
                                : editingProduct
                                  ? 'No changes'
                                  : 'New item'
                        }
                        errors={form.errors}
                        onSubmit={submit}
                    >
                        {(expanded) => (
                            <div className="space-y-6 p-4 sm:p-6">
                                <section
                                    aria-label="Item details"
                                    className="space-y-3"
                                >
                                    <h3 className="font-semibold">
                                        Item details
                                    </h3>
                                    <div
                                        className={
                                            expanded
                                                ? 'grid gap-4 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4'
                                                : 'grid gap-4 sm:grid-cols-2'
                                        }
                                    >
                                        <EditorField
                                            id="products-name"
                                            label="Name"
                                            value={form.data.name}
                                            error={form.errors.name}
                                            onChange={(value) =>
                                                form.setData('name', value)
                                            }
                                            required
                                        />
                                        <EditorField
                                            id="products-sku"
                                            label="SKU"
                                            value={form.data.sku}
                                            error={form.errors.sku}
                                            onChange={(value) =>
                                                form.setData('sku', value)
                                            }
                                        />
                                        <EditorField
                                            id="products-barcode"
                                            label="Barcode"
                                            value={form.data.barcode}
                                            error={form.errors.barcode}
                                            onChange={(value) =>
                                                form.setData('barcode', value)
                                            }
                                        />
                                        <EditorChoice
                                            id="products-type"
                                            label="Type"
                                            value={form.data.type}
                                            error={form.errors.type}
                                            onChange={(value) =>
                                                form.setData('type', value)
                                            }
                                            options={[
                                                'service',
                                                'product',
                                                'package',
                                            ]}
                                        />
                                        <EditorField
                                            id="products-category"
                                            label="Category"
                                            value={form.data.category}
                                            error={form.errors.category}
                                            onChange={(value) =>
                                                form.setData('category', value)
                                            }
                                        />
                                        <EditorField
                                            id="products-unit"
                                            label="Unit"
                                            value={form.data.unit}
                                            error={form.errors.unit}
                                            onChange={(value) =>
                                                form.setData('unit', value)
                                            }
                                        />
                                    </div>
                                </section>
                                <section
                                    aria-label="Pricing and stock"
                                    className="space-y-3"
                                >
                                    <h3 className="font-semibold">
                                        Pricing and stock
                                    </h3>
                                    <div
                                        className={
                                            expanded
                                                ? 'grid gap-4 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4'
                                                : 'grid gap-4 sm:grid-cols-2'
                                        }
                                    >
                                        <EditorField
                                            id="products-price"
                                            label="Price"
                                            value={form.data.price}
                                            error={form.errors.price}
                                            onChange={(value) =>
                                                form.setData('price', value)
                                            }
                                            type="number"
                                            min={0}
                                            step="0.01"
                                            required
                                        />
                                        <EditorField
                                            id="products-cost"
                                            label="Cost"
                                            value={form.data.cost}
                                            error={form.errors.cost}
                                            onChange={(value) =>
                                                form.setData('cost', value)
                                            }
                                            type="number"
                                            min={0}
                                            step="0.01"
                                        />
                                        <EditorField
                                            id="products-reorder_point"
                                            label="Reorder Point"
                                            value={form.data.reorder_point}
                                            error={form.errors.reorder_point}
                                            onChange={(value) =>
                                                form.setData(
                                                    'reorder_point',
                                                    value,
                                                )
                                            }
                                            type="number"
                                            min={0}
                                            step="0.01"
                                        />
                                        <label className="flex items-center gap-2">
                                            <input
                                                type="checkbox"
                                                checked={form.data.is_active}
                                                onChange={(e) =>
                                                    form.setData(
                                                        'is_active',
                                                        e.target.checked,
                                                    )
                                                }
                                            />
                                            Active
                                        </label>
                                    </div>
                                </section>
                                <section
                                    aria-label="Description"
                                    className="space-y-3"
                                >
                                    <h3 className="font-semibold">
                                        Description
                                    </h3>
                                    <div
                                        className={
                                            expanded
                                                ? 'grid gap-4 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4'
                                                : 'grid gap-4 sm:grid-cols-2'
                                        }
                                    >
                                        <EditorField
                                            id="products-description"
                                            label="Description"
                                            value={form.data.description}
                                            error={form.errors.description}
                                            onChange={(value) =>
                                                form.setData(
                                                    'description',
                                                    value,
                                                )
                                            }
                                            multiline
                                        />
                                    </div>
                                </section>
                            </div>
                        )}
                    </CreateEditDialog>
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
