import assert from 'node:assert/strict';
import test from 'node:test';

import { createEmployeeEditModalController } from '../../../resources/js/frontend/nhanvien/edit-modal.js';
import { initializeEmployeeWizards } from '../../../resources/js/frontend/nhanvien/wizard.js';

class FakeElement {
    constructor(attributes = {}) {
        this.attributes = { ...attributes };
        this.listeners = {};
        this.hidden = false;
        this.disabled = false;
        this.open = false;
        this.focused = false;
        this.textContent = '';
        this.innerHTML = '';
        this.dataset = {};
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

    closest() {
        return null;
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

class FakeField extends FakeElement {
    constructor(name) {
        super({ name });
        this.name = name;
        this.id = name;
    }
}

class FakeForm extends FakeElement {
    constructor() {
        super({ action: '/nhan-vien/00001', method: 'POST' });
        this.action = '/nhan-vien/00001';
        this.method = 'POST';
        this.button = new FakeElement();
        this.button.textContent = 'Cập nhật hồ sơ';
        this.fields = { email: new FakeField('email') };
        this.error = new FakeElement();
        this.formError = new FakeElement();
        this.formError.hidden = true;
        this.page = new FakeElement();
        this.page.querySelectorAll = () => [];
        this.modal = new FakeElement();
        this.steps = [1, 2, 3].map((stepNumber) => {
            const step = new FakeElement();
            step.dataset.wizardStep = String(stepNumber);
            step.heading = new FakeElement();
            step.querySelector = (selector) => selector === '[data-step-heading]' ? step.heading : null;
            return step;
        });
    }

    querySelector(selector) {
        if (selector === '[data-submit-employee]') return this.button;
        if (selector === '[data-step-heading], input, select, textarea, button') return this.button;
        if (selector === '[data-modal-field-error="email"]') return this.error;
        if (selector === '[data-modal-form-error]') return this.formError;
        return null;
    }

    querySelectorAll(selector) {
        if (selector === '[data-submit-employee]') return [this.button];
        if (selector === '[data-wizard-step]') return this.steps;
        return [];
    }

    closest(selector) {
        if (selector === '.employee-page') return this.page;
        if (selector === '[data-employee-edit-modal]') return this.modal;
        return null;
    }

    get elements() {
        return {
            namedItem: (name) => this.fields[name] ?? null,
        };
    }
}

class FakeContent extends FakeElement {
    constructor(form) {
        super();
        this.form = form;
        this.heading = new FakeElement();
        this.onInject = null;
    }

    set innerHTML(value) {
        this.html = value;
        this.onInject?.(value);
    }

    get innerHTML() {
        return this.html ?? '';
    }

    querySelector(selector) {
        if (selector === '[data-employee-wizard]') return this.form;
        if (selector === '[data-step-heading], input, select, textarea, button') return this.heading;
        return null;
    }

    querySelectorAll(selector) {
        if (selector === '[data-employee-wizard]') return [this.form];
        return [];
    }
}

function createHarness(fetchImpl, initializeWizards = null) {
    const form = new FakeForm();
    const content = new FakeContent(form);
    const ui = {
        dialog: new FakeDialog(),
        content,
        loading: new FakeElement(),
        error: new FakeElement(),
        fallback: new FakeElement(),
        retry: new FakeElement(),
        close: new FakeElement(),
    };
    const navigation = { reloads: 0, reload() { this.reloads += 1; } };
    const initialized = [];
    const controller = createEmployeeEditModalController(ui, {
        fetch: fetchImpl,
        navigation,
        initializeWizards: initializeWizards ?? ((root) => initialized.push(root)),
        createFormData: () => ({ employee: '00001' }),
    });

    return { controller, ui, form, navigation, initialized };
}

function response({ status = 200, html = '', json = {} } = {}) {
    return {
        ok: status >= 200 && status < 300,
        status,
        text: async () => html,
        json: async () => json,
    };
}

test('loads edit form on demand, injects it once, and initializes the wizard', async () => {
    const calls = [];
    const { controller, ui, navigation, initialized } = createHarness(async (url, options) => {
        calls.push({ url, options });
        return response({ html: '<form data-employee-wizard></form>' });
    });
    const opener = new FakeElement();

    await controller.open(opener, '/nhan-vien/00001/edit');

    assert.equal(ui.dialog.open, true);
    assert.equal(ui.fallback.href, '/nhan-vien/00001/edit');
    assert.equal(ui.content.innerHTML, '<form data-employee-wizard></form>');
    assert.equal(initialized.length, 1);
    assert.equal(calls[0].url, '/nhan-vien/00001/edit');
    assert.equal(calls[0].options.headers['X-Employee-Edit-Modal'], '1');
    assert.equal(navigation.reloads, 0);
});

test('keeps a safe retry and progressive fallback when the form cannot load', async () => {
    const { controller, ui } = createHarness(async () => response({ status: 503 }));
    const opener = new FakeElement();

    await controller.open(opener, '/nhan-vien/00002/edit');

    assert.equal(ui.error.hidden, false);
    assert.match(ui.error.textContent, /Không tải được biểu mẫu/);
    assert.equal(ui.fallback.hidden, false);
    assert.equal(ui.fallback.href, '/nhan-vien/00002/edit');
    assert.equal(ui.retry.hidden, false);
    assert.doesNotMatch(ui.error.textContent, /SQLSTATE|Exception|stack/i);
});

test('renders field validation errors and blocks a second submit while saving', async () => {
    let resolveRequest;
    let requestCount = 0;
    const { controller, form, ui } = createHarness(() => {
        requestCount += 1;
        if (requestCount === 1) {
            return response({ html: '<form data-employee-wizard></form>' });
        }

        return new Promise((resolve) => {
            resolveRequest = resolve;
        });
    });
    const opener = new FakeElement();
    await controller.open(opener, '/nhan-vien/00001/edit');

    const first = {};
    const firstSubmit = controller.submit(form, first);
    assert.equal(form.button.disabled, true);
    const second = {
        preventDefault() {
            this.prevented = true;
        },
    };
    controller.submit(form, second);
    assert.equal(second.prevented, true);

    resolveRequest(response({
        status: 422,
        json: { message: 'Dữ liệu chưa hợp lệ.', errors: { email: ['Email không hợp lệ.'] } },
    }));
    await firstSubmit;

    assert.equal(form.fields.email.classList.contains('is-invalid'), true);
    assert.equal(form.error.textContent, 'Email không hợp lệ.');
    assert.equal(form.button.disabled, false);
    assert.equal(ui.dialog.open, true);
});

test('lets the modal own wizard submission state so a 422 restores the label and allows retry', async () => {
    let requestCount = 0;
    const pending = [];
    const { controller, form, ui, navigation } = createHarness(() => {
        requestCount += 1;
        if (requestCount === 1) {
            return response({ html: '<form data-employee-wizard></form>' });
        }

        return new Promise((resolve) => pending.push(resolve));
    }, (root) => initializeEmployeeWizards(root));

    await controller.open(new FakeElement(), '/nhan-vien/00001/edit');
    const firstEvent = {};
    form.dispatch('submit', firstEvent);
    const firstSubmit = controller.submit(form, firstEvent);
    assert.equal(form.button.disabled, true);
    pending[0](response({
        status: 422,
        json: { message: 'Email đã được sử dụng.', errors: { email: ['Email đã được sử dụng.'] } },
    }));
    await firstSubmit;

    assert.equal(form.button.textContent, 'Cập nhật hồ sơ');
    assert.equal(form.button.disabled, false);

    const secondEvent = {};
    form.dispatch('submit', secondEvent);
    const secondSubmit = controller.submit(form, secondEvent);
    assert.equal(requestCount, 3);
    pending[1](response({
        json: { success: true, message: 'Đã cập nhật hồ sơ nhân viên.' },
    }));
    await secondSubmit;
    assert.equal(navigation.reloads, 1);
    assert.equal(ui.dialog.open, false);
});

test('blocks close and Escape during update, then permits reopening after a safe failure', async () => {
    let requestCount = 0;
    let resolveUpdate;
    const { controller, ui, form } = createHarness(() => {
        requestCount += 1;
        if (requestCount === 1) {
            return response({ html: '<form data-employee-wizard></form>' });
        }

        if (requestCount === 2) {
            return new Promise((resolve) => {
                resolveUpdate = resolve;
            });
        }

        return response({ html: '<form data-employee-wizard></form>' });
    });
    const firstOpener = new FakeElement();
    await controller.open(firstOpener, '/nhan-vien/00001/edit');

    const update = controller.submit(form, {});
    assert.equal(ui.close.disabled, true);
    assert.equal(controller.close(), false);

    const escape = {};
    controller.handleKeydown({ key: 'Escape', preventDefault() { escape.prevented = true; } });
    assert.equal(escape.prevented, true);
    assert.equal(ui.dialog.open, true);

    const cancel = {};
    ui.dialog.dispatch('cancel', cancel);
    assert.equal(cancel.prevented, true);
    assert.equal(ui.dialog.open, true);
    assert.equal(await controller.open(new FakeElement(), '/nhan-vien/00002/edit'), null);
    assert.equal(ui.fallback.href, '/nhan-vien/00001/edit');

    resolveUpdate(response({ status: 500, json: { message: 'SQLSTATE private details' } }));
    await update;
    assert.equal(ui.close.disabled, false);
    assert.equal(form.button.disabled, false);

    const secondOpener = new FakeElement();
    await controller.open(secondOpener, '/nhan-vien/00002/edit');
    assert.equal(ui.dialog.open, true);
    assert.equal(ui.fallback.href, '/nhan-vien/00002/edit');
});

test('shows a safe form-level message for an unmapped 422 error and keeps the form usable', async () => {
    let requestCount = 0;
    const { controller, ui, form } = createHarness(async () => {
        requestCount += 1;
        return requestCount === 1
            ? response({ html: '<form data-employee-wizard></form>' })
            : response({
                status: 422,
                json: {
                    message: 'Email đã được sử dụng.',
                    errors: { nhan_vien: ['Email đã được sử dụng.'] },
                },
            });
    });
    await controller.open(new FakeElement(), '/nhan-vien/00001/edit');

    await controller.submit(form, {});

    assert.equal(form.formError.hidden, false);
    assert.equal(form.formError.textContent, 'Email đã được sử dụng.');
    assert.equal(form.button.disabled, false);
    assert.equal(ui.dialog.open, true);
    assert.doesNotMatch(form.formError.textContent, /SQLSTATE|Exception|stack/i);
});

test('closes and reloads the current list only after a successful update', async () => {
    const { controller, ui, form, navigation } = createHarness(async () => response({
        json: { success: true, message: 'Đã cập nhật hồ sơ nhân viên.' },
    }));
    const opener = new FakeElement();
    await controller.open(opener, '/nhan-vien/00001/edit');

    await controller.submit(form, {});

    assert.equal(ui.dialog.open, false);
    assert.equal(navigation.reloads, 1);
    assert.equal(opener.focused, true);
});

test('reuses the dialog for another employee and reinitializes the injected form', async () => {
    const calls = [];
    const { controller, ui, initialized } = createHarness(async (url) => {
        calls.push(url);
        return response({ html: '<form data-employee-wizard></form>' });
    });
    const firstOpener = new FakeElement();
    const secondOpener = new FakeElement();

    await controller.open(firstOpener, '/nhan-vien/00001/edit');
    controller.close();
    await controller.open(secondOpener, '/nhan-vien/00002/edit');

    assert.deepEqual(calls, ['/nhan-vien/00001/edit', '/nhan-vien/00002/edit']);
    assert.equal(initialized.length, 2);
    assert.equal(ui.fallback.href, '/nhan-vien/00002/edit');
});

test('uses a safe server message and re-enables the form after an update failure', async () => {
    let requestCount = 0;
    const { controller, form, ui } = createHarness(async () => {
        requestCount += 1;
        return requestCount === 1
            ? response({ html: '<form data-employee-wizard></form>' })
            : response({ status: 500, json: { message: 'SQLSTATE private details' } });
    });
    await controller.open(new FakeElement(), '/nhan-vien/00001/edit');

    await controller.submit(form, {});

    assert.equal(ui.error.hidden, false);
    assert.equal(ui.error.textContent, 'Không thể cập nhật nhân viên lúc này. Vui lòng thử lại sau.');
    assert.doesNotMatch(ui.error.textContent, /SQLSTATE/);
    assert.equal(form.button.disabled, false);
});

test('Escape closes the modal and restores focus to the triggering row', async () => {
    const { controller, ui } = createHarness(async () => response({ html: '<form data-employee-wizard></form>' }));
    const opener = new FakeElement();
    await controller.open(opener, '/nhan-vien/00001/edit');

    const escape = {};
    controller.handleKeydown({ key: 'Escape', preventDefault() { escape.prevented = true; } });

    assert.equal(escape.prevented, true);
    assert.equal(ui.dialog.open, false);
    assert.equal(opener.focused, true);
});
