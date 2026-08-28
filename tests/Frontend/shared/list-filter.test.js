import assert from 'node:assert/strict';
import test from 'node:test';

import { initializeListFilter } from '../../../resources/js/frontend/shared/list-filter.js';

test('list filter exposes busy state and prevents duplicate submissions', () => {
    const listeners = {};
    const form = {
        attributes: { 'aria-busy': 'false' },
        addEventListener(name, handler) {
            listeners[name] = handler;
        },
        setAttribute(name, value) {
            this.attributes[name] = String(value);
        },
        querySelector() {
            return button;
        },
    };
    const button = {
        dataset: { submittingText: 'Đang lọc...' },
        disabled: false,
        attributes: {},
        setAttribute(name, value) {
            this.attributes[name] = String(value);
        },
        textContent: 'Lọc',
    };

    initializeListFilter(form);
    initializeListFilter(form);

    const first = {};
    listeners.submit(first);
    assert.equal(form.attributes['aria-busy'], 'true');
    assert.equal(button.disabled, true);
    assert.equal(button.attributes['aria-disabled'], 'true');
    assert.equal(button.textContent, 'Đang lọc...');
    assert.equal(first.prevented, undefined);

    const second = {};
    second.preventDefault = () => { second.prevented = true; };
    listeners.submit(second);
    assert.equal(second.prevented, true);
});
