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
const permissions = fs.readFileSync(
    new URL('../../../resources/js/frontend/luong/luongPermissions.js', import.meta.url),
    'utf8',
);
const salaryStatus = await import(
    '../../../resources/js/frontend/luong/salary-status.js'
);

test('salary list uses explicit filter submit and canonical page sizes', () => {
    assert.match(view, /<form[^>]+class="[^"]*filter-bar[^"]*"[^>]+id="salary-filter-form"/);
    assert.match(view, /for="search-field"/);
    assert.match(view, /for="department-filter"/);
    assert.match(view, /for="position-filter"/);
    assert.doesNotMatch(view, /<svg[^>]*>[^<]*(?:circle|path)/u);
    assert.doesNotMatch(view, /input-group-text[\s\S]*?search-field/u);
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

test('employee salary role cannot see cross-employee filters', () => {
    assert.match(view, /data-salary-employee-filter/u);
    assert.match(permissions, /getUser\(\)\?\.ma_vt/);
    assert.match(permissions, /applyEmployeeScopeVisibility/);
    assert.match(permissions, /element\.hidden\s*=\s*selfOnly/);
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

test('salary selected cells stay opaque across sticky columns and status messages can mark completion', () => {
    assert.match(view, /salary-row-selected[\s\S]{0,180}background:\s*#[0-9a-f]{6}\s*!important/iu);
    assert.doesNotMatch(view, /salary-row-selected[\s\S]{0,180}background:\s*rgba\(/iu);
    assert.equal(salaryStatus.isSalaryCalculationComplete({ trang_thai_tinh_luong: ' READY ' }), true);
    assert.equal(salaryStatus.isSalaryCalculationComplete({ thong_bao_tinh_luong: '  hoàn TẤT tính LƯƠNG  ' }), true);
    assert.equal(salaryStatus.isSalaryCalculationComplete({ thong_bao_tinh_luong: 'Đang kiểm tra' }), false);
    assert.equal(salaryStatus.getSalaryStatusText({ thong_bao_tinh_luong: 'Hoàn tất tính lương' }), 'Hoàn tất tính lương');
    assert.match(js, /text-bg-success/u);
    assert.match(js, /Hoàn tất tính lương/u);
});

test('salary detail rows keep employee name and code without generated avatar initials', () => {
    assert.doesNotMatch(js, /function\s+getInitials\s*\(/u);
    assert.doesNotMatch(js, /class="avatar"/u);
    assert.doesNotMatch(view, /\.salary-page\s+\.avatar\s*\{/u);
    assert.match(js, /class="employee-name"/u);
    assert.match(js, /employeeCode/);
});
