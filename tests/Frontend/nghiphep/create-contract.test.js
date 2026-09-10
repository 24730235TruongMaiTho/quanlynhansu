import assert from 'node:assert/strict';
import fs from 'node:fs';
import path from 'node:path';
import test from 'node:test';
import { fileURLToPath } from 'node:url';

const root = fileURLToPath(new URL('../../../', import.meta.url));
const source = fs.readFileSync(
    path.join(root, 'resources/js/frontend/nghiphep/create.js'),
    'utf8',
);

function permissionsFor(name) {
    const match = source.match(
        new RegExp(`const ${name} = Object\\.freeze\\(\\[([\\s\\S]*?)\\]\\);`, 'u'),
    );

    assert.ok(match, `${name} declaration should exist`);
    return [...match[1].matchAll(/'([^']+)'/gu)].map((item) => item[1]);
}

test('leave create page uses only canonical NghiPhep permissions', () => {
    assert.deepEqual(permissionsFor('CREATE_PERMISSIONS'), ['NghiPhep.Insert']);
    assert.deepEqual(permissionsFor('UPDATE_PERMISSIONS'), ['NghiPhep.Update']);
    assert.deepEqual(permissionsFor('DELETE_PERMISSIONS'), ['NghiPhep.Delete']);
    assert.doesNotMatch(source, /NhanVien\./u);
});

test('leave create page uses Insert-only lookup endpoints', () => {
    assert.match(source, /\/api\/v1\/nghi-phep\/tao\/loai-phep/u);
    assert.match(source, /\/api\/v1\/nghi-phep\/tao\/phong-ban/u);
    assert.doesNotMatch(source, /\/api\/v1\/nghi-phep\/loai-phep['"`]/u);
    assert.doesNotMatch(source, /\/api\/v1\/nghi-phep\/phong-ban['"`]/u);
});

test('leave update and delete checks do not fall back to create permission', () => {
    const updateStart = source.indexOf('function canUpdateOwnLeave');
    const deleteStart = source.indexOf('function canDeleteOwnLeave');
    const showErrorStart = source.indexOf('function showError');

    assert.doesNotMatch(source.slice(updateStart, deleteStart), /canCreateLeave\(\)/u);
    assert.doesNotMatch(source.slice(deleteStart, showErrorStart), /canCreateLeave\(\)/u);
    assert.match(source.slice(updateStart, deleteStart), /return canAnyPermission\(\s*UPDATE_PERMISSIONS\s*\);/u);
    assert.match(source.slice(deleteStart, showErrorStart), /return canAnyPermission\(\s*DELETE_PERMISSIONS\s*\);/u);
});

test('leave create page loads the own log with Insert instead of Read permission', () => {
    assert.match(source, /const OWN_LEAVE_API_URL\s*=\s*['"]\/api\/v1\/nghi-phep\/cua-toi['"]/u);
    assert.doesNotMatch(source, /const READ_PERMISSIONS/u);

    const permissionStart = source.indexOf('function canReadOwnLeaveLog');
    const permissionEnd = source.indexOf('function canUpdateOwnLeave', permissionStart);
    const permissionBlock = source.slice(permissionStart, permissionEnd);
    assert.match(permissionBlock, /return canCreateLeave\(\);/u);
    assert.doesNotMatch(permissionBlock, /NghiPhep\.Read|READ_PERMISSIONS/u);
});

test('own leave log request does not send a client-selected employee code', () => {
    const loadStart = source.indexOf('async function loadOwnLeaveLog');
    const loadEnd = source.indexOf('function switchOwnLeaveTab', loadStart);
    const loadBlock = source.slice(loadStart, loadEnd);

    assert.match(loadBlock, /OWN_LEAVE_API_URL/u);
    assert.doesNotMatch(loadBlock, /NGHI_PHEP_API_URL/u);
    assert.doesNotMatch(loadBlock, /searchParams\.set\(\s*['"]ma_nv['"]/u);
});
