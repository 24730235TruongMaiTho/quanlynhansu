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
const view = fs.readFileSync(
    path.join(root, 'resources/views/backend/nghiphep/create.blade.php'),
    'utf8',
);

function permissionsFor(name) {
    const match = source.match(
        new RegExp(`const ${name} = Object\\.freeze\\(\\[([\\s\\S]*?)\\]\\);`, 'u'),
    );

    assert.ok(match, `${name} declaration should exist`);
    return [...match[1].matchAll(/'([^']+)'/gu)].map((item) => item[1]);
}

test('leave create page does not gate self-service on NghiPhep.Insert', () => {
    assert.doesNotMatch(source, /CREATE_PERMISSIONS/u);
    assert.deepEqual(permissionsFor('UPDATE_PERMISSIONS'), ['NghiPhep.Update']);
    assert.deepEqual(permissionsFor('DELETE_PERMISSIONS'), ['NghiPhep.Delete']);
    assert.doesNotMatch(source, /NhanVien\./u);
});

test('leave create page does not render a back action in the page header', () => {
    assert.doesNotMatch(view, /Quay lại/u);
    assert.doesNotMatch(view, /<x-slot:actions>/u);
});

test('leave create page uses the auth-only self-service lookup endpoints', () => {
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

test('leave create page loads the own log without a management permission', () => {
    assert.match(source, /own-leave-contract\.js/u);
    assert.match(source, /OWN_LEAVE_API_URL/u);
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

test('leave submit handler posts create mode to the own leave transport', () => {
    const submitStart = source.indexOf('async function submitLeaveRequest');
    const submitEnd = source.indexOf('async function initialize', submitStart);
    const submitBlock = source.slice(submitStart, submitEnd);
    const requestMatch = submitBlock.match(
        /await requestJson\(\s*(isEdit\s*\?\s*`[^`]+`\s*:\s*[A-Z_]+)\s*,\s*\{\s*method:\s*(isEdit\s*\?\s*'PUT'\s*:\s*'POST')/u,
    );

    assert.ok(requestMatch, 'submit handler should resolve its request transport');

    const resolveUrl = new Function(
        'isEdit',
        'leaveId',
        'NGHI_PHEP_API_URL',
        'OWN_LEAVE_API_URL',
        `return ${requestMatch[1]};`,
    );

    assert.equal(
        resolveUrl(
            false,
            '42',
            '/api/v1/nghi-phep',
            '/api/v1/nghi-phep/cua-toi',
        ),
        '/api/v1/nghi-phep/cua-toi',
    );
    assert.equal(
        resolveUrl(
            true,
            '42',
            '/api/v1/nghi-phep',
            '/api/v1/nghi-phep/cua-toi',
        ),
        '/api/v1/nghi-phep/42',
    );

    const resolveMethod = new Function(
        'isEdit',
        `return ${requestMatch[2]};`,
    );
    assert.equal(resolveMethod(false), 'POST');
    assert.equal(resolveMethod(true), 'PUT');
});
