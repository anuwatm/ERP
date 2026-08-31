import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, router } from '@inertiajs/react';
import { FormEvent } from 'react';

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
const post = (event: FormEvent<HTMLFormElement>, url: string) => {
    event.preventDefault();
    router.post(url, new FormData(event.currentTarget));
    event.currentTarget.reset();
};

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
    return (
        <AuthenticatedLayout header="Document Management">
            <Head title="Documents" />
            <div className="space-y-6 p-6">
                {can.manage && (
                    <form
                        onSubmit={(e) => post(e, route('documents.store'))}
                        className="grid gap-3 rounded border bg-white p-4 md:grid-cols-3"
                    >
                        <input
                            name="title"
                            required
                            placeholder="Document title"
                            className="rounded border-slate-300"
                        />
                        <select
                            name="category_id"
                            className="rounded border-slate-300"
                        >
                            <option value="">Uncategorized</option>
                            {categories
                                .filter((c) => c.status)
                                .map((c) => (
                                    <option key={c.id} value={c.id}>
                                        {c.name}
                                    </option>
                                ))}
                        </select>
                        <select
                            name="sensitivity"
                            defaultValue="org_internal"
                            className="rounded border-slate-300"
                        >
                            <option value="org_internal">
                                Organization internal
                            </option>
                            <option value="department_restricted">
                                Department restricted
                            </option>
                            <option value="finance_confidential">
                                Finance confidential
                            </option>
                            <option value="hr_confidential">
                                HR confidential
                            </option>
                            <option value="executive_confidential">
                                Executive confidential
                            </option>
                        </select>
                        <input
                            name="expires_at"
                            type="date"
                            className="rounded border-slate-300"
                        />
                        <input
                            name="renewal_alert_days"
                            type="number"
                            placeholder="Alert days"
                            className="rounded border-slate-300"
                        />
                        <input
                            name="file"
                            required
                            type="file"
                            accept=".pdf,.jpg,.jpeg,.png,.webp"
                            className="rounded border-slate-300"
                        />
                        <input
                            name="change_note"
                            placeholder="Version note"
                            className="rounded border-slate-300 md:col-span-2"
                        />
                        <button className="rounded bg-indigo-600 px-3 py-2 text-white">
                            Upload
                        </button>
                    </form>
                )}
                {can.retentionManage && (
                    <div className="grid gap-4 lg:grid-cols-2">
                        <form
                            onSubmit={(e) =>
                                post(
                                    e,
                                    route('documents.retention-policies.store'),
                                )
                            }
                            className="grid gap-2 rounded border bg-white p-4 md:grid-cols-2"
                        >
                            <b className="md:col-span-2">Retention policy</b>
                            <input
                                name="code"
                                required
                                placeholder="Code"
                                className="rounded border-slate-300"
                            />
                            <input
                                name="name"
                                required
                                placeholder="Name"
                                className="rounded border-slate-300"
                            />
                            <input
                                name="minimum_retention_days"
                                type="number"
                                min="0"
                                defaultValue="0"
                                className="rounded border-slate-300"
                            />
                            <input
                                name="effective_from"
                                type="date"
                                required
                                className="rounded border-slate-300"
                            />
                            <label>
                                <input
                                    name="legal_hold_required"
                                    type="checkbox"
                                />{' '}
                                Legal hold
                            </label>
                            <button className="rounded bg-slate-800 text-white">
                                Save policy
                            </button>
                        </form>
                        <form
                            onSubmit={(e) =>
                                post(e, route('documents.categories.store'))
                            }
                            className="grid gap-2 rounded border bg-white p-4 md:grid-cols-2"
                        >
                            <b className="md:col-span-2">Document category</b>
                            <input
                                name="code"
                                required
                                placeholder="Code"
                                className="rounded border-slate-300"
                            />
                            <input
                                name="name"
                                required
                                placeholder="Name"
                                className="rounded border-slate-300"
                            />
                            <select
                                name="retention_policy_id"
                                className="rounded border-slate-300"
                            >
                                <option value="">No retention policy</option>
                                {retentionPolicies.map((p) => (
                                    <option key={p.id} value={p.id}>
                                        {p.name}
                                    </option>
                                ))}
                            </select>
                            <select
                                name="default_sensitivity"
                                className="rounded border-slate-300"
                            >
                                <option value="org_internal">
                                    Organization internal
                                </option>
                                <option value="finance_confidential">
                                    Finance confidential
                                </option>
                                <option value="hr_confidential">
                                    HR confidential
                                </option>
                                <option value="executive_confidential">
                                    Executive confidential
                                </option>
                            </select>
                            <label>
                                <input
                                    name="expiry_tracking_enabled"
                                    type="checkbox"
                                />{' '}
                                Track expiry
                            </label>
                            <input
                                name="default_renewal_alert_days"
                                type="number"
                                placeholder="Alert days"
                                className="rounded border-slate-300"
                            />
                            <button className="rounded bg-slate-800 text-white">
                                Save category
                            </button>
                        </form>
                    </div>
                )}
                <div className="overflow-auto rounded border bg-white">
                    <table className="min-w-full text-sm">
                        <thead className="bg-slate-50 text-left">
                            <tr>
                                <th className="p-3">Document</th>
                                <th>Category</th>
                                <th>History</th>
                                <th>Links</th>
                                <th className="p-3">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            {documents.data.map((d) => (
                                <tr key={d.id} className="border-t align-top">
                                    <td className="p-3">
                                        <b>{d.title}</b>
                                        <br />
                                        <small>
                                            {d.document_no} | {d.sensitivity} |
                                            Expires: {d.expires_at || '-'}
                                        </small>
                                    </td>
                                    <td>{d.category?.name || '-'}</td>
                                    <td>
                                        {d.versions.map((v) => (
                                            <div key={v.id}>
                                                v{v.version_no}{' '}
                                                {v.scan_status === 'clean' ? (
                                                    <a
                                                        className="text-indigo-700"
                                                        href={route(
                                                            'documents.versions.download',
                                                            v.id,
                                                        )}
                                                    >
                                                        {v.original_name}
                                                    </a>
                                                ) : (
                                                    `${v.original_name} (${v.scan_status})`
                                                )}
                                            </div>
                                        ))}
                                    </td>
                                    <td>
                                        {d.links.map((l) => (
                                            <div key={l.id}>
                                                {l.linkable_type} ({l.role})
                                            </div>
                                        ))}
                                    </td>
                                    <td className="p-3">
                                        {can.manage && (
                                            <>
                                                <form
                                                    onSubmit={(e) =>
                                                        post(
                                                            e,
                                                            route(
                                                                'documents.versions.store',
                                                                d.id,
                                                            ),
                                                        )
                                                    }
                                                >
                                                    <input
                                                        name="file"
                                                        required
                                                        type="file"
                                                        className="max-w-40 text-xs"
                                                    />
                                                    <input
                                                        name="change_note"
                                                        placeholder="Version note"
                                                        className="w-full rounded border text-xs"
                                                    />
                                                    <button className="text-indigo-700">
                                                        Add version
                                                    </button>
                                                </form>
                                                <form
                                                    onSubmit={(e) =>
                                                        post(
                                                            e,
                                                            route(
                                                                'documents.links.store',
                                                                d.id,
                                                            ),
                                                        )
                                                    }
                                                    className="mt-2 grid gap-1"
                                                >
                                                    <select
                                                        name="linkable_type"
                                                        className="rounded border text-xs"
                                                    >
                                                        <option value="customer">
                                                            Customer
                                                        </option>
                                                        <option value="supplier">
                                                            Supplier
                                                        </option>
                                                        <option value="project">
                                                            Project
                                                        </option>
                                                        <option value="task">
                                                            Task
                                                        </option>
                                                        <option value="fixed_asset">
                                                            Fixed asset
                                                        </option>
                                                    </select>
                                                    <input
                                                        name="linkable_id"
                                                        required
                                                        placeholder="Parent UUID"
                                                        className="rounded border text-xs"
                                                    />
                                                    <select
                                                        name="role"
                                                        className="rounded border text-xs"
                                                    >
                                                        <option value="supporting">
                                                            Supporting
                                                        </option>
                                                        <option value="primary">
                                                            Primary
                                                        </option>
                                                        <option value="generated">
                                                            Generated
                                                        </option>
                                                    </select>
                                                    <button className="text-indigo-700">
                                                        Link
                                                    </button>
                                                </form>
                                            </>
                                        )}
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
