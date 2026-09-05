import test from 'node:test';
import assert from 'node:assert/strict';
import fs from 'node:fs';

const view = fs.readFileSync(
    new URL('../../../resources/views/backend/luong/index.blade.php', import.meta.url),
    'utf8',
);
const salaryJs = fs.readFileSync(
    new URL('../../../resources/js/frontend/luong/luong.js', import.meta.url),
    'utf8',
);
const coefficientJs = fs.readFileSync(
    new URL('../../../resources/js/frontend/luong/luongHeSoCreateUpdate.js', import.meta.url),
    'utf8',
);
const coefficientListJs = fs.readFileSync(
    new URL('../../../resources/js/frontend/luong/luongHeSo.js', import.meta.url),
    'utf8',
);

test('coefficient date fields are strict Vietnamese display controls', () => {
    for (const id of ['coefficient-from-date', 'coefficient-to-date']) {
        const field = view.slice(view.indexOf(`id="${id}"`), view.indexOf(`id="${id}"`) + 500);
        assert.match(field, /type="text"/);
        assert.match(field, /placeholder="dd\/mm\/yyyy"/);
        assert.match(field, /inputmode="numeric"/);
        assert.match(field, /maxlength="10"/);
        assert.match(field, new RegExp(`${id}-error`));
        if (id === 'coefficient-from-date') {
            assert.match(field, /required/);
        }
    }
});

test('coefficient and salary date surfaces use shared strict formatting', () => {
    assert.match(coefficientJs, /shared\/date-field\.js/);
    assert.match(coefficientJs, /formatDisplayDate/);
    assert.match(coefficientJs, /toIsoDate/);
    assert.match(coefficientJs, /const fromIso[\s\S]{0,80}toIsoDate/);
    assert.match(coefficientJs, /const toIso[\s\S]{0,100}toIsoDate/);
    assert.match(coefficientJs, /if \(!toIso\)/);
    assert.match(coefficientJs, /X-CSRF-TOKEN/);
    assert.match(coefficientListJs, /shared\/date-field\.js/);
    assert.match(coefficientListJs, /formatDisplayDate/);
    assert.doesNotMatch(salaryJs, /toLocaleDateString/);
    assert.match(salaryJs, /shared\/date-field\.js/);
    assert.match(salaryJs, /formatDisplayDate/);
});
