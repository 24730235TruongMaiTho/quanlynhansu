import test from 'node:test';
import assert from 'node:assert/strict';

test('role actions render each button only for its exact permission', async () => {
    const { renderRoleActions } = await import('../../..//resources/js/frontend/vaitro/vaitro.js');
    const role = { ma_vt: 6, ten_vt: 'Vai trò kiểm thử' };

    const editOnly = renderRoleActions(role, {
        canViewPermissions: false,
        canEdit: true,
        canDelete: false,
    });
    assert.match(editOnly, /data-role-edit="6"/);
    assert.doesNotMatch(editOnly, /data-role-delete/);
    assert.doesNotMatch(editOnly, /phan-quyen/);

    const deleteAndPermissions = renderRoleActions(role, {
        canViewPermissions: true,
        canEdit: false,
        canDelete: true,
    });
    assert.match(deleteAndPermissions, /data-role-delete="6"/);
    assert.match(deleteAndPermissions, /href="\/vai-tro\/6\/phan-quyen"/);
    assert.doesNotMatch(deleteAndPermissions, /data-role-edit/);
});
