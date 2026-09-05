import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import test from 'node:test';

import { createDeleteAction } from '../../../resources/js/frontend/shared/delete-action.js';

const read = (path) => readFileSync(new URL(path, import.meta.url), 'utf8');

class FakeButton {
    constructor() {
        this.disabled = false;
        this.title = 'Xóa bản ghi';
        this.attributes = { 'aria-label': 'Xóa bản ghi' };
    }

    setAttribute(name, value) {
        this.attributes[name] = String(value);
    }

    getAttribute(name) {
        return this.attributes[name] ?? null;
    }

    removeAttribute(name) {
        delete this.attributes[name];
    }
}

function syncButton(button, selection) {
    const enabled = Boolean(
        selection?.id &&
        selection?.persisted &&
        selection?.canDelete
    );

    button.disabled = !enabled;
    const label = enabled
        ? `Xóa bản ghi ${selection.id}`
        : 'Bản ghi chưa được lưu nên không thể xóa';
    button.title = label;
    button.setAttribute('aria-label', label);
}

test('attendance and leave pages wire deletion through the shared guarded action', () => {
    for (const path of [
        '../../../resources/js/frontend/chamcong/chamcong.js',
        '../../../resources/js/frontend/nghiphep/nghiphep.js',
    ]) {
        const source = read(path);
        assert.match(source, /import \{ createDeleteAction \} from ['"]\.\.\/shared\/delete-action\.js['"]/);
        assert.match(source, /createDeleteAction\(\{/);
    }
});

test('failed attendance DELETE restores the button and permits a retry', async () => {
    const button = new FakeButton();
    const selection = { id: '7', persisted: true, canDelete: true };
    let calls = 0;

    const action = createDeleteAction({
        button,
        getSelection: () => selection,
        confirmAction: () => true,
        requestDelete: async (id) => {
            calls += 1;
            assert.equal(id, '7');
            if (calls === 1) throw new Error('DELETE failed');
        },
        onError: () => {},
        sync: () => syncButton(button, selection),
    });

    await action();
    assert.equal(calls, 1);
    assert.equal(button.disabled, false);
    assert.equal(button.getAttribute('aria-busy'), null);
    assert.equal(button.title, 'Xóa bản ghi 7');

    await action();
    assert.equal(calls, 2);
    assert.equal(button.disabled, false);
});

test('delete action rejects an unpersisted attendance record without transport', async () => {
    const button = new FakeButton();
    const selection = { id: null, persisted: false, canDelete: true };
    let calls = 0;

    const action = createDeleteAction({
        button,
        getSelection: () => selection,
        confirmAction: () => true,
        requestDelete: async () => { calls += 1; },
        sync: () => syncButton(button, selection),
    });

    await action();
    assert.equal(calls, 0);
    assert.equal(button.disabled, true);
});

test('pending leave DELETE ignores a double click and restores state after failure', async () => {
    const button = new FakeButton();
    const selection = { id: '12', persisted: true, canDelete: true };
    let calls = 0;
    let release;
    const pending = new Promise((resolve) => { release = resolve; });

    const action = createDeleteAction({
        button,
        getSelection: () => selection,
        confirmAction: () => true,
        requestDelete: async (id) => {
            calls += 1;
            assert.equal(id, '12');
            await pending;
            throw new Error('DELETE failed');
        },
        onError: () => {},
        sync: () => syncButton(button, selection),
    });

    const first = action();
    assert.equal(button.disabled, true);
    assert.equal(button.getAttribute('aria-busy'), 'true');

    await action();
    assert.equal(calls, 1);

    release();
    await first;
    assert.equal(button.disabled, false);
    assert.equal(button.getAttribute('aria-busy'), null);
    assert.equal(button.title, 'Xóa bản ghi 12');
});

test('successful leave DELETE awaits the list reload before syncing selection state', async () => {
    const button = new FakeButton();
    let selection = { id: '12', persisted: true, canDelete: true };
    let reloads = 0;

    const action = createDeleteAction({
        button,
        getSelection: () => selection,
        confirmAction: () => true,
        requestDelete: async () => {},
        onSuccess: async () => {
            reloads += 1;
            selection = { id: null, persisted: false, canDelete: true };
        },
        sync: () => syncButton(button, selection),
    });

    assert.equal(await action(), true);
    assert.equal(reloads, 1);
    assert.equal(button.disabled, true);
    assert.equal(button.getAttribute('aria-busy'), null);
    assert.equal(button.title, 'Bản ghi chưa được lưu nên không thể xóa');
});
