import CreateEditDialog from '@/Components/UI/CreateEditDialog';
import DiscardChangesDialog from '@/Components/UI/DiscardChangesDialog';
import { EditorField } from '@/Components/UI/EditorFields';
import useDiscardGuard from '@/Hooks/useDiscardGuard';
import useSubmissionKey from '@/Hooks/useSubmissionKey';
import { PageProps } from '@/Types';
import { useState, FormEvent } from 'react';
import PrimaryButton from '@/Components/PrimaryButton';
import Card from '@/Components/UI/Card';
import DataTable from '@/Components/UI/DataTable';
import PageHeader from '@/Components/UI/PageHeader';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, router, useForm, usePage } from '@inertiajs/react';

type Period = {
    id: string;
    name: string;
    start_date: string;
    end_date: string;
    status: string;
};

export default function Periods({ periods }: { periods: Period[] }) {
    const form = useForm({ name: '', start_date: '', end_date: '' });
    const [open, setOpen] = useState(false);
    const [minimized, setMinimized] = useState(false);
    const { auth } = usePage<PageProps>().props;
    const canManage = auth.permissions.includes('accounting.periods.manage');
    const guard = useDiscardGuard(open && form.isDirty, form.processing);
    const submission = useSubmissionKey();
    const close = () => {
        setOpen(false);
        setMinimized(false);
        form.reset();
        form.clearErrors();
        submission.reset();
    };
    const create = () => {
        if (canManage && !form.processing)
            guard.request(() => {
                close();
                setOpen(true);
            });
    };
    const submit = (event: FormEvent) => {
        event.preventDefault();
        if (!canManage || form.processing) return;
        form.post(route('accounting.periods.store'), {
            preserveScroll: true,
            headers: submission.headers(),
            onSuccess: close,
        });
    };
    return (
        <AuthenticatedLayout>
            <Head title="Accounting Periods" />
            <div className="space-y-6">
                <PageHeader
                    actions={
                        canManage && (
                            <PrimaryButton onClick={create}>
                                + Create Period
                            </PrimaryButton>
                        )
                    }
                    title="Accounting Periods"
                    description="Open periods accept postings; closed periods are immutable."
                />
                <div className="space-y-6">
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
                        title="Create Period"
                        breadcrumb="Accounting / Periods"
                        entityLabel="Period"
                        draftLabel={form.data.name || 'New period'}
                        storageKey="period-editor-size"
                        formId="period-editor"
                        processing={form.processing}
                        saveDisabled={!canManage}
                        saveLabel="Create Period"
                        status="New open accounting period"
                        errors={form.errors}
                        onSubmit={submit}
                    >
                        {(expanded) => (
                            <section
                                aria-label="Period details"
                                className={
                                    expanded
                                        ? 'grid gap-4 p-4 sm:grid-cols-2 lg:grid-cols-3'
                                        : 'grid gap-4 p-4 sm:grid-cols-2'
                                }
                            >
                                <EditorField
                                    id="form-name"
                                    label="Name"
                                    value={String(form.data.name)}
                                    error={form.errors.name}
                                    onChange={(value) =>
                                        form.setData('name', value)
                                    }
                                    required
                                />
                                <EditorField
                                    id="form-start_date"
                                    label="Start Date"
                                    value={String(form.data.start_date)}
                                    error={form.errors.start_date}
                                    onChange={(value) =>
                                        form.setData('start_date', value)
                                    }
                                    type="date"
                                    required
                                />
                                <EditorField
                                    id="form-end_date"
                                    label="End Date"
                                    value={String(form.data.end_date)}
                                    error={form.errors.end_date}
                                    onChange={(value) =>
                                        form.setData('end_date', value)
                                    }
                                    type="date"
                                    required
                                />
                            </section>
                        )}
                    </CreateEditDialog>
                    <Card title="Periods">
                        <DataTable
                            data={periods}
                            keyExtractor={(item) => item.id}
                            columns={[
                                {
                                    header: 'Name',
                                    accessor: (item) => item.name,
                                },
                                {
                                    header: 'Start',
                                    accessor: (item) => item.start_date,
                                },
                                {
                                    header: 'End',
                                    accessor: (item) => item.end_date,
                                },
                                {
                                    header: 'Status',
                                    accessor: (item) => item.status,
                                },
                                {
                                    header: 'Actions',
                                    accessor: (item) =>
                                        canManage &&
                                        item.status === 'open' && (
                                            <button
                                                type="button"
                                                disabled={
                                                    open || form.processing
                                                }
                                                onClick={() =>
                                                    router.post(
                                                        route(
                                                            'accounting.periods.close',
                                                            item.id,
                                                        ),
                                                    )
                                                }
                                            >
                                                Close
                                            </button>
                                        ),
                                },
                            ]}
                        />
                    </Card>
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
