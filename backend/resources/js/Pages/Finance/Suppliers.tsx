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
    const [open, setOpen] = useState(false);
    const [minimized, setMinimized] = useState(false);
    const { auth } = usePage<PageProps>().props;
    const canCreate = auth.permissions.includes('suppliers.create');
    const canEdit = auth.permissions.includes('suppliers.update');
    const canDelete = auth.permissions.includes('suppliers.delete');
    const guard = useDiscardGuard(open && form.isDirty, form.processing);
    const submission = useSubmissionKey();
    const close = () => {
        setOpen(false);
        setMinimized(false);
        setEditingSupplier(null);
        form.setDefaults(emptySupplier);
        form.setData(emptySupplier);
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
        if (form.processing || (editingSupplier ? !canEdit : !canCreate))
            return;
        const options = {
            preserveScroll: true,
            onSuccess: close,
        };

        if (editingSupplier) {
            form.patch(route('suppliers.update', editingSupplier.id), options);
        } else {
            form.post(route('suppliers.store'), {
                ...options,
                headers: submission.headers(),
            });
        }
    };

    const edit = (supplier: Supplier) => {
        if (!canEdit || form.processing) return;
        guard.request(() => {
            setOpen(true);
            setMinimized(false);
            form.clearErrors();
            submission.reset();
            setEditingSupplier(supplier);
            const data = {
                name: supplier.name,
                tax_id: supplier.tax_id ?? '',
                email: supplier.email ?? '',
                phone: supplier.phone ?? '',
                address: supplier.address ?? '',
                status: supplier.status,
            };
            form.setDefaults(data);
            form.setData(data);
        });
    };

    return (
        <AuthenticatedLayout>
            <Head title="Suppliers" />
            <div className="space-y-6">
                <PageHeader
                    title="Suppliers"
                    description="Vendor master data for purchase orders and expenses."
                    actions={
                        canCreate && (
                            <PrimaryButton onClick={create}>
                                + Create Supplier
                            </PrimaryButton>
                        )
                    }
                />
                <div className="space-y-6">
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
                                                disabled={
                                                    !canEdit || form.processing
                                                }
                                                onClick={() => edit(row)}
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
                                                                'suppliers.destroy',
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
                        title={
                            editingSupplier
                                ? 'Edit Supplier'
                                : 'Create Supplier'
                        }
                        breadcrumb="Suppliers / Editor"
                        entityLabel="Supplier"
                        draftLabel={form.data.name || 'New supplier'}
                        storageKey="suppliers-editor-size"
                        formId="suppliers-editor"
                        processing={form.processing}
                        saveDisabled={editingSupplier ? !canEdit : !canCreate}
                        saveLabel="Save Supplier"
                        status={
                            form.isDirty
                                ? 'Unsaved changes'
                                : editingSupplier
                                  ? 'No changes'
                                  : 'New supplier'
                        }
                        errors={form.errors}
                        onSubmit={submit}
                    >
                        {(expanded) => (
                            <div className="space-y-6 p-4 sm:p-6">
                                <section aria-label="Supplier details">
                                    <h3 className="mb-3 font-semibold">
                                        Supplier details
                                    </h3>
                                    <div
                                        className={
                                            expanded
                                                ? 'grid gap-4 sm:grid-cols-2 lg:grid-cols-3'
                                                : 'grid gap-4 sm:grid-cols-2'
                                        }
                                    >
                                        <EditorField
                                            id="supplier-name"
                                            label="Name"
                                            value={form.data.name}
                                            error={form.errors.name}
                                            onChange={(v) =>
                                                form.setData('name', v)
                                            }
                                            required
                                        />
                                        <EditorField
                                            id="supplier-tax_id"
                                            label="Tax ID"
                                            value={form.data.tax_id}
                                            error={form.errors.tax_id}
                                            onChange={(v) =>
                                                form.setData('tax_id', v)
                                            }
                                        />
                                        <EditorChoice
                                            id="supplier-status"
                                            label="Status"
                                            value={form.data.status}
                                            error={form.errors.status}
                                            onChange={(v) =>
                                                form.setData('status', v)
                                            }
                                            options={['active', 'inactive']}
                                        />
                                    </div>
                                </section>
                                <section aria-label="Contact information">
                                    <h3 className="mb-3 font-semibold">
                                        Contact information
                                    </h3>
                                    <div className="grid gap-4 sm:grid-cols-2">
                                        <EditorField
                                            id="supplier-email"
                                            label="Email"
                                            value={form.data.email}
                                            error={form.errors.email}
                                            onChange={(v) =>
                                                form.setData('email', v)
                                            }
                                            type="email"
                                        />
                                        <EditorField
                                            id="supplier-phone"
                                            label="Phone"
                                            value={form.data.phone}
                                            error={form.errors.phone}
                                            onChange={(v) =>
                                                form.setData('phone', v)
                                            }
                                            type="tel"
                                        />
                                        <EditorField
                                            id="supplier-address"
                                            label="Address"
                                            value={form.data.address}
                                            error={form.errors.address}
                                            onChange={(v) =>
                                                form.setData('address', v)
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
