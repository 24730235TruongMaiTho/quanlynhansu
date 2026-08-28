import assert from 'node:assert/strict';
import test from 'node:test';

import { initializeEmployeeFilter } from '../../../resources/js/frontend/nhanvien/filter-submit.js';
import { initializeEmployeePage } from '../../../resources/js/frontend/nhanvien/employee-page.js';

class FakeElement {
    constructor(attributes = {}) {
        this.attributes = { ...attributes };
        this.dataset = {};
        this.listeners = {};
        this.disabled = false;
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
        this.listeners[name] ??= [];
        this.listeners[name].push(handler);
    }

    dispatch(name, event = {}) {
        for (const handler of this.listeners[name] ?? []) {
            handler({ target: this, preventDefault() { event.prevented = true; }, ...event });
        }
    }

    querySelectorAll() {
        return [];
    }
}

class FakeForm extends FakeElement {
    constructor(button) {
        super({ 'aria-busy': 'false' });
        this.button = button;
    }

    querySelector(selector) {
        return selector === '[data-disable-on-submit]' ? this.button : null;
    }
}

class FakeRoot extends FakeElement {
    constructor(form) {
        super();
        this.form = form;
        this.queryCounts = {};
    }

    querySelectorAll(selector) {
        this.queryCounts[selector] = (this.queryCounts[selector] ?? 0) + 1;
        return selector === '[data-employee-filter]' ? [this.form] : [];
    }
}

test('employee filter exposes a busy disabled state exactly once while GET is submitted', () => {
    const button = new FakeElement({
        'data-disable-on-submit': '',
        'data-submitting-text': 'Đang lọc...',
    });
    button.textContent = 'Áp dụng bộ lọc';
    const form = new FakeForm(button);

    initializeEmployeeFilter(form);
    const first = {};
    form.dispatch('submit', first);

    assert.equal(first.prevented, undefined);
    assert.equal(form.getAttribute('aria-busy'), 'true');
    assert.equal(button.disabled, true);
    assert.equal(button.getAttribute('aria-disabled'), 'true');
    assert.equal(button.textContent, 'Đang lọc...');

    const second = {};
    form.dispatch('submit', second);
    assert.equal(second.prevented, true);
});

test('employee page entrypoint wires filter discovery and remains idempotent', () => {
    const button = new FakeElement({
        'data-disable-on-submit': '',
        'data-submitting-text': 'Đang lọc...',
    });
    button.textContent = 'Áp dụng bộ lọc';
    const form = new FakeForm(button);
    const root = new FakeRoot(form);

    initializeEmployeePage(root, {});
    initializeEmployeePage(root, {});

    assert.deepEqual(root.queryCounts, {
        '[data-employee-wizard]': 1,
        '[data-employee-filter]': 1,
        '[data-action-dialog]': 1,
        '[data-dialog-open]': 1,
        '[data-row-action-select]': 1,
    });

    const first = {};
    form.dispatch('submit', first);
    assert.equal(first.prevented, undefined);
    assert.equal(form.getAttribute('aria-busy'), 'true');
    assert.equal(button.disabled, true);
    assert.equal(button.getAttribute('aria-disabled'), 'true');
    assert.equal(button.textContent, 'Đang lọc...');

    const second = {};
    form.dispatch('submit', second);
    assert.equal(second.prevented, true);
});

test('direct filter initialization is idempotent for the same form', () => {
    const button = new FakeElement({
        'data-disable-on-submit': '',
        'data-submitting-text': 'Đang lọc...',
    });
    button.textContent = 'Áp dụng bộ lọc';
    const form = new FakeForm(button);

    initializeEmployeeFilter(form);
    initializeEmployeeFilter(form);

    const first = {};
    form.dispatch('submit', first);
    assert.equal(first.prevented, undefined);
});
