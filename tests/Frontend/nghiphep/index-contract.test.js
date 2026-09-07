import assert from 'node:assert/strict';
import fs from 'node:fs';
import path from 'node:path';
import test from 'node:test';
import { fileURLToPath } from 'node:url';

const root = fileURLToPath(new URL('../../../', import.meta.url));
const read = (relativePath) => fs.readFileSync(path.join(root, relativePath), 'utf8');

test('canonical leave table exposes a stable anchor and manager-only approval control', () => {
    const view = read('resources/views/backend/nghiphep/index.blade.php');
    const source = read('resources/js/frontend/nghiphep/nghiphep.js');

    assert.match(view, /id="leave-table-card"/);
    assert.match(view, /data-nghi-phep-can-approve=/);
    assert.match(view, /Gate::allows\('department-manager'\)/);
    assert.match(source, /canApproveLeaves/);
    assert.match(source, /nghiPhepCanApprove/);
    assert.match(source, /restoreLeaveTableAnchor/);
    assert.match(source, /scrollIntoView\(\{ behavior: 'auto', block: 'start' \}\)/);

    const dataLoadStart = source.indexOf('// Pending global');
    const dataLoadEnd = source.indexOf(']);', dataLoadStart);
    const finalAnchorRestore = source.lastIndexOf('restoreLeaveTableAnchor();');
    assert.ok(dataLoadStart >= 0);
    assert.ok(dataLoadEnd > dataLoadStart);
    assert.ok(finalAnchorRestore > dataLoadEnd);
});

test('leave badges use server totals, not only the current page rows', () => {
    const source = read('resources/js/frontend/nghiphep/nghiphep.js');
    const updateCounts = source.slice(
        source.indexOf('function updateLeaveCounts'),
        source.indexOf('function leavePaginator'),
    );

    assert.match(updateCounts, /state\.counts\?\.pending/);
    assert.match(updateCounts, /state\.pendingPaginator\?\.total/);
    assert.match(updateCounts, /state\.counts\?\.history/);
    assert.doesNotMatch(updateCounts, /String\(state\.pendingRows\.length\)/);
});

test('canonical approval PATCH sends only the validated status', () => {
    const source = read('resources/js/frontend/nghiphep/nghiphep.js');
    const actionStart = source.indexOf('async function approveSelectedLeave');
    const actionEnd = source.indexOf('function clearEmployeeFilters', actionStart);
    const actionBlock = source.slice(actionStart, actionEnd);

    assert.match(actionBlock, /method: 'PATCH'/);
    assert.match(actionBlock, /trang_thai_duyet: 1/);
    assert.doesNotMatch(actionBlock, /ma_nv:/);
    assert.doesNotMatch(actionBlock, /ma_pb:/);
});

test('generic leave edit payload cannot reopen a processed leave', () => {
    const source = read('resources/js/frontend/nghiphep/nghiphep.js');
    const payloadStart = source.indexOf('function buildLeavePayload');
    const payloadEnd = source.indexOf('function validateLeavePayload', payloadStart);
    const payloadBlock = source.slice(payloadStart, payloadEnd);

    assert.doesNotMatch(payloadBlock, /trang_thai_duyet/);
});
