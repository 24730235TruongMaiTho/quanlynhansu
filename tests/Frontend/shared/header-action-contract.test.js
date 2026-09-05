import test from 'node:test';
import assert from 'node:assert/strict';
import fs from 'node:fs';

const read = (relativePath) => fs.readFileSync(
    new URL(`../../../${relativePath}`, import.meta.url),
    'utf8',
);

test('dynamic role edit/delete controls are icon-only and keep safe accessible names', async () => {
    const source = read('resources/js/frontend/vaitro/vaitro.js');
    const { renderRoleActions } = await import('../../../resources/js/frontend/vaitro/vaitro.js');
    const rendered = renderRoleActions(
        { ma_vt: 7, ten_vt: '<Vai trò>' },
        { canViewPermissions: true, canEdit: true, canDelete: true },
    );

    assert.match(rendered, /data-role-edit="7"[^>]+aria-label="Sửa &lt;Vai trò&gt;"[^>]+title="Sửa &lt;Vai trò&gt;"/);
    assert.match(rendered, /data-role-delete="7"[^>]+aria-label="Xóa &lt;Vai trò&gt;"[^>]+title="Xóa &lt;Vai trò&gt;"/);
    assert.doesNotMatch(rendered, /data-role-edit="7"[\s\S]*?>\s*<i[^>]*><\/i>\s*Sửa\s*</);
    assert.doesNotMatch(rendered, /data-role-delete="7"[\s\S]*?>\s*<i[^>]*><\/i>\s*Xóa\s*</);
    assert.match(rendered, />Phân quyền<\/a>/);
    assert.match(source, /btn-icon-action/);
    assert.match(source, /escapeHtml\(role\.ten_vt\)/);
});

test('salary and leave dynamic edit/delete controls are icon-only while create controls keep text', () => {
    const salary = read('resources/js/frontend/luong/luong.js');
    const coefficient = read('resources/js/frontend/luong/luongHeSo.js');
    const leave = read('resources/js/frontend/nghiphep/create.js');
    const leaveList = read('resources/js/frontend/nghiphep/nghiphep.js');
    const attendance = read('resources/js/frontend/chamcong/chamcong.js');

    assert.match(salary, /class="btn salary-icon-action btn-icon-action"[\s\S]*?data-salary-action="edit"/);
    assert.match(salary, /class="btn salary-icon-action btn-icon-action"[\s\S]*?data-salary-action="delete"/);
    assert.doesNotMatch(salary, /\$\{iconEdit\(\)\}Sửa/);
    assert.doesNotMatch(salary, /\$\{iconDelete\(\)\}Xóa/);
    const createMarker = 'data-salary-action="create-for-employee"';
    const createIndex = salary.indexOf(createMarker);
    assert.notEqual(createIndex, -1, `missing ${createMarker}`);
    const createStart = salary.lastIndexOf('<button', createIndex);
    const createMarkup = salary.slice(createStart, createStart + 760);
    assert.doesNotMatch(createMarkup, /class="[^\"]*btn-icon-action/, 'salary create must not be icon-only');
    assert.match(createMarkup, /\$\{iconCreate\(\)\}Tạo thông tin lương/);

    assert.match(coefficient, /btn-icon-action[\s\S]*?data-coefficient-action="edit"/);
    assert.match(coefficient, /btn-icon-action[\s\S]*?data-coefficient-action="delete"/);
    assert.doesNotMatch(coefficient, /\$\{iconEdit\(\)\}Sửa/);
    assert.doesNotMatch(coefficient, /\$\{iconDelete\(\)\}Xóa/);

    assert.match(leave, /leave-log-edit-btn[\s\S]*?aria-label="Sửa đơn nghỉ phép"/);
    assert.match(leave, /leave-log-delete-btn[\s\S]*?aria-label="Xóa đơn nghỉ phép"/);
    assert.doesNotMatch(leave, /\$\{icon[^}]+\}[\s\S]*?\n\s*Sửa\s*\n/);
    assert.doesNotMatch(leave, /\$\{icon[^}]+\}[\s\S]*?\n\s*Xóa\s*\n/);
    assert.match(leaveList, /Bạn không có quyền xóa đơn nghỉ phép/);
    assert.match(attendance, /Bản ghi chấm công chưa được lưu nên không thể xóa/);
});
