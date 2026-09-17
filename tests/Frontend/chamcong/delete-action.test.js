import assert from 'node:assert/strict';
import test from 'node:test';
import fs from 'node:fs';
import { createDeleteAction } from '../../../resources/js/frontend/shared/delete-action.js';

const source = fs.readFileSync('resources/js/frontend/chamcong/chamcong.js', 'utf8');
const view = fs.readFileSync('resources/views/backend/chamcong/index.blade.php', 'utf8');

test('attendance delete derives persisted state from the selected date item, not DOM tr', () => {
    const start = source.indexOf('const deleteAttendanceAction = createDeleteAction({');
    const end = source.indexOf('async function deleteSelectedAttendance', start);
    assert.notEqual(start, -1);
    const block = source.slice(start, end);
    assert.match(block, /findAttendanceByDate\(\s*state\.selectedAttendanceRow\?\.dataset\?\.date/s);
    assert.match(block, /persisted:\s*selectedItem\?\._persisted\s*===\s*true/s);
    assert.doesNotMatch(block, /selectedAttendanceRow\?\._persisted/);
});

test('attendance handler delegates stale selection and reports delete outcomes with shared toasts', () => {
    const actionStart = source.indexOf('const deleteAttendanceAction = createDeleteAction({');
    const actionEnd = source.indexOf('async function deleteSelectedAttendance', actionStart);
    const handlerEnd = source.indexOf('function applyFilters', actionEnd);
    assert.notEqual(actionStart, -1);
    assert.notEqual(actionEnd, -1);
    assert.notEqual(handlerEnd, -1);

    const action = source.slice(actionStart, actionEnd);
    const handler = source.slice(actionEnd, handlerEnd);

    assert.doesNotMatch(handler, /selectedAttendanceId/);
    assert.match(action, /onSuccess:\s*async\s*\(\)\s*=>/s);
    assert.match(action, /Đã xóa chấm công thành công/);
    assert.match(action, /Đã xóa chấm công nhưng chưa tải lại được danh sách/);
    assert.match(action, /variant:\s*'success'/s);
    assert.match(action, /variant:\s*'warning'/s);
    assert.match(action, /variant:\s*'danger'/s);
    assert.match(source, /import \{ createConfirmDialog \} from ['"]\.\.\/shared\/confirm-dialog\.js['"]/);
    assert.match(action, /confirmAction:\s*\(\)\s*=>\s*confirmDialog\.open\(elements\.deleteButton\)/s);
    assert.match(source, /onUnavailable:[\s\S]*?showToast/);
    assert.doesNotMatch(source, /window\.confirm/);
    assert.match(view, /<dialog[\s\S]*?id="attendance-delete-dialog"[\s\S]*?aria-labelledby="attendance-delete-dialog-title"[\s\S]*?aria-describedby="attendance-delete-dialog-description"/);
    assert.match(view, /id="attendance-delete-dialog-cancel"/);
    assert.match(view, /id="attendance-delete-dialog-confirm"/);
});

test('persisted selected attendance sends DELETE while an unsaved row does not', async () => {
    const items = [
        { ma_cc: 17, _persisted: true },
        { ma_cc: null, _persisted: false },
    ];
    let selectedDate = '2026-09-01';
    const rows = { '2026-09-01': items[0], '2026-09-02': items[1] };
    const calls = [];
    const action = createDeleteAction({
        getSelection: () => {
            const item = rows[selectedDate];
            return { id: item?.ma_cc ? String(item.ma_cc) : null, persisted: item?._persisted === true, canDelete: true };
        },
        confirmAction: () => true,
        requestDelete: async (id) => calls.push(id),
    });

    assert.equal(await action(), true);
    selectedDate = '2026-09-02';
    assert.equal(await action(), false);
    assert.deepEqual(calls, ['17']);
});

test('attendance can report an unpersisted selection through the shared guard', async () => {
    let invalidSelection;
    let deleteCalls = 0;

    const action = createDeleteAction({
        getSelection: () => ({ id: null, persisted: false, canDelete: true }),
        onInvalidSelection: (selection) => {
            invalidSelection = selection;
        },
        confirmAction: () => true,
        requestDelete: async () => {
            deleteCalls += 1;
        },
    });

    assert.equal(await action(), false);
    assert.deepEqual(invalidSelection, {
        id: null,
        persisted: false,
        canDelete: true,
    });
    assert.equal(deleteCalls, 0);
});

test('delete action awaits a custom dialog confirmation before DELETE', async () => {
    const button = { disabled: false };
    let resolveConfirmation;
    let deleteCalls = 0;
    const confirmation = new Promise((resolve) => {
        resolveConfirmation = resolve;
    });

    const action = createDeleteAction({
        button,
        getSelection: () => ({ id: '17', persisted: true, canDelete: true }),
        confirmAction: () => confirmation,
        requestDelete: async () => {
            deleteCalls += 1;
        },
    });

    const pending = action();
    await Promise.resolve();
    assert.equal(deleteCalls, 0);

    resolveConfirmation(true);
    assert.equal(await pending, true);
    assert.equal(deleteCalls, 1);
});
