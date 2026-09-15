import CreateEditDialog from '@/Components/UI/CreateEditDialog';
import DiscardChangesDialog from '@/Components/UI/DiscardChangesDialog';
import {
    EditorChoice,
    EditorField,
    EditorReference,
} from '@/Components/UI/EditorFields';
import useDiscardGuard from '@/Hooks/useDiscardGuard';
import useSubmissionKey from '@/Hooks/useSubmissionKey';
import { useForm } from '@inertiajs/react';
import { FormEvent, useEffect, useMemo, useState } from 'react';

type Customer = { id: string; customer_code: string; company_name: string };
type Deal = {
    id: string;
    title: string;
    customer_id: string;
    owner_id?: string | null;
    value_amount: string;
};
type Owner = { id: string; name: string; email: string };
type Project = {
    id: string;
    project_code: string;
    name: string;
    customer_id?: string | null;
    deal_id?: string | null;
    owner_id?: string | null;
    status: string;
    start_date?: string | null;
    due_date?: string | null;
    progress_percent: number;
    budget_amount: string;
    currency: string;
    note?: string | null;
};

type ProjectForm = {
    name: string;
    customer_id: string;
    deal_id: string;
    owner_id: string;
    status: string;
    start_date: string;
    due_date: string;
    progress_percent: string;
    budget_amount: string;
    currency: string;
    note: string;
};

const emptyProject: ProjectForm = {
    name: '',
    customer_id: '',
    deal_id: '',
    owner_id: '',
    status: 'planning',
    start_date: '',
    due_date: '',
    progress_percent: '0',
    budget_amount: '0.00',
    currency: 'THB',
    note: '',
};

export default function ProjectEditorDialog({
    open,
    project,
    customers,
    deals,
    owners,
    statuses,
    canSeeAllProjects,
    canReassignProjects,
    onClose,
}: {
    open: boolean;
    project: Project | null;
    customers: Customer[];
    deals: Deal[];
    owners: Owner[];
    statuses: string[];
    canSeeAllProjects: boolean;
    canReassignProjects: boolean;
    onClose: () => void;
}) {
    const form = useForm<ProjectForm>(emptyProject);
    const [minimized, setMinimized] = useState(false);
    const { headers, reset: resetSubmissionKey } = useSubmissionKey();

    useEffect(() => {
        if (!open) return;
        if (project) {
            form.setData({
                name: project.name ?? '',
                customer_id: project.customer_id ?? '',
                deal_id: project.deal_id ?? '',
                owner_id: project.owner_id ?? '',
                status: project.status ?? 'planning',
                start_date: project.start_date?.slice(0, 10) ?? '',
                due_date: project.due_date?.slice(0, 10) ?? '',
                progress_percent: String(project.progress_percent ?? 0),
                budget_amount: project.budget_amount ?? '0.00',
                currency: project.currency ?? 'THB',
                note: project.note ?? '',
            });
        } else {
            form.setData(emptyProject);
        }
        form.clearErrors();
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [open, project?.id]);

    const availableDeals = useMemo(
        () =>
            deals.filter((deal) =>
                form.data.customer_id
                    ? deal.customer_id === form.data.customer_id
                    : true,
            ),
        [deals, form.data.customer_id],
    );

    const isDirty =
        form.data.name !== (project ? project.name : '') ||
        form.data.customer_id !==
            (project ? (project.customer_id ?? '') : '') ||
        form.data.deal_id !== (project ? (project.deal_id ?? '') : '') ||
        form.data.status !== (project ? project.status : 'planning') ||
        form.data.budget_amount !==
            (project ? project.budget_amount : '0.00') ||
        form.data.note !== (project ? (project.note ?? '') : '');

    const guard = useDiscardGuard(isDirty, form.processing);

    function setDeal(dealId: string) {
        const deal = deals.find((item) => item.id === dealId);
        form.setData({
            ...form.data,
            deal_id: dealId,
            name: deal && !form.data.name ? deal.title : form.data.name,
            budget_amount: deal
                ? (deal.value_amount ?? form.data.budget_amount)
                : form.data.budget_amount,
            owner_id:
                deal?.owner_id && canReassignProjects
                    ? deal.owner_id
                    : form.data.owner_id,
        });
    }

    function submit(event: FormEvent) {
        event.preventDefault();
        if (form.processing || !form.data.name) return;

        const options = {
            headers: headers(),
            preserveScroll: true,
            onSuccess: () => {
                resetSubmissionKey();
                onClose();
            },
        };

        if (project) {
            form.patch(route('projects.update', project.id), options);
        } else {
            form.post(route('projects.store'), options);
        }
    }

    if (!open) return null;

    return (
        <>
            <DiscardChangesDialog
                open={guard.confirming}
                onKeep={guard.keep}
                onDiscard={guard.discard}
            />
            <CreateEditDialog
                open={open}
                minimized={minimized}
                onMinimize={setMinimized}
                onClose={() => guard.request(onClose)}
                title={project ? 'Edit Project' : 'Create Project'}
                breadcrumb="Delivery / Projects"
                entityLabel="Project"
                draftLabel={
                    form.data.name ||
                    (project ? project.project_code : 'New Project')
                }
                storageKey="project-editor-size"
                formId="project-editor-form"
                processing={form.processing}
                saveDisabled={!form.data.name}
                saveLabel={project ? 'Save Project' : 'Create Project'}
                status={project ? project.status : 'Planning'}
                errors={form.errors}
                onSubmit={submit}
            >
                {() => (
                    <div className="space-y-6 p-4 sm:p-6">
                        <EditorField
                            id="project-name"
                            label="Project Name"
                            value={form.data.name}
                            onChange={(val: string) =>
                                form.setData('name', val)
                            }
                            error={form.errors.name}
                            required
                        />

                        <div className="grid gap-4 sm:grid-cols-2">
                            <EditorReference
                                id="project-customer-id"
                                label="Customer"
                                emptyLabel="No customer linked"
                                value={form.data.customer_id}
                                options={customers.map((c) => ({
                                    id: c.id,
                                    label: `${c.customer_code} - ${c.company_name}`,
                                }))}
                                onChange={(val: string) => {
                                    form.setData('customer_id', val);
                                    form.setData('deal_id', '');
                                }}
                                error={form.errors.customer_id}
                            />

                            <EditorReference
                                id="project-deal-id"
                                label="Won Deal"
                                emptyLabel="No deal linked"
                                value={form.data.deal_id}
                                options={availableDeals.map((d) => ({
                                    id: d.id,
                                    label: d.title,
                                }))}
                                onChange={setDeal}
                                error={form.errors.deal_id}
                            />
                        </div>

                        <div className="grid gap-4 sm:grid-cols-2">
                            {canSeeAllProjects && canReassignProjects && (
                                <EditorReference
                                    id="project-owner-id"
                                    label="Project Owner"
                                    emptyLabel="Unassigned"
                                    value={form.data.owner_id}
                                    options={owners.map((o) => ({
                                        id: o.id,
                                        label: o.name,
                                    }))}
                                    onChange={(val: string) =>
                                        form.setData('owner_id', val)
                                    }
                                    error={form.errors.owner_id}
                                />
                            )}

                            <EditorChoice
                                id="project-status"
                                label="Status"
                                value={form.data.status}
                                options={statuses}
                                onChange={(val: string) =>
                                    form.setData('status', val)
                                }
                                error={form.errors.status}
                            />
                        </div>

                        <div className="grid gap-4 sm:grid-cols-3">
                            <EditorField
                                id="project-start-date"
                                label="Start Date"
                                type="date"
                                value={form.data.start_date}
                                onChange={(val: string) =>
                                    form.setData('start_date', val)
                                }
                                error={form.errors.start_date}
                            />

                            <EditorField
                                id="project-due-date"
                                label="Due Date"
                                type="date"
                                value={form.data.due_date}
                                onChange={(val: string) =>
                                    form.setData('due_date', val)
                                }
                                error={form.errors.due_date}
                            />

                            <EditorField
                                id="project-progress"
                                label="Progress %"
                                type="number"
                                min={0}
                                max={100}
                                value={form.data.progress_percent}
                                onChange={(val: string) =>
                                    form.setData('progress_percent', val)
                                }
                                error={form.errors.progress_percent}
                            />
                        </div>

                        <div className="grid gap-4 sm:grid-cols-2">
                            <EditorField
                                id="project-budget"
                                label="Budget Amount"
                                type="number"
                                step="0.01"
                                min={0}
                                value={form.data.budget_amount}
                                onChange={(val: string) =>
                                    form.setData('budget_amount', val)
                                }
                                error={form.errors.budget_amount}
                            />

                            <EditorField
                                id="project-currency"
                                label="Currency"
                                value={form.data.currency}
                                onChange={(val: string) =>
                                    form.setData('currency', val.toUpperCase())
                                }
                                error={form.errors.currency}
                                maxLength={3}
                                required
                            />
                        </div>

                        <EditorField
                            id="project-note"
                            label="Project Note"
                            multiline
                            value={form.data.note}
                            onChange={(val: string) =>
                                form.setData('note', val)
                            }
                            error={form.errors.note}
                        />
                    </div>
                )}
            </CreateEditDialog>
        </>
    );
}
