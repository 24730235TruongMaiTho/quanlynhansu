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
const employeeEditForm = readFileSync(
    new URL('../../../resources/views/backend/nhanvien/partials/edit-form.blade.php', import.meta.url),
    'utf8',
);
const employeeStyles = readFileSync(
    new URL('../../../resources/css/nhanvien/nhanvien.css', import.meta.url),
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

test('employee review uses one continuous separator per row and stacks responsively', () => {
    assert.equal((employeeEditForm.match(/employee-review-row/g) || []).length, 7);
    assert.match(employeeStyles, /\.employee-review-row\s*\{[\s\S]*?border-bottom\s*:\s*1px/);
    assert.match(employeeStyles, /@media\s*\(max-width:\s*575\.98px\)[\s\S]*?\.employee-review-row\s*\{[\s\S]*?grid-template-columns\s*:\s*minmax\(0,\s*1fr\)/);
});

test('employee and shared edit dialogs are centered in the viewport', () => {
    const employeeDialog = employeeStyles.match(/\.employee-edit-dialog\s*\{([\s\S]*?)\n\}/)?.[1];
    const sharedDialog = shellStyles.match(/\.backend-edit-dialog\s*\{([\s\S]*?)\n\s*\}/)?.[1];

    assert.ok(employeeDialog, 'employee dialog rule must exist');
    assert.ok(sharedDialog, 'shared dialog rule must exist');
    for (const dialogRule of [employeeDialog, sharedDialog]) {
        assert.match(dialogRule, /position\s*:\s*fixed\s*;/);
        assert.match(dialogRule, /inset\s*:\s*50%\s+auto\s+auto\s+50%\s*;/);
        assert.match(dialogRule, /transform\s*:\s*translate\(\s*-50%\s*,\s*-50%\s*\)\s*;/);
        assert.match(dialogRule, /margin\s*:\s*0\s*;/);
    }
});
