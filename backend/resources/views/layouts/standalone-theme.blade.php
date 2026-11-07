<script src="{{ asset('erp-theme.js') }}"></script>
<style>
    :root {
        color-scheme: light;
        --page: #f5f2ea;
        --surface: #fffdf8;
        --ink: #112e28;
        --line: #aebbb5;
        --link: #17614d;
        --warning: #fff1cd;
        --error: #912e22;
    }
    :root.dark {
        color-scheme: dark;
        --page: #101a18;
        --surface: #192a25;
        --ink: #eaf4ef;
        --line: #657e73;
        --link: #a0d9bf;
        --warning: #47361d;
        --error: #ffc2b3;
    }
    body.standalone { background: var(--page); color: var(--ink); }
    .standalone :is(main, section, article) { background: var(--surface); border-color: var(--line); }
    .standalone :is(input, select, textarea) { background: var(--surface); border-color: var(--line); color: var(--ink); }
    .standalone :is(a, a:visited) { color: var(--link); }
    .standalone :focus-visible { outline: 2px solid var(--link); outline-offset: 3px; }
    .standalone .notice { background: var(--warning); }
    .standalone .error { color: var(--error); }
    .standalone h1 { overflow-wrap: anywhere; }
    .standalone.payment-qr { padding: 0 1rem; overflow-wrap: anywhere; }
    .payment-qr img { background: #fff; max-width: 100%; height: auto; }
    @media (max-width: 30rem) {
        .standalone main { margin: 1rem auto; padding: 1rem; }
        .standalone #recovery ul { grid-template-columns: 1fr; }
    }
</style>
