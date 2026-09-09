import { Head } from '@inertiajs/react';

export default function PortalSignIn() {
    return (
        <>
            <Head title="ERP Portal" />
            <main className="min-h-screen bg-slate-50 px-4 py-8 text-slate-900">
                <div className="mx-auto max-w-md border bg-white p-6">
                    <p className="text-sm font-medium text-indigo-700">
                        ERP Portal
                    </p>
                    <h1 className="mt-2 text-2xl font-bold">
                        Access by email link
                    </h1>
                    <p className="mt-3 text-sm text-slate-600">
                        Use the secure link sent by your ERP contact. The link
                        expires after 30 minutes and can be used once.
                    </p>
                </div>
            </main>
        </>
    );
}
