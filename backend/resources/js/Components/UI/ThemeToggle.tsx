import { useEffect, useState } from 'react';

type Theme = 'system' | 'light' | 'dark';

export default function ThemeToggle({
    className = '',
}: {
    className?: string;
}) {
    const [theme, setTheme] = useState<Theme>(() => {
        if (typeof document === 'undefined') return 'system';

        const preference = document.documentElement.dataset.themePreference;
        return preference === 'light' || preference === 'dark'
            ? preference
            : 'system';
    });

    useEffect(() => {
        const root = document.documentElement;
        root.dataset.themePreference = theme;
        root.classList.toggle(
            'dark',
            theme === 'dark' ||
                (theme === 'system' &&
                    window.matchMedia('(prefers-color-scheme: dark)').matches),
        );
        try {
            localStorage.setItem('theme', theme);
        } catch {
            // Theme remains usable when the browser blocks storage.
        }
    }, [theme]);

    return (
        <select
            aria-label="Color theme"
            value={theme}
            onChange={(event) => setTheme(event.target.value as Theme)}
            className={`min-h-10 rounded-lg border-slate-300 bg-white py-1.5 pl-2 pr-7 text-sm font-medium text-slate-800 focus:border-teal-700 focus:ring-teal-700 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-100 dark:focus:border-teal-300 dark:focus:ring-teal-300 ${className}`}
        >
            <option value="system">System</option>
            <option value="light">Light</option>
            <option value="dark">Dark</option>
        </select>
    );
}
