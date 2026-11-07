<script>
    try {
        const theme = localStorage.getItem('pos-theme');
        if (theme === 'light' || theme === 'dark') document.documentElement.dataset.posTheme = theme;
    } catch {}
</script>
<style>
    @font-face {
        font-family: "Sarabun";
        src: url("/fonts/sarabun-regular-v1.ttf") format("truetype");
        font-weight: 400;
        font-display: swap;
    }
    @font-face {
        font-family: "Sarabun";
        src: url("/fonts/sarabun-bold-v1.ttf") format("truetype");
        font-weight: 700;
        font-display: swap;
    }
    body { font-family: "Sarabun", system-ui, sans-serif; }
</style>
