import test from 'node:test';
import assert from 'node:assert/strict';
import fs from 'node:fs';

const view = fs.readFileSync(
    new URL('../../../resources/views/backend/chamcong/index.blade.php', import.meta.url),
    'utf8',
);
const source = fs.readFileSync(
    new URL('../../../resources/js/frontend/chamcong/chamcong.js', import.meta.url),
    'utf8',
);

test('attendance keeps month period controls, not native full-date controls', () => {
    assert.match(view, /id="month-filter"/);
    assert.match(view, /id="year-filter"/);
    assert.doesNotMatch(view, /type="date"/);
    assert.match(view, /Kỳ chấm công/);
});

test('attendance full-date cells use the shared strict display helper', () => {
    assert.match(source, /shared\/date-field\.js/);
    assert.match(source, /formatDisplayDate/);
    assert.match(source, /formatDate\(item\.ngay_lam\)/);
    assert.doesNotMatch(source, /return `\$\{match\[3\]\}-\$\{match\[2\]\}-\$\{match\[1\]\}`/);
    assert.match(source, /ngay_lam:\s*item\.ngay_lam/);
});
