@include('partials.theme-preference')
<style>
    :root {
        color-scheme: light dark;
        --workspace-bg: light-dark(#f5f2ea, #101a18);
        --workspace-panel: light-dark(#fffdf8, #192a25);
        --workspace-text: light-dark(#112e28, #eaf4ef);
        --workspace-muted: light-dark(#455e55, #b7cbc2);
        --workspace-border: light-dark(#aebbb5, #657e73);
        --workspace-link: light-dark(#17614d, #a0d9bf);
        --workspace-warning: light-dark(#fff1cd, #47361d);
        --workspace-error: light-dark(#912e22, #ffc2b3);
    }
    :root[data-pos-theme="light"] { color-scheme: light; }
    :root[data-pos-theme="dark"] { color-scheme: dark; }
    body.pos-workspace { background: var(--workspace-bg); color: var(--workspace-text); font-family: "Sarabun", system-ui, sans-serif; }
    .pos-workspace header { flex-wrap: wrap; }
    .pos-workspace :is(section, article) { min-width: 0; background: var(--workspace-panel); border-color: var(--workspace-border); }
    .pos-workspace :is(input, select, textarea) { max-width: 100%; color: var(--workspace-text); background: var(--workspace-panel); border-color: var(--workspace-border); }
    .pos-workspace :is(button, summary) { min-height: 2.75rem; }
    .pos-workspace :is(a, a:visited) { color: var(--workspace-link); text-underline-offset: .2em; }
    .pos-workspace :is(small, .hint) { color: var(--workspace-muted); }
    .pos-workspace :is(.notice, .note, #message) { background: var(--workspace-warning); color: var(--workspace-text); }
    .pos-workspace :is(.error, [role="alert"]) { color: var(--workspace-error); }
    .pos-workspace :is(td, th) { border-color: var(--workspace-border); }
    .pos-workspace :focus-visible { outline: 2px solid var(--workspace-link); outline-offset: 3px; }
    .pos-workspace h1 { overflow-wrap: anywhere; }
</style>
