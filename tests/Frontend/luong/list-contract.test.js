import test from 'node:test';
import assert from 'node:assert/strict';
import fs from 'node:fs';

const js = fs.readFileSync(
    new URL('../../../resources/js/frontend/luong/luong.js', import.meta.url),
    'utf8',
);
const coefficientJs = fs.readFileSync(
    new URL('../../../resources/js/frontend/luong/luongHeSo.js', import.meta.url),
    'utf8',
);
const view = fs.readFileSync(
    new URL('../../../resources/views/backend/luong/index.blade.php', import.meta.url),
    'utf8',
);

test('salary list uses explicit filter submit and canonical page sizes', () => {
    assert.match(view, /<form[^>]+class="[^"]*filter-bar[^"]*"[^>]+id="salary-filter-form"/);
    assert.match(view, /for="search-field"/);
    assert.match(view, /for="department-filter"/);
    assert.match(view, /for="position-filter"/);
    assert.match(view, /Áp dụng bộ lọc/);
    assert.match(view, /Đặt lại/);
    for (const size of [10, 20, 50]) {
        assert.match(view, new RegExp(`option value="${size}"`));
    }
    assert.doesNotMatch(view, /option value="(?:5|15|25)"/);

    assert.match(js, /normalizePaginator/);
    assert.match(js, /salary-filter-form/);
    assert.match(js, /addEventListener\(\s*['"]submit['"]/);
    assert.doesNotMatch(js, /addEventListener\(\s*['"]input['"]/);
    assert.doesNotMatch(js, /addEventListener\(\s*['"]change['"][\s\S]{0,180}applyFilters/);
    assert.match(js, /loadSalaryData\(\s*1/);
});

test('salary and coefficient lists use server paginator metadata without local truncation', () => {
    assert.match(js, /normalizePaginator\(\s*result\.data/);
    assert.match(js, /state\.perPage/);
    assert.match(js, /per_page/);
    assert.doesNotMatch(js, /\.slice\(/);

    assert.match(coefficientJs, /normalizePaginator/);
    assert.match(coefficientJs, /state\.coefficientPage|state\.page/);
    assert.match(coefficientJs, /per_page/);
    assert.match(coefficientJs, /coefficient-pagination/);
    assert.doesNotMatch(coefficientJs, /\.slice\(/);
    assert.match(view, /id="coefficient-pagination"/);
});

test('salary tables expose responsive accessible state contracts', () => {
    assert.match(view, /class="table-responsive"/);
    assert.match(view, /class="table table-hover align-middle mb-0 salary-data-table"/);
    assert.match(view, /<thead class="table-light">/);
    assert.match(view, /<caption class="visually-hidden">/);
    assert.match(view, /<th[^>]+scope="col"/);
    assert.match(view, /salary-tbody/);
    assert.match(view, /salary-coefficient-tbody/);
    assert.match(view, /colspan="14"/);
    assert.match(view, /colspan="7"/);
});
