import CreateEditDialog from '@/Components/UI/CreateEditDialog';
import DiscardChangesDialog from '@/Components/UI/DiscardChangesDialog';
import EditorAttachments from '@/Components/UI/EditorAttachments';
import useDiscardGuard from '@/Hooks/useDiscardGuard';
import { PageProps } from '@/Types';
import { useForm, usePage } from '@inertiajs/react';
import { FormEvent, useState } from 'react';

type VoucherRow = {
    id: string;
    voucher_no?: string | number | null;
    attachment?: { id: string; file_name: string } | null;
    [key: string]: unknown;
};

export default function VoucherAttachmentDialog({
    voucher,
    onClose,
}: {
    voucher: VoucherRow;
    onClose: () => void;
}) {
    const form = useForm<{ attachment: File | null }>({ attachment: null });
    const [minimized, setMinimized] = useState(false);
    const { auth } = usePage<PageProps>().props;
    const canManage =
        auth.permissions.includes('vouchers.update') ||
        auth.permissions.includes('vouchers.manage');
    const guard = useDiscardGuard(
        form.data.attachment !== null,
        form.processing,
    );

    function submit(event: FormEvent) {
        event.preventDefault();
        if (!canManage || form.processing || !form.data.attachment) return;
        form.post(route('vouchers.attachment.store', voucher.id), {
            forceFormData: true,
            preserveScroll: true,
            onSuccess: onClose,
        });
    }

    const attachment = voucher.attachment as
        { id: string; file_name: string } | null | undefined;

    return (
        <>
            <DiscardChangesDialog
                open={guard.confirming}
                onKeep={guard.keep}
                onDiscard={guard.discard}
            />
            <CreateEditDialog
                open
                minimized={minimized}
                onMinimize={setMinimized}
                onClose={() => guard.request(onClose)}
                title={
                    attachment
                        ? 'Replace Voucher Proof'
                        : 'Upload Voucher Proof'
                }
                breadcrumb="Finance / Commercial Documents"
                entityLabel="Voucher proof"
                draftLabel={String(voucher.voucher_no ?? 'Voucher')}
                storageKey="voucher-proof-editor-size"
                formId="voucher-proof-editor"
                processing={form.processing}
                saveDisabled={!canManage || !form.data.attachment}
                saveLabel={attachment ? 'Replace Proof' : 'Upload Proof'}
                status="PDF, JPG, PNG or WebP · Maximum 5 MB"
                errors={form.errors}
                onSubmit={submit}
            >
                {() => (
                    <div className="space-y-4 p-4 sm:p-6">
                        <p className="break-words text-sm text-slate-600 dark:text-slate-300">
                            Voucher No:{' '}
                            <span className="font-mono font-semibold text-slate-900 dark:text-white">
                                {String(voucher.voucher_no ?? '-')}
                            </span>
                        </p>
                        {attachment && (
                            <p className="text-sm text-slate-500 dark:text-slate-400">
                                Saving replaces the existing proof. Clearing the
                                selected file keeps the existing proof.
                            </p>
                        )}
                        <EditorAttachments
                            selected={form.data.attachment}
                            onChange={(file) => {
                                form.setData('attachment', file);
                                form.clearErrors('attachment');
                            }}
                            existing={
                                attachment
                                    ? [
                                          {
                                              id: attachment.id,
                                              name: attachment.file_name,
                                              url: route(
                                                  'files.download',
                                                  attachment.id,
                                              ),
                                              status: 'Current proof',
                                          },
                                      ]
                                    : []
                            }
                            accept=".pdf,.jpg,.jpeg,.png,.webp"
                            disabled={!canManage}
                        />
                    </div>
                )}
            </CreateEditDialog>
        </>
    );
}
