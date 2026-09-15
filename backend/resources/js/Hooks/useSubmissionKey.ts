import { useRef } from 'react';

// Memory only. Keep the key on failures/uncertain responses; reset only for a new draft.
export default function useSubmissionKey() {
    const key = useRef<string>();
    return {
        headers: () => ({
            'X-Form-Submission': (key.current ??= crypto.randomUUID()),
        }),
        reset: () => {
            key.current = undefined;
        },
    };
}
