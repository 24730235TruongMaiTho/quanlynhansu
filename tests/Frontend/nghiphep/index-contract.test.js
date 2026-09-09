import assert from 'node:assert/strict';
import fs from 'node:fs';
import path from 'node:path';
import test from 'node:test';
import { fileURLToPath } from 'node:url';

const root = fileURLToPath(new URL('../../../', import.meta.url));
const read = (relativePath) => fs.readFileSync(path.join(root, relativePath), 'utf8');
const view = read('resources/views/backend/nghiphep/index.blade.php');
const js = read('resources/js/frontend/nghiphep/nghiphep.js');

test('leave header omits calendar/create actions while preserving history controls', () => {
    assert.doesNotMatch(view, /id="calendar-btn"/u);
    assert.doesNotMatch(view, /Lịch nghỉ/u);
    assert.doesNotMatch(view, /id="create-btn"/u);
    assert.doesNotMatch(view, /Thêm nghỉ phép/u);

    assert.doesNotMatch(js, /calendarButton/u);
    assert.doesNotMatch(js, /createButton/u);
    assert.doesNotMatch(js, /leave:calendar/u);
    assert.doesNotMatch(js, /elements\.createButton/u);

    assert.match(view, /id="history-tab"/u);
    assert.match(view, /id="leave-history-filter-form"/u);
    assert.match(view, /id="all-leaves-btn"/u);
    assert.match(view, /Xem tất cả lịch nghỉ/u);
});

test('canonical leave table exposes a stable anchor and dedicated approval control', () => {
    const view = read('resources/views/backend/nghiphep/index.blade.php');
    const source = read('resources/js/frontend/nghiphep/nghiphep.js');

    assert.match(view, /id="leave-table-card"/);
    assert.match(view, /data-nghi-phep-can-approve=/);
    assert.match(view, /NghiPhepPermission::Duyet->value/);
    assert.doesNotMatch(view, /Gate::allows\('department-manager'\)/);
    assert.match(source, /canApproveLeaves/);
    assert.match(source, /NghiPhep\.Approve/);
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
    const actionStart = js.indexOf('async function approveLeave');
    const actionEnd = js.indexOf('function clearEmployeeFilters', actionStart);
    const actionBlock = js.slice(actionStart, actionEnd);

    assert.match(actionBlock, /method: 'PATCH'/);
    assert.match(actionBlock, /trang_thai_duyet: 1/);
    assert.doesNotMatch(actionBlock, /ma_nv:/);
    assert.doesNotMatch(actionBlock, /ma_pb:/);
});

test('approve-only actors receive a direct pending-row approval action', () => {
    assert.match(js, /leave\.trang_thai_duyet\s*===\s*0[\s\S]*?canApproveLeaves\(\)/u);
    assert.match(js, /data-leave-action="approve"/u);
    assert.match(js, /PERMISSION_CODES\.APPROVE/u);
});

test('generic leave edit payload cannot reopen a processed leave', () => {
    const source = read('resources/js/frontend/nghiphep/nghiphep.js');
    const payloadStart = source.indexOf('function buildLeavePayload');
    const payloadEnd = source.indexOf('function validateLeavePayload', payloadStart);
    const payloadBlock = source.slice(payloadStart, payloadEnd);

    assert.doesNotMatch(payloadBlock, /trang_thai_duyet/);
});

test('history tab exposes a server-seeded native date range and all-company action', () => {
    assert.match(view, /id="history-from-date"[^>]*type="date"/u);
    assert.match(view, /id="history-to-date"[^>]*type="date"/u);
    assert.match(view, /value="\{\{\s*\$historyFrom\s*\}\}"/u);
    assert.match(view, /value="\{\{\s*\$historyTo\s*\}\}"/u);
    assert.match(view, /Xem tất cả lịch nghỉ/u);
    assert.match(js, /history-from-date|historyFromDate/u);
    assert.match(js, /history-to-date|historyToDate/u);
    assert.match(js, /tu_ngay/u);
    assert.match(js, /den_ngay/u);
    assert.doesNotMatch(js, /new Date\s*\(/u);
});

test('leave table keeps eight columns and eight-column render states', () => {
    const tableStart = view.indexOf('id="leave-table-card"');
    const tableEnd = view.indexOf('</table>', tableStart);
    const leaveTable = view.slice(tableStart, tableEnd);
    const headerRow = leaveTable.match(/<thead[\s\S]*?<tr>([\s\S]*?)<\/tr>/u)?.[1] || '';
    const leaveRenderStart = js.indexOf('function renderLeaves');
    const leaveRenderEnd = js.indexOf('async function loadPendingLeaves', leaveRenderStart);
    const leaveRenderBlock = js.slice(leaveRenderStart, leaveRenderEnd);
    const pendingStart = js.indexOf('async function loadPendingLeaves');
    const historyStart = js.indexOf('async function loadProcessedLeavesForEmployee', pendingStart);
    const leaveLoadBlock = js.slice(pendingStart, js.indexOf('async function refreshLeaveData', historyStart));

    assert.equal((headerRow.match(/<th\b/gu) || []).length, 8);
    assert.match(leaveTable, /<td\s+colspan="8"/u);
    assert.doesNotMatch(leaveTable, /colspan="9"/u);
    assert.match(leaveRenderBlock, /colspan="8"/u);
    assert.doesNotMatch(leaveRenderBlock, /colspan="9"/u);
    assert.doesNotMatch(leaveLoadBlock, /colspan="9"/u);
});

test('history date filters are scoped to history requests, not pending requests', () => {
    const pendingStart = js.indexOf('async function loadPendingLeaves');
    const historyStart = js.indexOf('async function loadProcessedLeavesForEmployee', pendingStart);
    const refreshStart = js.indexOf('async function refreshLeaveData', historyStart);
    const pendingBlock = js.slice(pendingStart, historyStart);
    const historyBlock = js.slice(historyStart, refreshStart);

    assert.doesNotMatch(pendingBlock, /appendHistoryFilters\(url\)/u);
    assert.doesNotMatch(pendingBlock, /tu_ngay|den_ngay|state\.historyFilters/u);
    assert.match(historyBlock, /appendHistoryFilters\(url\)/u);
    assert.match(historyBlock, /tab', 'history'/u);
    assert.doesNotMatch(historyBlock, /!state\.selectedEmployee\?\.ma_nv/u);
    assert.match(pendingBlock, /pending:\s*result\.counts\?\.pending/u);
    assert.match(historyBlock, /history:\s*result\.counts\?\.history/u);
});

test('history filter is hidden on pending and shown when switching to history', () => {
    const formTag = view.match(/<form[^>]*id="leave-history-filter-form"[^>]*>/u)?.[0] || '';
    const switchStart = js.indexOf('function switchTab');
    const switchEnd = js.indexOf('function showAllLeaveHistory', switchStart);
    const switchBlock = js.slice(switchStart, switchEnd);

    assert.match(formTag, /\bhidden\b/u);
    assert.match(switchBlock, /historyFilterForm\.hidden\s*=\s*tab\s*!==\s*['"]history['"]/u);
});

test('show all history syncs and validates the visible range before changing scope', () => {
    const showAllStart = js.indexOf('function showAllLeaveHistory');
    const showAllEnd = js.indexOf('function showModalMessage', showAllStart);
    const showAllBlock = js.slice(showAllStart, showAllEnd);
    const syncIndex = showAllBlock.indexOf('syncHistoryFiltersFromUI()');
    const validateIndex = showAllBlock.indexOf('validateHistoryFilters(filters)');
    const clearEmployeeIndex = showAllBlock.indexOf('state.selectedEmployee = null');
    const switchTabIndex = showAllBlock.indexOf("switchTab('history')");

    assert.match(showAllBlock, /if \(!validateHistoryFilters\(filters\)\) return;/u);
    assert.ok(syncIndex >= 0 && syncIndex < clearEmployeeIndex);
    assert.ok(validateIndex >= 0 && validateIndex < clearEmployeeIndex);
    assert.ok(validateIndex < switchTabIndex);
});

test('leave rows render direct action controls instead of selection radios and shared buttons', () => {
    assert.match(view, /<th\s+scope="col">Thao tác<\/th>/u);
    assert.doesNotMatch(view, /id="edit-leave-btn"/u);
    assert.doesNotMatch(view, /id="delete-leave-btn"/u);
    assert.doesNotMatch(view, /id="approve-leave-btn"/u);
    assert.doesNotMatch(js, /selectedLeaveId|selected-leave|leave-radio/u);
    assert.match(js, /data-leave-action="edit"/u);
    assert.match(js, /data-leave-action="delete"/u);
    assert.match(js, /data-leave-action="approve"/u);
    assert.match(js, /data-leave-id=/u);
    assert.match(js, /leave\.trang_thai_duyet\s*===\s*0/u);
});

test('history loads companywide without an employee and initial refresh loads both scopes', () => {
    const pendingStart = js.indexOf('async function loadPendingLeaves');
    const historyStart = js.indexOf('async function loadProcessedLeavesForEmployee');
    const historyEnd = js.indexOf('async function refreshLeaveData', historyStart);
    const pendingBlock = js.slice(pendingStart, historyStart);
    const historyBlock = js.slice(historyStart, historyEnd);
    const initializeStart = js.indexOf('async function initialize');
    const initializeEnd = js.indexOf('initialize();', initializeStart);
    const initializeBlock = js.slice(initializeStart, initializeEnd);

    assert.match(pendingBlock, /tab', 'pending'/u);
    assert.doesNotMatch(pendingBlock, /ma_nv|appendHistoryFilters\(url\)/u);
    assert.match(historyBlock, /tab', 'history'/u);
    assert.match(historyBlock, /if \(state\.selectedEmployee\?\.ma_nv\)/u);
    assert.match(historyBlock, /appendHistoryFilters\(url\)/u);
    assert.doesNotMatch(historyBlock, /!state\.selectedEmployee\?\.ma_nv/u);
    assert.match(initializeBlock, /Promise\.all\(\[/u);
    assert.match(initializeBlock, /loadProcessedLeavesForEmployee\(\{\s*render:\s*false\s*\}\)/u);
    assert.match(initializeBlock, /loadPendingLeaves\(/u);
    assert.match(initializeBlock, /loadProcessedLeavesForEmployee\(/u);
    assert.match(js, /state\.counts\s*=\s*\{\s*\.\.\.state\.counts/u);
});

test('approve-only actors are not marked read-only', () => {
    const initializeStart = js.indexOf('async function initialize');
    const initializeEnd = js.indexOf('initialize();', initializeStart);
    const initializeBlock = js.slice(initializeStart, initializeEnd);
    const readOnlyStart = initializeBlock.indexOf('const readOnly');
    const readOnlyEnd = initializeBlock.indexOf('if (elements.readOnlyBadge)', readOnlyStart);
    const readOnlyBlock = initializeBlock.slice(readOnlyStart, readOnlyEnd);

    assert.match(readOnlyBlock, /!canApproveLeaves\(\)/u);
});
