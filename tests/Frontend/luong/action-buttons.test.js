import test from 'node:test';
import assert from 'node:assert/strict';
import fs from 'node:fs';

const source = fs.readFileSync(
    new URL('../../../resources/js/frontend/luong/luongHeSo.js', import.meta.url),
    'utf8',
);
const salarySource = fs.readFileSync(
    new URL('../../../resources/js/frontend/luong/luong.js', import.meta.url),
    'utf8',
);
const mutationSource = fs.readFileSync(
    new URL('../../../resources/js/frontend/luong/luongHeSoCreateUpdate.js', import.meta.url),
    'utf8',
);

test('salary coefficient actions expose direct accessible button controls', () => {
    assert.match(source, /bi-eye/);
    assert.match(source, /bi-pencil-square/);
    assert.match(source, /bi-trash/);

    for (const action of ['view', 'edit', 'delete']) {
        const marker = `data-coefficient-action="${action}"`;
        const markerIndex = source.indexOf(marker);

        assert.notEqual(markerIndex, -1, `missing ${marker}`);

        const markup = source.slice(markerIndex, markerIndex + 220);
        assert.match(markup, /type="button"/, `${action} must be a button`);
        assert.match(markup, /aria-label="[^"]+"/, `${action} needs an accessible name`);
    }
});

test('salary row actions keep view/create text while edit/delete are icon-only', () => {
    for (const icon of ['bi-eye', 'bi-pencil-square', 'bi-trash', 'bi-plus-circle']) {
        assert.match(salarySource, new RegExp(icon));
    }

    for (const label of ['Xem chi tiết']) {
        assert.match(salarySource, new RegExp(label));
    }

    assert.doesNotMatch(salarySource, /function\s+iconCoefficient\s*\(/u);
    assert.doesNotMatch(salarySource, /data-salary-action="coefficient"/u);

    for (const [action, label] of [
        ['edit', 'Sửa'],
        ['delete', 'Xóa'],
    ]) {
        const marker = `data-salary-action="${action}"`;
        const markerIndex = salarySource.indexOf(marker);

        assert.notEqual(markerIndex, -1, `missing ${marker}`);

        const markupStart = salarySource.lastIndexOf('<button', markerIndex);
        const markup = salarySource.slice(markupStart, markupStart + 520);
        assert.match(markup, /class="[^"]*btn-icon-action/, `${action} must use the icon-only contract`);
        assert.match(markup, /title="[^"]+"/, `${action} needs a hover label`);
        assert.match(markup, /aria-label="[^"]+"/, `${action} needs an accessible name`);
        const escapedLabel = label.replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
        assert.doesNotMatch(markup, new RegExp(`>\\s*${escapedLabel}\\s*<\\/button>`), `${action} must not expose a visible text label`);
    }

    const createMarker = 'data-salary-action="create-for-employee"';
    const createIndex = salarySource.indexOf(createMarker);
    assert.notEqual(createIndex, -1, `missing ${createMarker}`);
    const createStart = salarySource.lastIndexOf('<button', createIndex);
    const createMarkup = salarySource.slice(createStart, createStart + 760);
    assert.doesNotMatch(createMarkup, /class="[^"]*btn-icon-action/, 'create must keep the icon-and-text contract');
    assert.match(createMarkup, /title="Tạo thông tin lương"/);
    assert.match(createMarkup, /aria-label="Tạo thông tin lương cho [^"]+"/);
    assert.match(createMarkup, /\$\{iconCreate\(\)\}(?:Tạo thông tin lương|<span data-button-label>Tạo thông tin lương<\/span>)/);
});

test('salary employee rows select coefficient context with keyboard without hijacking controls', () => {
    assert.match(salarySource, /data-salary-row/u);
    assert.match(salarySource, /data-employee-code=/u);
    assert.match(salarySource, /data-employee-name=/u);
    assert.match(salarySource, /tabindex="0"/u);
    assert.match(salarySource, /aria-selected="\$\{[^}]+\}"/u);
    assert.match(source, /\[data-salary-row\]/u);
    assert.match(source, /salary-row-selected/u);
    assert.match(source, /aria-selected/u);
    assert.match(source, /Enter/u);
    assert.match(source, /event\.key\s*!==\s*['"] ['"]/u);
    assert.match(source, /button,\s*a,\s*input,\s*select,\s*textarea,\s*label,\s*\[role="button"\]/u);
    assert.match(source, /salary-coefficient-card[\s\S]*scrollIntoView/u);
    assert.doesNotMatch(source, /data-salary-action="coefficient"/u);
});

test('coefficient mutations use delete endpoint and lock controls in flight', () => {
    assert.match(mutationSource, /method:\s*['"]DELETE['"]/);
    assert.match(mutationSource, /button\.disabled\s*=\s*true/);
    assert.match(mutationSource, /elements\.submit\.disabled\s*=\s*true/);
    assert.match(mutationSource, /window\.confirm/);
});
