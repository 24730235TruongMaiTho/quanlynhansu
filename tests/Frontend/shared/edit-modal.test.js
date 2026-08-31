import assert from 'node:assert/strict';
import test from 'node:test';

import { createSimpleEditModalController } from '../../../resources/js/frontend/shared/edit-modal.js';

class FakeElement {
    constructor(attributes = {}) {
        this.attributes = { ...attributes };
        this.dataset = {};
        this.listeners = {};
        this.hidden = false;
        this.disabled = false;
        this.open = false;
        this.focused = false;
        this.textContent = '';
        this.innerHTML = '';
        this.classList = {
            values: new Set(),
            add: (...values) => values.forEach((value) => this.classList.values.add(value)),
            remove: (...values) => values.forEach((value) => this.classList.values.delete(value)),
            contains: (value) => this.classList.values.has(value),
        };
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

    addEventListener(name, handler) {
        this.listeners[name] ??= [];
        this.listeners[name].push(handler);
    }

    dispatch(name, event = {}) {
        for (const handler of this.listeners[name] ?? []) {
            handler({ target: this, preventDefault() { event.prevented = true; }, ...event });
        }
    }

    querySelector() {
        return null;
    }

    querySelectorAll() {
        return [];
    }

    focus() {
        this.focused = true;
    }
}

class FakeDialog extends FakeElement {
    showModal() {
        this.open = true;
    }

    close() {
        this.open = false;
    }
}

class FakeForm extends FakeElement {
    constructor() {
        super({ action: '/phong-ban/1', method: 'POST' });
        this.action = '/phong-ban/1';
        this.method = 'POST';
        this.button = new FakeElement();
        this.button.textContent = 'Lưu thay đổi';
        this.fields = { ten_pb: new FakeElement({ name: 'ten_pb' }) };
        this.formError = new FakeElement();
        this.formError.hidden = true;
    }

    querySelector(selector) {
        if (selector === '[data-submit-edit]') return this.button;
        if (selector === '[data-modal-form-error]') return this.formError;
        if (selector === '[data-edit-modal-field-error="ten_pb"]') return this.formError;
        return null;
    }

    querySelectorAll(selector) {
        if (selector === '[data-submit-edit]') return [this.button];
        return [];
    }

    get elements() {
        return { namedItem: (name) => this.fields[name] ?? null };
    }
}

class FakeContent extends FakeElement {
    constructor(form) {
        super();
        this.form = form;
    }

    set innerHTML(value) {
        this.html = value;
    }

    get innerHTML() {
        return this.html ?? '';
    }

    querySelector(selector) {
        if (selector === '[data-simple-edit-form]') return this.form;
        if (selector === 'input, select, textarea, button') return this.form.button;
        return null;
    }

    querySelectorAll(selector) {
        if (selector === '[data-simple-edit-form]') return [this.form];
        return [];
    }
}

function response({ status = 200, html = '', json = {} } = {}) {
    return {
        ok: status >= 200 && status < 300,
        status,
        text: async () => html,
        json: async () => json,
    };
}

function createHarness(fetchImpl) {
    const form = new FakeForm();
    const ui = {
        dialog: new FakeDialog(),
        content: new FakeContent(form),
        loading: new FakeElement(),
        error: new FakeElement(),
        recovery: new FakeElement(),
        fallback: new FakeElement(),
        retry: new FakeElement(),
        close: new FakeElement(),
    };
    const navigation = { reloads: 0, reload() { this.reloads += 1; } };
    const controller = createSimpleEditModalController(ui, {
        fetch: fetchImpl,
        navigation,
        createFormData: () => ({ department: 1 }),
    });

    return { controller, form, ui, navigation };
}

test('loads a simple edit form on demand and restores the trigger after success', async () => {
    let requestCount = 0;
    const { controller, form, ui, navigation } = createHarness(async () => {
        requestCount += 1;
        return requestCount === 1
            ? response({ html: '<form data-simple-edit-form></form>' })
            : response({ json: { success: true, message: 'Đã cập nhật phòng ban.' } });
    });
    const opener = new FakeElement();

    await controller.open(opener, '/phong-ban/1/edit');
    await controller.submit(form, {});

    assert.equal(requestCount, 2);
    assert.equal(ui.dialog.open, false);
    assert.equal(navigation.reloads, 1);
    assert.equal(opener.focused, true);
});

test('renders form-level 422 errors and permits retry after validation failure', async () => {
    let requestCount = 0;
    const { controller, form, ui } = createHarness(async () => {
        requestCount += 1;
        return requestCount === 1
            ? response({ html: '<form data-simple-edit-form></form>' })
            : response({
                status: 422,
                json: { message: 'Tên phòng ban đã tồn tại.', errors: { phong_ban: ['Tên phòng ban đã tồn tại.'] } },
            });
    });

    await controller.open(new FakeElement(), '/phong-ban/1/edit');
    await controller.submit(form, {});

    assert.equal(form.formError.hidden, false);
    assert.equal(form.formError.textContent, 'Tên phòng ban đã tồn tại.');
    assert.equal(form.button.disabled, false);
    assert.equal(ui.dialog.open, true);
});

test('blocks close, cancel, Escape and opening another row while update is pending', async () => {
    let resolveUpdate;
    let requestCount = 0;
    const { controller, form, ui } = createHarness(() => {
        requestCount += 1;
        if (requestCount === 1) return Promise.resolve(response({ html: '<form data-simple-edit-form></form>' }));
        return new Promise((resolve) => { resolveUpdate = resolve; });
    });

    await controller.open(new FakeElement(), '/phong-ban/1/edit');
    const submit = controller.submit(form, {});
    assert.equal(ui.close.disabled, true);
    assert.equal(controller.close(), false);
    controller.handleKeydown({ key: 'Escape', preventDefault() {} });
    assert.equal(ui.dialog.open, true);
    ui.dialog.dispatch('cancel', {});
    assert.equal(ui.dialog.open, true);
    assert.equal(await controller.open(new FakeElement(), '/phong-ban/2/edit'), null);

    resolveUpdate(response({ status: 500, json: { message: 'SQLSTATE private details' } }));
    await submit;
    assert.equal(ui.close.disabled, false);
    assert.equal(form.button.disabled, false);
});
