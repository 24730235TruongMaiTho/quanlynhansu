import assert from 'node:assert/strict';
import fs from 'node:fs';
import test from 'node:test';

const js = fs.readFileSync(
    new URL('../../../resources/js/frontend/nghiphep/nghiphep.js', import.meta.url),
    'utf8',
);
const view = fs.readFileSync(
    new URL('../../../resources/views/backend/nghiphep/index.blade.php', import.meta.url),
    'utf8',
);

test('leave employee filter is explicit and uses canonical page sizes', () => {
    assert.match(view, /class="[^"]*filter-bar[^"]*"/);
    assert.match(view, />Áp dụng bộ lọc</);
    assert.match(view, />Đặt lại</);
    assert.match(view, /<option value="10"/);
    assert.match(view, /<option value="20"/);
    assert.match(view, /<option value="50"/);
});

test('leave UI does not fetch when a filter control changes', () => {
    assert.match(js, /filterForm\?\.addEventListener\(\s*['"]submit['"]/);
    assert.doesNotMatch(js, /department\?\.addEventListener\([\s\S]*?applyEmployeeFilters/);
    assert.doesNotMatch(js, /position\?\.addEventListener\([\s\S]*?applyEmployeeFilters/);
    assert.doesNotMatch(js, /search\?\.addEventListener\([\s\S]*?applyEmployeeFilters/);
});

test('leave tables expose responsive and accessible table markup', () => {
    assert.match(view, /<caption class="visually-hidden">/);
    assert.match(view, /<th[^>]+scope="col"/);
});

test('leave list uses server-side tab-aware pagination for every page', () => {
    assert.match(js, /state\.leavePage/);
    assert.match(js, /state\.leavePerPage/);
    assert.match(js, /state\.activeTab/);
    assert.match(js, /url\.searchParams\.set\(\s*['"]page['"]\s*,\s*String\(state\.leavePage\)/);
    assert.match(js, /url\.searchParams\.set\(\s*['"]per_page['"]\s*,\s*String\(state\.leavePerPage\)/);
    assert.match(js, /url\.searchParams\.set\(\s*['"]tab['"]\s*,\s*state\.activeTab/);
    assert.doesNotMatch(js, /per_page['"]\s*,\s*['"]50['"]/);
    assert.doesNotMatch(js, /filterLeavesForActiveTab\([\s\S]*?\.slice\(/);
    assert.match(js, /result\.counts/);
});
