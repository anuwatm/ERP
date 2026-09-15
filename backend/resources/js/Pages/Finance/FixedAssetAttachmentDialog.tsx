import CreateEditDialog from '@/Components/UI/CreateEditDialog';
import DiscardChangesDialog from '@/Components/UI/DiscardChangesDialog';
import EditorAttachments from '@/Components/UI/EditorAttachments';
import useDiscardGuard from '@/Hooks/useDiscardGuard';
import { PageProps } from '@/Types';
import { useForm, usePage } from '@inertiajs/react';
import { FormEvent, useState } from 'react';

type Asset = {
    id: string;
    asset_no: string;
    name: string;
    attachment: { id: string; file_name: string } | null;
};

export default function FixedAssetAttachmentDialog({
    asset,
    onClose,
}: {
    asset: Asset;
    onClose: () => void;
}) {
    const form = useForm<{ attachment: File | null }>({ attachment: null });
    const [minimized, setMinimized] = useState(false);
    const { auth } = usePage<PageProps>().props;
    const canManage = auth.permissions.includes('fixed_assets.manage');
    const guard = useDiscardGuard(
        form.data.attachment !== null,
        form.processing,
    );
    function submit(event: FormEvent) {
        event.preventDefault();
        if (!canManage || form.processing || !form.data.attachment) return;
        form.post(route('fixed-assets.attachment.store', asset.id), {
            forceFormData: true,
            preserveScroll: true,
            onSuccess: onClose,
        });
    }
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
                    asset.attachment
                        ? 'Replace Asset Proof'
                        : 'Upload Asset Proof'
                }
                breadcrumb="Finance / Fixed Assets"
                entityLabel="Asset proof"
                draftLabel={`${asset.asset_no} - ${asset.name}`}
                storageKey="fixed-asset-proof-editor-size"
                formId="fixed-asset-proof-editor"
                processing={form.processing}
                saveDisabled={!canManage || !form.data.attachment}
                saveLabel={asset.attachment ? 'Replace Proof' : 'Upload Proof'}
                status="PDF, JPG, PNG or WebP · Maximum 5 MB"
                errors={form.errors}
                onSubmit={submit}
            >
                {() => (
                    <div className="space-y-4 p-4 sm:p-6">
                        <p className="break-words text-sm">
                            {asset.asset_no} — {asset.name}
                        </p>
                        {asset.attachment && (
                            <p className="text-sm">
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
                                asset.attachment
                                    ? [
                                          {
                                              id: asset.attachment.id,
                                              name: asset.attachment.file_name,
                                              url: auth.permissions.includes(
                                                  'fixed_assets.view',
                                              )
                                                  ? route(
                                                        'files.download',
                                                        asset.attachment.id,
                                                    )
                                                  : undefined,
                                              status: 'Current proof',
                                          },
                                      ]
                                    : []
                            }
                            error={form.errors.attachment}
                            disabled={form.processing}
                            accept=".pdf,.jpg,.jpeg,.png,.webp"
                        />
                        {form.progress && (
                            <p role="status" className="text-sm">
                                Uploading: {form.progress.percentage ?? 0}%
                            </p>
                        )}
                    </div>
                )}
            </CreateEditDialog>
        </>
    );
}
