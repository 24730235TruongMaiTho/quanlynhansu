import assert from 'node:assert/strict';
import test from 'node:test';

import { applyFieldErrors } from '../../../resources/js/frontend/shared/form-feedback.js';

class FakeClassList {
    constructor() {
        this.values = new Set();
    }

    add(...values) {
        values.forEach((value) => this.values.add(value));
    }

    remove(...values) {
        values.forEach((value) => this.values.delete(value));
    }

    contains(value) {
        return this.values.has(value);
    }
}

class FakeField {
    constructor(id, name, attributes = {}) {
        this.id = id;
        this.name = name;
        this.attributes = { ...attributes, id, name };
        this.classList = new FakeClassList();
        this.parentElement = null;
    }

    getAttribute(name) {
        return this.attributes[name] ?? null;
    }

    setAttribute(name, value) {
        this.attributes[name] = String(value);
    }

    removeAttribute(name) {
        delete this.attributes[name];
    }
}

class FakeError {
    constructor() {
        this.id = '';
        this.textContent = '';
        this.attributes = {};
        this.removed = false;
    }

    setAttribute(name, value) {
        this.attributes[name] = String(value);
    }

    remove() {
        this.removed = true;
    }
}

class FakeForm {
    constructor(fields) {
        this.fields = fields;
        this.errors = [];
        this.elements = {
            namedItem: (name) => this.fields.find((field) => field.name === name) ?? null,
        };
        this.fields.forEach((field) => {
            field.parentElement = {
                append: (error) => this.errors.push(error),
            };
        });
    }

    querySelectorAll(selector) {
        if (selector === '[data-form-feedback]') return this.errors.filter((error) => !error.removed);
        return [];
    }
}

test('applies field errors with accessible IDs while preserving help text IDs', () => {
    const previousDocument = globalThis.document;
    globalThis.document = { createElement: () => new FakeError() };

    try {
        const field = new FakeField('email', 'email', { 'aria-describedby': 'email-help' });
        const form = new FakeForm([field]);

        applyFieldErrors(form, { email: ['Email không hợp lệ.'] }, { email: 'Email' });

        assert.equal(field.classList.contains('is-invalid'), true);
        assert.equal(field.getAttribute('aria-invalid'), 'true');
        assert.equal(field.getAttribute('aria-describedby'), 'email-help email-error');
        assert.equal(form.errors.length, 1);
        assert.equal(form.errors[0].id, 'email-error');
        assert.equal(form.errors[0].textContent, 'Email không hợp lệ.');
    } finally {
        globalThis.document = previousDocument;
    }
});

test('accepts a scalar validation message and does not create errors for unknown fields', () => {
    const previousDocument = globalThis.document;
    globalThis.document = { createElement: () => new FakeError() };

    try {
        const field = new FakeField('name', 'name');
        const form = new FakeForm([field]);

        applyFieldErrors(form, { name: 'Tên là bắt buộc.', unknown: ['Không hiển thị'] }, { name: 'Tên' });

        assert.equal(form.errors.length, 1);
        assert.equal(form.errors[0].textContent, 'Tên là bắt buộc.');
        assert.equal(field.getAttribute('aria-describedby'), 'name-error');
    } finally {
        globalThis.document = previousDocument;
    }
});
