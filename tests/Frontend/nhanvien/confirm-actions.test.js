import assert from 'node:assert/strict';
import test from 'node:test';

import { initializeActionDialogs } from '../../../resources/js/frontend/nhanvien/confirm-actions.js';

class FakeElement {
    constructor(attributes = {}) {
        this.attributes = attributes;
        this.listeners = {};
        this.disabled = false;
        this.hidden = false;
        this.open = false;
        this.focused = false;
        this.dataset = {};
        for (const [key, value] of Object.entries(attributes)) {
            if (key.startsWith('data-')) {
                this.dataset[key.slice(5).replace(/-([a-z])/g, (_, letter) => letter.toUpperCase())] = value;
            }
        }
    }

    getAttribute(name) {
        return this.attributes[name] ?? null;
    }

    setAttribute(name, value) {
        this.attributes[name] = String(value);
    }

    addEventListener(name, handler) {
        this.listeners[name] = handler;
    }

    dispatch(name, event = {}) {
        this.listeners[name]?.({ target: this, preventDefault() { event.prevented = true; }, ...event });
    }

    querySelectorAll(selector) {
        return this.children?.filter((child) => child.matches(selector)) ?? [];
    }

    querySelector(selector) {
        return this.querySelectorAll(selector)[0] ?? null;
    }

    matches(selector) {
        if (selector === '[data-dialog-cancel], [data-dialog-submit]') {
            return this.matches('[data-dialog-cancel]') || this.matches('[data-dialog-submit]');
        }
        if (selector === '[data-dialog-form]') return this.attributes['data-dialog-form'] !== undefined;
        if (selector === '[data-dialog-cancel]') return this.attributes['data-dialog-cancel'] !== undefined;
        if (selector === '[data-dialog-submit]') return this.attributes['data-dialog-submit'] !== undefined;
        return false;
    }

    focus() {
        this.focused = true;
    }
}

class FakeDialog extends FakeElement {
    constructor(attributes = {}) {
        super(attributes);
        this.children = [];
    }

    showModal() {
        this.open = true;
    }

    close() {
        this.open = false;
    }
}

class FakeRoot extends FakeElement {
    constructor(dialogs, openers) {
        super();
        this.dialogs = dialogs;
        this.openers = openers;
    }

    querySelectorAll(selector) {
        if (selector === '[data-action-dialog]') return this.dialogs;
        if (selector === '[data-dialog-open]') return this.openers;
        return [];
    }
}

test('opens dialog, focuses cancel, and restores opener focus on Escape', () => {
    const opener = new FakeElement({ 'data-dialog-open': 'reset-dialog' });
    const cancel = new FakeElement({ 'data-dialog-cancel': '' });
    const dialog = new FakeDialog({ 'data-action-dialog': '', id: 'reset-dialog' });
    dialog.children = [cancel];
    const root = new FakeRoot([dialog], [opener]);

    initializeActionDialogs(root, {});
    opener.dispatch('click');
    assert.equal(dialog.open, true);
    assert.equal(cancel.focused, true);
    dialog.dispatch('keydown', { key: 'Escape' });
    assert.equal(dialog.open, false);
    assert.equal(opener.focused, true);
});

test('cancel closes dialog and restores focus without submitting', () => {
    const opener = new FakeElement({ 'data-dialog-open': 'destroy-dialog' });
    const cancel = new FakeElement({ 'data-dialog-cancel': '' });
    const dialog = new FakeDialog({ 'data-action-dialog': '', id: 'destroy-dialog' });
    dialog.children = [cancel];
    const root = new FakeRoot([dialog], [opener]);

    initializeActionDialogs(root, {});
    opener.dispatch('click');
    const event = {};
    cancel.dispatch('click', event);
    assert.equal(event.prevented, true);
    assert.equal(dialog.open, false);
    assert.equal(opener.focused, true);
});

test('prevents a second submit while the first action is in flight', () => {
    const opener = new FakeElement({ 'data-dialog-open': 'destroy-dialog' });
    const submit = new FakeElement({ 'data-dialog-submit': '' });
    const dialog = new FakeDialog({ 'data-action-dialog': '', id: 'destroy-dialog' });
    dialog.children = [submit];
    const root = new FakeRoot([dialog], [opener]);

    initializeActionDialogs(root, {});
    const first = {};
    dialog.dispatch('submit', first);
    const second = {};
    dialog.dispatch('submit', second);
    assert.equal(first.prevented, undefined);
    assert.equal(second.prevented, true);
    assert.equal(submit.disabled, true);
});

test('uses window.confirm when dialog modal APIs are unavailable', () => {
    const opener = new FakeElement({ 'data-dialog-open': 'legacy-dialog' });
    const dialog = new FakeElement({ 'data-action-dialog': '', id: 'legacy-dialog' });
    const root = new FakeRoot([dialog], [opener]);
    let asked = 0;

    initializeActionDialogs(root, { confirm: () => { asked += 1; return false; } });
    const event = {};
    opener.dispatch('click', event);
    assert.equal(asked, 1);
    assert.equal(event.prevented, true);
});

test('submits exactly once when fallback confirmation is accepted', () => {
    const opener = new FakeElement({ 'data-dialog-open': 'legacy-dialog' });
    const form = new FakeElement({ 'data-dialog-form': '' });
    let submitted = 0;
    form.requestSubmit = () => { submitted += 1; };
    const dialog = new FakeElement({ 'data-action-dialog': '', id: 'legacy-dialog' });
    dialog.children = [form];
    const root = new FakeRoot([dialog], [opener]);

    initializeActionDialogs(root, { confirm: () => true });
    opener.dispatch('click', {});

    assert.equal(submitted, 1);
});
