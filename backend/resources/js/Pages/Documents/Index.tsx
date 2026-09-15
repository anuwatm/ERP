import PageHeader from '@/Components/UI/PageHeader';
import PrimaryButton from '@/Components/PrimaryButton';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head } from '@inertiajs/react';
import { useState } from 'react';
import {
    DocumentCategoryDialog,
    DocumentDialog,
    DocumentLinkDialog,
    DocumentVersionDialog,
    RetentionPolicyDialog,
} from './DocumentDialogs';

type Version = {
    id: string;
    version_no: number;
    original_name: string;
    scan_status: string;
};
type Row = {
    id: string;
    document_no: string;
    title: string;
    sensitivity: string;
    expires_at?: string | null;
    category?: { name: string } | null;
    versions: Version[];
    links: { id: string; linkable_type: string; role: string }[];
};
type Category = { id: string; name: string; status: boolean };
type Policy = { id: string; name: string };

export default function Index({
    documents,
    categories,
    retentionPolicies,
    can,
}: {
    documents: { data: Row[] };
    categories: Category[];
    retentionPolicies: Policy[];
    can: { manage: boolean; retentionManage: boolean };
}) {
    const [uploadOpen, setUploadOpen] = useState(false);
    const [categoryOpen, setCategoryOpen] = useState(false);
    const [policyOpen, setPolicyOpen] = useState(false);

    const [versionTarget, setVersionTarget] = useState<{
        id: string;
        title: string;
    } | null>(null);

    const [linkTarget, setLinkTarget] = useState<{
        id: string;
        title: string;
    } | null>(null);

    return (
        <AuthenticatedLayout>
            <Head title="Documents" />
            <div className="space-y-6">
                <PageHeader
                    title="Document Management"
                    description="Enterprise DMS with immutable file versioning, automated anti-virus scan statuses, and retention governance."
                    actions={
                        <div className="flex flex-wrap gap-2">
                            {can.manage && (
                                <PrimaryButton
                                    onClick={() => setUploadOpen(true)}
                                >
                                    Upload Document
                                </PrimaryButton>
                            )}
                            {can.retentionManage && (
                                <>
                                    <button
                                        type="button"
                                        onClick={() => setCategoryOpen(true)}
                                        className="rounded-md border border-slate-300 bg-white px-3 py-2 text-sm font-medium text-slate-700 shadow-sm hover:bg-slate-50 dark:border-slate-600 dark:bg-slate-800 dark:text-slate-200"
                                    >
                                        New Category
                                    </button>
                                    <button
                                        type="button"
                                        onClick={() => setPolicyOpen(true)}
                                        className="rounded-md border border-slate-300 bg-white px-3 py-2 text-sm font-medium text-slate-700 shadow-sm hover:bg-slate-50 dark:border-slate-600 dark:bg-slate-800 dark:text-slate-200"
                                    >
                                        New Retention Policy
                                    </button>
                                </>
                            )}
                        </div>
                    }
                />

                <div className="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm dark:border-slate-800 dark:bg-slate-900">
                    <table className="min-w-full divide-y divide-slate-200 text-sm dark:divide-slate-800">
                        <thead className="bg-slate-50 text-left font-medium text-slate-600 dark:bg-slate-800/50 dark:text-slate-300">
                            <tr>
                                <th className="p-4">Document</th>
                                <th className="p-4">Category</th>
                                <th className="p-4">Versions</th>
                                <th className="p-4">Linked Entities</th>
                                <th className="p-4 text-right">Actions</th>
                            </tr>
                        </thead>
                        <tbody className="divide-y divide-slate-100 dark:divide-slate-800">
                            {documents.data.length === 0 && (
                                <tr>
                                    <td
                                        colSpan={5}
                                        className="p-8 text-center text-slate-500"
                                    >
                                        No documents found. Click "Upload
                                        Document" to add files.
                                    </td>
                                </tr>
                            )}
                            {documents.data.map((d) => (
                                <tr
                                    key={d.id}
                                    className="align-top hover:bg-slate-50/50 dark:hover:bg-slate-800/25"
                                >
                                    <td className="p-4">
                                        <div className="font-semibold text-slate-900 dark:text-slate-100">
                                            {d.title}
                                        </div>
                                        <div className="mt-1 flex flex-wrap gap-2 text-xs text-slate-500">
                                            <span>{d.document_no}</span>
                                            <span>•</span>
                                            <span className="capitalize">
                                                {d.sensitivity.replace(
                                                    '_',
                                                    ' ',
                                                )}
                                            </span>
                                            {d.expires_at && (
                                                <>
                                                    <span>•</span>
                                                    <span>
                                                        Expires {d.expires_at}
                                                    </span>
                                                </>
                                            )}
                                        </div>
                                    </td>
                                    <td className="p-4 text-slate-700 dark:text-slate-300">
                                        {d.category?.name || '-'}
                                    </td>
                                    <td className="p-4">
                                        <div className="space-y-1">
                                            {d.versions.map((v) => (
                                                <div
                                                    key={v.id}
                                                    className="flex items-center gap-2 text-xs"
                                                >
                                                    <span className="font-mono font-medium text-slate-500">
                                                        v{v.version_no}
                                                    </span>
                                                    {v.scan_status ===
                                                    'clean' ? (
                                                        <a
                                                            className="text-indigo-600 hover:underline dark:text-indigo-400"
                                                            href={route(
                                                                'documents.versions.download',
                                                                v.id,
                                                            )}
                                                        >
                                                            {v.original_name}
                                                        </a>
                                                    ) : (
                                                        <span className="text-slate-600 dark:text-slate-400">
                                                            {v.original_name} (
                                                            {v.scan_status})
                                                        </span>
                                                    )}
                                                </div>
                                            ))}
                                        </div>
                                    </td>
                                    <td className="p-4">
                                        <div className="flex flex-wrap gap-1">
                                            {d.links.length === 0 && (
                                                <span className="text-xs text-slate-400">
                                                    None
                                                </span>
                                            )}
                                            {d.links.map((l) => (
                                                <span
                                                    key={l.id}
                                                    className="inline-flex rounded bg-slate-100 px-2 py-0.5 text-xs text-slate-700 dark:bg-slate-800 dark:text-slate-300"
                                                >
                                                    {l.linkable_type} ({l.role})
                                                </span>
                                            ))}
                                        </div>
                                    </td>
                                    <td className="p-4 text-right">
                                        {can.manage && (
                                            <div className="flex justify-end gap-2 text-xs">
                                                <button
                                                    type="button"
                                                    onClick={() =>
                                                        setVersionTarget({
                                                            id: d.id,
                                                            title: d.title,
                                                        })
                                                    }
                                                    className="rounded border border-slate-200 px-2.5 py-1.5 font-medium text-indigo-600 hover:bg-indigo-50 dark:border-slate-700 dark:text-indigo-400 dark:hover:bg-slate-800"
                                                >
                                                    New Version
                                                </button>
                                                <button
                                                    type="button"
                                                    onClick={() =>
                                                        setLinkTarget({
                                                            id: d.id,
                                                            title: d.title,
                                                        })
                                                    }
                                                    className="rounded border border-slate-200 px-2.5 py-1.5 font-medium text-slate-700 hover:bg-slate-50 dark:border-slate-700 dark:text-slate-300 dark:hover:bg-slate-800"
                                                >
                                                    Link Entity
                                                </button>
                                            </div>
                                        )}
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>
            </div>

            <DocumentDialog
                open={uploadOpen}
                onClose={() => setUploadOpen(false)}
                categories={categories}
            />
            <DocumentCategoryDialog
                open={categoryOpen}
                onClose={() => setCategoryOpen(false)}
                retentionPolicies={retentionPolicies}
            />
            <RetentionPolicyDialog
                open={policyOpen}
                onClose={() => setPolicyOpen(false)}
            />
            {versionTarget && (
                <DocumentVersionDialog
                    open={true}
                    onClose={() => setVersionTarget(null)}
                    documentId={versionTarget.id}
                    documentTitle={versionTarget.title}
                />
            )}
            {linkTarget && (
                <DocumentLinkDialog
                    open={true}
                    onClose={() => setLinkTarget(null)}
                    documentId={linkTarget.id}
                    documentTitle={linkTarget.title}
                />
            )}
        </AuthenticatedLayout>
    );
}
