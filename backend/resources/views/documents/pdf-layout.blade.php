/* DomPDF uses its own media/layout engine; keep the browser grid layout separate. */
.sheet { min-height: 0; margin: 0; padding: 0; max-width: none; width: 100%; }
.header, .parties, .signatures, .grid { display: table; width: 100%; table-layout: fixed; }
.header > div, .parties > div, .signatures > div, .grid > div { display: table-cell; vertical-align: top; }
.header > div:first-child { width: 60%; }
.header > div:last-child { width: 40%; }
.brand > .logo { float: left; margin-right: 10px; }
.brand > div:last-child { margin-left: 76px; }
.meta { display: block; }
.meta strong { display: block; float: left; clear: left; width: 45%; }
.meta span { display: block; margin-left: 47%; min-height: 18px; }
.totals { display: block; width: 100%; }
.totals > div { float: left; width: 55%; }
.totals > table { float: right; width: 40%; }
.totals::after { content: ""; display: block; clear: both; }
.sheet > table { table-layout: fixed; }
.sheet > table th, .sheet > table td { padding: 4px; word-wrap: break-word; }
.sheet > table th { width: auto !important; }
.sheet > table th:nth-child(1) { width: 4% !important; }
.sheet > table th:nth-child(2) { width: 30% !important; }
.sheet > table th:nth-child(3) { width: 8% !important; }
.sheet > table th:nth-child(4) { width: 7% !important; }
.sheet > table th:nth-child(5) { width: 14% !important; }
.sheet > table th:nth-child(6) { width: 13% !important; }
.sheet > table th:nth-child(7) { width: 9% !important; }
.sheet > table th:nth-child(8) { width: 15% !important; }
.signatures { clear: both; margin-top: 40px; page-break-inside: avoid; }
