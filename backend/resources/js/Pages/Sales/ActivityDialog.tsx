import CreateEditDialog from '@/Components/UI/CreateEditDialog';
import type { InertiaFormProps } from '@inertiajs/react';
import type { FormEvent } from 'react';
import {
    EditorChoice,
    EditorField,
    EditorReference,
} from '@/Components/UI/EditorFields';
import type { Activity, ActivityForm, Deal, Owner } from './Deals';

export default function ActivityDialog({
    open,
    minimized,
    onMinimize,
    onClose,
    form,
    editing,
    deals,
    owners,
    onSubmit,
}: {
    open: boolean;
    minimized: boolean;
    onMinimize: (value: boolean) => void;
    onClose: () => void;
    form: InertiaFormProps<ActivityForm>;
    editing: Activity | null;
    deals: Deal[];
    owners: Owner[];
    onSubmit: (event: FormEvent) => void;
}) {
    const field = (name: Exclude<keyof ActivityForm, 'entity_type'>) => ({
        id: `activity-${name}`,
        value: form.data[name],
        error: form.errors[name],
        onChange: (value: string) => {
            form.setData(name, value);
            form.clearErrors(name);
        },
    });
    return (
        <CreateEditDialog
            open={open}
            minimized={minimized}
            onMinimize={onMinimize}
            onClose={onClose}
            entityLabel="Activity"
            storageKey="erp.activity-dialog.size"
            formId="activity-dialog-form"
            saveLabel="Save Activity"
            processing={form.processing}
            errors={form.errors}
            onSubmit={onSubmit}
            title={editing ? 'Edit Activity' : 'Create Activity'}
            breadcrumb={`Deals / ${deals.find((deal) => deal.id === form.data.entity_id)?.title || 'Activity'}`}
            draftLabel={form.data.subject || 'New Activity'}
            status={`${form.data.completed_at ? 'Completed' : 'Open'} · ${form.isDirty ? 'Unsaved changes' : editing ? 'No changes' : 'New activity'}`}
        >
            {(expanded) => {
                const grid = expanded
                    ? 'grid gap-4 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4'
                    : 'grid gap-4 sm:grid-cols-2';
                return (
                    <>
                        <section aria-labelledby="activity-details-heading">
                            <h3
                                id="activity-details-heading"
                                className="mb-4 text-base font-semibold"
                            >
                                Activity Details
                            </h3>
                            <div className={grid}>
                                <EditorReference
                                    {...field('entity_id')}
                                    label="Deal"
                                    required
                                    options={deals.map((deal) => ({
                                        id: deal.id,
                                        label: deal.title,
                                    }))}
                                    emptyLabel="Select deal"
                                />
                                <EditorChoice
                                    {...field('activity_type')}
                                    label="Activity Type"
                                    required
                                    options={[
                                        'call',
                                        'meeting',
                                        'email',
                                        'line',
                                        'note',
                                    ]}
                                />
                                <EditorField
                                    {...field('subject')}
                                    label="Subject"
                                    maxLength={255}
                                />
                                <EditorReference
                                    {...field('owner_id')}
                                    label="Owner"
                                    options={owners.map((owner) => ({
                                        id: owner.id,
                                        label: `${owner.name} · ${owner.email}`,
                                    }))}
                                    emptyLabel={
                                        editing ? 'Unassigned' : 'Assign to me'
                                    }
                                />
                            </div>
                        </section>
                        <section
                            aria-labelledby="activity-schedule-heading"
                            className="border-t border-gray-200 pt-6 dark:border-gray-700"
                        >
                            <h3
                                id="activity-schedule-heading"
                                className="mb-4 text-base font-semibold"
                            >
                                Schedule (UTC)
                            </h3>
                            <div className={grid}>
                                <EditorField
                                    {...field('activity_at')}
                                    value={form.data.activity_at.slice(0, 19)}
                                    label="Activity At (UTC)"
                                    required
                                    type="datetime-local"
                                    step="1"
                                />
                                <EditorField
                                    {...field('follow_up_at')}
                                    value={form.data.follow_up_at.slice(0, 19)}
                                    label="Follow Up At (UTC)"
                                    type="datetime-local"
                                    step="1"
                                />
                            </div>
                            {form.data.completed_at && (
                                <p className="mt-3 text-sm">
                                    Completed at: {form.data.completed_at}.
                                    Completion is managed by the Done action.
                                </p>
                            )}
                        </section>
                        <section
                            aria-labelledby="activity-notes-heading"
                            className="border-t border-gray-200 pt-6 dark:border-gray-700"
                        >
                            <h3
                                id="activity-notes-heading"
                                className="mb-4 text-base font-semibold"
                            >
                                Notes
                            </h3>
                            <EditorField
                                {...field('body')}
                                label="Activity Notes"
                                multiline
                                maxLength={4000}
                            />
                        </section>
                    </>
                );
            }}
        </CreateEditDialog>
    );
}
