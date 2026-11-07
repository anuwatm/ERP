import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import Card from '@/Components/UI/Card';
import PageHeader from '@/Components/UI/PageHeader';
import { PageProps } from '@/Types';
import { Head } from '@inertiajs/react';
import DeleteUserForm from './Partials/DeleteUserForm';
import UpdatePasswordForm from './Partials/UpdatePasswordForm';
import UpdateProfileInformationForm from './Partials/UpdateProfileInformationForm';

export default function Edit({
    mustVerifyEmail,
    status,
}: PageProps<{ mustVerifyEmail: boolean; status?: string }>) {
    return (
        <AuthenticatedLayout>
            <Head title="Profile" />

            <div className="mx-auto max-w-7xl space-y-6">
                <PageHeader
                    title="Profile"
                    description="Manage your account information, password and account access."
                />
                <div className="grid items-start gap-6 xl:grid-cols-2">
                    <Card>
                        <UpdateProfileInformationForm
                            mustVerifyEmail={mustVerifyEmail}
                            status={status}
                            className="max-w-xl"
                        />
                    </Card>

                    <Card>
                        <UpdatePasswordForm className="max-w-xl" />
                    </Card>

                    <Card className="xl:col-span-2">
                        <DeleteUserForm className="max-w-xl" />
                    </Card>
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
