(() => {
    let preference = 'system';
    try {
        const saved = localStorage.getItem('theme');
        if (saved === 'light' || saved === 'dark') preference = saved;
    } catch {
        // Storage may be unavailable in a restricted browser.
    }

    const root = document.documentElement;
    const system = window.matchMedia('(prefers-color-scheme: dark)');
    root.dataset.themePreference = preference;
    root.classList.toggle(
        'dark',
        preference === 'dark' || (preference === 'system' && system.matches),
    );
    system.addEventListener('change', (event) => {
        if (root.dataset.themePreference === 'system') {
            root.classList.toggle('dark', event.matches);
        }
    });
})();
