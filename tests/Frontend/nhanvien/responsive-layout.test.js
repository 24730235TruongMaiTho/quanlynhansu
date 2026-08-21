import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import test from 'node:test';

const shellStyles = readFileSync(
    new URL('../../../public/backend/css/style.css', import.meta.url),
    'utf8',
);
const employeeIndex = readFileSync(
    new URL('../../../resources/views/backend/nhanvien/index.blade.php', import.meta.url),
    'utf8',
);

test('canonical shell lets employee table overflow stay inside table-responsive', () => {
    const mainContent = shellStyles.match(/\.main-content\s*\{([\s\S]*?)\n\s*\}/)?.[1];

    assert.ok(mainContent, 'canonical .main-content rule must exist');
    assert.match(mainContent, /flex\s*:\s*1\s*;/);
    assert.match(mainContent, /min-width\s*:\s*0\s*;/);
    assert.doesNotMatch(mainContent, /overflow(?:-x)?\s*:/);
    assert.match(employeeIndex, /<div class="table-responsive">[\s\S]*?<table/);
});

test('ultra-narrow topbar hides only its placeholder search and keeps employee filters', () => {
    const narrowTopbar = shellStyles.match(
        /@media\s*\(max-width:\s*360px\)\s*\{([\s\S]*)\}\s*$/,
    )?.[1];

    assert.ok(narrowTopbar, 'a dedicated 360px topbar rule must exist');
    assert.match(narrowTopbar, /\.top-bar \.search-box\s*\{[\s\S]*?display\s*:\s*none\s*;/);
    assert.match(employeeIndex, /data-employee-filter/);
});
