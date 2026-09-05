import assert from 'node:assert/strict';
import fs from 'node:fs';
import test from 'node:test';

const js = fs.readFileSync(
    new URL('../../../resources/js/frontend/chamcong/chamcong.js', import.meta.url),
    'utf8',
);
const view = fs.readFileSync(
    new URL('../../../resources/views/backend/chamcong/index.blade.php', import.meta.url),
    'utf8',
);

test('attendance list has explicit filter submission and canonical page sizes', () => {
    assert.match(view, /class="[^"]*filter-bar[^"]*"/);
    assert.match(view, />Áp dụng bộ lọc</);
    assert.match(view, />Đặt lại</);
    assert.match(view, /<option value="10"/);
    assert.match(view, /<option value="20"/);
    assert.match(view, /<option value="50"/);
    assert.doesNotMatch(view, /<option value="5">/);
    assert.doesNotMatch(view, /<option value="15"/);
    assert.doesNotMatch(view, /<option value="25"/);
});

test('attendance UI does not fetch when a filter control changes', () => {
    assert.match(js, /filterForm\?\.addEventListener\(\s*['"]submit['"]/);
    assert.doesNotMatch(js, /month\?\.addEventListener\(\s*['"]change['"]\s*,\s*applyFilters/);
    assert.doesNotMatch(js, /department\?\.addEventListener\(\s*['"]change['"]\s*,\s*applyFilters/);
    assert.doesNotMatch(js, /search\?\.addEventListener\(\s*['"]input['"][\s\S]*?applyFilters/);
});

test('attendance template download uses the canonical protected endpoint', () => {
    assert.match(js, /CHAM_CONG_IMPORT_TEMPLATE_API_URL\s*=\s*['"]\/api\/v1\/cham-cong\/template['"]/);
    assert.doesNotMatch(js, /\/api\/v1\/cham-cong\/import-template/);
});

test('attendance tables expose the accessible responsive table contract', () => {
    assert.match(view, /<div class="table-responsive[^"]*">[\s\S]*<table class="[^"]*table-hover[^\"]*align-middle/);
    assert.match(view, /<caption class="visually-hidden">/);
    assert.match(view, /<th[^>]+scope="col"/);
});
