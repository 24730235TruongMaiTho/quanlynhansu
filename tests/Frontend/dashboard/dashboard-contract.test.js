import assert from 'node:assert/strict';
import fs from 'node:fs';
import test from 'node:test';

const view = fs.readFileSync(
    new URL('../../../resources/views/backend/tongquan/index.blade.php', import.meta.url),
    'utf8',
);

test('dashboard personal/company fetches remain independent and DOM-safe', () => {
    assert.match(view, /fetchJson\('\/api\/v1\/dashboard\/personal'/u);
    assert.match(view, /fetchJson\('\/api\/v1\/dashboard\/overview'/u);
    assert.match(view, /const tasks = \[fetchPersonalData\(\)\]/u);
    assert.match(view, /if \(hasCompanyWidgets\) tasks\.push\(fetchCompanyData\(\)\)/u);
    assert.match(view, /Promise\.allSettled\(tasks\)/u);
    assert.match(view, /if \(element\) element\.textContent/u);
    assert.match(view, /cell\.textContent = String\(value\)/u);
    assert.match(view, /tbody\.replaceChildren\(\)/u);
    assert.doesNotMatch(view, /console\.(?:warn|error)\s*\(/u);
    assert.doesNotMatch(view, /tbody\.innerHTML\s*=/u);
});

test('dashboard keeps salary personal widget free of amount fields', () => {
    const personalSection = view.slice(view.indexOf('id="personalDashboardSection"'), view.indexOf('</section>', view.indexOf('id="personalDashboardSection"')));

    assert.match(personalSection, /data-personal-widget="salary"/u);
    for (const amountField of ['luong_co_ban', 'thuong', 'phat', 'bao_hiem', 'thue', 'tong_luong']) {
        assert.doesNotMatch(personalSection, new RegExp(amountField, 'u'));
    }
});

test('dashboard personal section keeps widget links without quick-action buttons', () => {
    const personalStart = view.indexOf('id="personalDashboardSection"');
    const personalSection = view.slice(personalStart, view.indexOf('</section>', personalStart));

    assert.doesNotMatch(personalSection, /Cập nhật thông tin/u);
    assert.doesNotMatch(personalSection, /Tạo đơn nghỉ phép/u);
    assert.doesNotMatch(personalSection, /btn-icon-text/u);
    assert.match(personalSection, /href="\{\{ route\('backend\.profile\.edit'\) \}\}"/u);
    assert.match(personalSection, /href="\{\{ route\('backend\.nghiphep\.create'\) \}\}"/u);
});

test('dashboard company markup is guarded by module permissions', () => {
    assert.match(view, /@if \(\$canEmployeeRead\)[\s\S]*?Tổng nhân viên/u);
    assert.match(view, /@if \(\$canDepartmentRead\)[\s\S]*?Tổng phòng ban/u);
    assert.match(view, /@if \(\$canEmployeeRead && \$canDepartmentRead\)[\s\S]*?Nhân viên theo phòng ban/u);
    assert.match(view, /@if \(\$canContractRead\)[\s\S]*?Hợp đồng sắp hết hạn/u);
    assert.match(view, /@if \(\$canAttendanceRead\)[\s\S]*?Báo cáo chấm công/u);
    assert.match(view, /@if \(\$canSalaryRead\)[\s\S]*?Báo cáo lương/u);
    assert.match(view, /@if \(\$canLeaveOverview\)[\s\S]*?Nghỉ phép chờ duyệt/u);
});
