import { router } from '@inertiajs/react';
import axios from 'axios';
import { useEffect, useState } from 'react';

export default function NetworkFailureNotice() {
    const [visible, setVisible] = useState(false);

    useEffect(() => {
        const removeException = router.on('exception', (event) => {
            const error = event.detail.exception;
            if (!axios.isAxiosError(error) || error.response) return;
            event.preventDefault();
            const activeDialog = Array.from(
                document.querySelectorAll('[role="dialog"] header'),
            ).some((header) => header.getClientRects().length > 0);
            if (activeDialog) {
                window.dispatchEvent(new Event('app:network-failure'));
            } else {
                setVisible(true);
            }
        });
        const removeSuccess = router.on('success', () => setVisible(false));
        return () => {
            removeException();
            removeSuccess();
        };
    }, []);

    if (!visible) return null;

    return (
        <div
            role="alert"
            className="fixed bottom-4 left-4 right-4 z-[100] flex items-center justify-between gap-4 rounded-lg border border-amber-400 bg-amber-50 p-4 text-sm text-amber-950 shadow-lg dark:bg-amber-950 dark:text-amber-100"
        >
            <p>
                เชื่อมต่อไม่สำเร็จ ยังยืนยันผลการบันทึกไม่ได้
                กรุณาตรวจเครือข่ายแล้วลองบันทึกอีกครั้ง
            </p>
            <button
                type="button"
                onClick={() => setVisible(false)}
                className="shrink-0 underline"
            >
                ปิดข้อความ
            </button>
        </div>
    );
}
