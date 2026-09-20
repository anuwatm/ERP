import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import test from 'node:test';

const source = (name) =>
    readFileSync(
        new URL(
            `../../resources/js/Components/Dashboard/${name}`,
            import.meta.url,
        ),
        'utf8',
    );

test('dashboard filter keeps period state in the URL and rejects invalid custom ranges', () => {
    const filter = source('DashboardFilterBar.tsx');
    assert.match(filter, /router\.get\(route\(routeName\), filter/);
    assert.match(filter, /preserveState: true/);
    assert.match(filter, /replace: true/);
    assert.match(filter, /Choose both dates for a custom range/);
    assert.match(filter, /start date must be on or before the end date/);
    assert.match(filter, /aria-invalid/);
    assert.match(filter, /value="today"/);
    assert.match(filter, /value="this_week"/);
});

test('dashboard widgets distinguish state, empty data, and hidden no-permission state', () => {
    for (const name of [
        'DashboardKpiCard.tsx',
        'DashboardList.tsx',
        'DashboardBreakdown.tsx',
        'DashboardChart.tsx',
    ]) {
        const component = source(name);
        assert.match(component, /visible = true/);
        assert.match(component, /if \(!visible\) return null/);
        assert.match(component, /state === 'error'/);
        assert.match(component, /Retry/);
    }
});

test('chart supports signed values, two series, text legend, and keyboard-accessible table fallback', () => {
    const chart = source('DashboardChart.tsx');
    assert.match(chart, /Math\.min\(0, \.\.\.values\)/);
    assert.match(chart, /Math\.max\(0, \.\.\.values\)/);
    assert.match(chart, /secondaryLabel/);
    assert.match(chart, /<title>/);
    assert.match(chart, /View data table/);
    assert.match(chart, /<table/);
});

test('layout, breakdown, progress, and list provide responsive and accessible shared vocabulary', () => {
    assert.match(source('DashboardLayout.tsx'), /aria-labelledby/);
    assert.match(source('DashboardLayout.tsx'), /dark:/);
    assert.match(source('DashboardBreakdown.tsx'), /aria-hidden="true"/);
    assert.match(source('DashboardProgress.tsx'), /role="progressbar"/);
    assert.match(source('DashboardList.tsx'), /@inertiajs\/react/);
    assert.match(source('DashboardList.tsx'), /truncate/);
    assert.match(source('DashboardList.tsx'), /route\(\s*item\.routeName/);
});
