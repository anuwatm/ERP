import InputError from '@/Components/InputError';
import InputLabel from '@/Components/InputLabel';
import PrimaryButton from '@/Components/PrimaryButton';
import TextInput from '@/Components/TextInput';
import GuestLayout from '@/Layouts/GuestLayout';
import { Head, useForm } from '@inertiajs/react';
import { FormEventHandler } from 'react';

type Invite = { user_id: string; token: string; email: string; name: string };

export default function AcceptInvite({ invite }: { invite: Invite }) {
    const { data, setData, patch, processing, errors, reset } = useForm({
        password: '',
        password_confirmation: '',
    });
    const submit: FormEventHandler = (event) => {
        event.preventDefault();
        patch(
            route('invites.accept.update', {
                user: invite.user_id,
                token: invite.token,
            }),
            { onFinish: () => reset('password', 'password_confirmation') },
        );
    };

    return (
        <GuestLayout>
            <Head title="Accept invite" />
            <form onSubmit={submit}>
                <div className="mb-4 text-sm text-gray-700">
                    Accept invite for {invite.email}
                </div>
                <InputLabel htmlFor="password" value="Password" />
                <TextInput
                    id="password"
                    type="password"
                    value={data.password}
                    className="mt-1 block w-full"
                    onChange={(e) => setData('password', e.target.value)}
                    required
                />
                <InputError message={errors.password} className="mt-2" />
                <div className="mt-4">
                    <InputLabel
                        htmlFor="password_confirmation"
                        value="Confirm Password"
                    />
                    <TextInput
                        id="password_confirmation"
                        type="password"
                        value={data.password_confirmation}
                        className="mt-1 block w-full"
                        onChange={(e) =>
                            setData('password_confirmation', e.target.value)
                        }
                        required
                    />
                </div>
                <PrimaryButton className="mt-4" disabled={processing}>
                    Accept invite
                </PrimaryButton>
            </form>
        </GuestLayout>
    );
}
