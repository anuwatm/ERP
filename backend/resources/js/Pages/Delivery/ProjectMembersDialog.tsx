import DangerButton from '@/Components/DangerButton';
import CreateEditDialog from '@/Components/UI/CreateEditDialog';
import DiscardChangesDialog from '@/Components/UI/DiscardChangesDialog';
import { EditorChoice, EditorReference } from '@/Components/UI/EditorFields';
import useDiscardGuard from '@/Hooks/useDiscardGuard';
import useSubmissionKey from '@/Hooks/useSubmissionKey';
import { router, useForm } from '@inertiajs/react';
import { FormEvent, useState } from 'react';

type Owner = { id: string; name: string; email: string };

type ProjectMember = {
    id: string;
    user_id: string;
    role: string;
    user?: Owner | null;
};

type Project = {
    id: string;
    project_code: string;
    name: string;
    members?: ProjectMember[];
};

export default function ProjectMembersDialog({
    open,
    project,
    owners,
    onClose,
}: {
    open: boolean;
    project: Project;
    owners: Owner[];
    onClose: () => void;
}) {
    const form = useForm({
        user_id: '',
        role: 'member',
    });
    const [minimized, setMinimized] = useState(false);
    const { headers, reset: resetKey } = useSubmissionKey();

    const isDirty = form.data.user_id !== '';
    const guard = useDiscardGuard(isDirty, form.processing);

    const existingMemberUserIds = (project.members ?? []).map((m) => m.user_id);
    const candidateUsers = owners.filter(
        (owner) => !existingMemberUserIds.includes(owner.id),
    );

    function submit(event: FormEvent) {
        event.preventDefault();
        if (form.processing || !form.data.user_id) return;

        form.post(route('projects.members.store', project.id), {
            headers: headers(),
            preserveScroll: true,
            onSuccess: () => {
                resetKey();
                form.reset('user_id');
            },
        });
    }

    const removeMember = (memberId: string) => {
        if (!confirm('Remove this member from project?')) return;
        router.delete(
            route('projects.members.destroy', [project.id, memberId]),
            {
                preserveScroll: true,
            },
        );
    };

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
                title="Manage Project Members"
                breadcrumb="Delivery / Projects"
                entityLabel="Project Members"
                draftLabel={`${project.project_code} - ${project.name}`}
                storageKey="project-members-dialog-size"
                formId="project-members-form"
                processing={form.processing}
                saveDisabled={!form.data.user_id}
                saveLabel="Add Member"
                status={`${(project.members ?? []).length} active members`}
                errors={form.errors}
                onSubmit={submit}
            >
                {() => (
                    <div className="space-y-6 p-4 sm:p-6">
                        {/* Add Member section */}
                        <div className="rounded-xl border border-slate-200 bg-slate-50/50 p-4 dark:border-slate-800 dark:bg-slate-900/50">
                            <h4 className="mb-3 text-xs font-semibold uppercase tracking-wider text-slate-500 dark:text-slate-400">
                                Assign New Team Member
                            </h4>
                            <div className="grid gap-3 sm:grid-cols-[1fr_150px]">
                                <EditorReference
                                    id="member-user-id"
                                    label="User"
                                    emptyLabel="Select user"
                                    value={form.data.user_id}
                                    options={candidateUsers.map((u) => ({
                                        id: u.id,
                                        label: `${u.name} (${u.email})`,
                                    }))}
                                    onChange={(val: string) =>
                                        form.setData('user_id', val)
                                    }
                                    error={form.errors.user_id}
                                    required
                                />

                                <EditorChoice
                                    id="member-role"
                                    label="Project Role"
                                    value={form.data.role}
                                    options={['member', 'lead', 'viewer']}
                                    onChange={(val: string) =>
                                        form.setData('role', val)
                                    }
                                    error={form.errors.role}
                                    required
                                />
                            </div>
                        </div>

                        {/* Existing Members list */}
                        <div>
                            <h4 className="mb-3 text-sm font-semibold text-slate-900 dark:text-white">
                                Current Team ({project.members?.length ?? 0})
                            </h4>
                            {project.members && project.members.length > 0 ? (
                                <div className="divide-y divide-slate-200 rounded-xl border border-slate-200 dark:divide-slate-800 dark:border-slate-800">
                                    {project.members.map((member) => (
                                        <div
                                            key={member.id}
                                            className="flex items-center justify-between p-3.5"
                                        >
                                            <div>
                                                <div className="font-medium text-slate-900 dark:text-white">
                                                    {member.user?.name ??
                                                        'Unknown User'}
                                                </div>
                                                <div className="text-xs text-slate-500">
                                                    {member.user?.email ?? '-'}{' '}
                                                    ·{' '}
                                                    <span className="capitalize font-medium text-indigo-600 dark:text-indigo-400">
                                                        {member.role}
                                                    </span>
                                                </div>
                                            </div>
                                            <DangerButton
                                                type="button"
                                                onClick={() =>
                                                    removeMember(member.id)
                                                }
                                            >
                                                Remove
                                            </DangerButton>
                                        </div>
                                    ))}
                                </div>
                            ) : (
                                <div className="rounded-xl border border-dashed border-slate-200 p-6 text-center text-sm text-slate-500 dark:border-slate-800">
                                    No team members assigned yet.
                                </div>
                            )}
                        </div>
                    </div>
                )}
            </CreateEditDialog>
        </>
    );
}
