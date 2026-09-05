import test from 'node:test';
import assert from 'node:assert/strict';

function fakeForm(message) {
    const listeners = {};

    return {
        dataset: { confirmDelete: message },
        addEventListener(type, listener) {
            listeners[type] = listener;
        },
        submit() {
            const event = {
                defaultPrevented: false,
                preventDefault() {
                    this.defaultPrevented = true;
                },
            };
            listeners.submit?.(event);
            return event;
        },
    };
}

function fakeContractUi() {
    const listeners = {};
    const typeSelect = {
        selectedOptions: [{ dataset: { contractTerm: 'indefinite' } }],
        addEventListener(type, listener) {
            listeners[`type:${type}`] = listener;
        },
        change() {
            listeners['type:change']?.({});
        },
    };
    const expiryInput = {
        value: '31/12/2099',
        disabled: false,
        required: true,
    };
    const salaryInput = {
        value: '13000000',
        selectionStart: 8,
        addEventListener(type, listener) {
            listeners[`salary:${type}`] = listener;
        },
        input() {
            listeners['salary:input']?.({});
        },
        setSelectionRange(start) {
            this.selectionStart = start;
        },
        setCustomValidity(message) {
            this.validationMessage = message;
        },
    };
    const form = {
        dataset: {},
        addEventListener(type, listener) {
            listeners[`form:${type}`] = listeners[`form:${type}`] || [];
            listeners[`form:${type}`].push(listener);
        },
        submit() {
            const event = {
                defaultPrevented: false,
                preventDefault() {
                    this.defaultPrevented = true;
                },
            };
            listeners['form:submit']?.forEach((listener) => listener(event));
            return event;
        },
        submitListenerCount() {
            return listeners['form:submit']?.length || 0;
        },
    };
    const help = { textContent: '' };
    const marker = { hidden: false };

    return {
        typeSelect,
        expiryInput,
        salaryInput,
        form,
        root: {
            getElementById(id) {
                return {
                    ma_lhd: typeSelect,
                    ngay_het_han: expiryInput,
                    luong_co_ban: salaryInput,
                    'ngay_het_han-help': help,
                }[id] || null;
            },
            querySelector(selector) {
                if (selector === '[data-contract-form]') return form;
                if (selector === '[data-expiry-required-marker]') return marker;
                return null;
            },
        },
    };
}

test('contract delete form confirms before submitting', async () => {
    const { bindConfirmDeleteForms } = await import(
        '../../..//resources/js/frontend/hopdong/hopdong.js'
    );
    const form = fakeForm('Xác nhận xóa hợp đồng?');
    const root = { querySelectorAll: () => [form] };
    const prompts = [];

    bindConfirmDeleteForms(root, {
        confirm(message) {
            prompts.push(message);
            return false;
        },
    });

    assert.equal(form.submit().defaultPrevented, true);
    assert.deepEqual(prompts, ['Xác nhận xóa hợp đồng?']);
});

test('contract delete form submits after confirmation', async () => {
    const { bindConfirmDeleteForms } = await import(
        '../../..//resources/js/frontend/hopdong/hopdong.js'
    );
    const form = fakeForm('Xác nhận xóa hợp đồng?');
    const root = { querySelectorAll: () => [form] };

    bindConfirmDeleteForms(root, { confirm: () => true });

    assert.equal(form.submit().defaultPrevented, false);
});

test('contract form derives expiry state from the verified type marker', async () => {
    const { bindContractForm } = await import(
        '../../..//resources/js/frontend/hopdong/hopdong.js'
    );
    const ui = fakeContractUi();

    bindContractForm(ui.root);
    assert.equal(ui.expiryInput.disabled, true);
    assert.equal(ui.expiryInput.required, false);
    assert.equal(ui.expiryInput.value, '');

    ui.typeSelect.selectedOptions = [{ dataset: { contractTerm: 'finite' } }];
    ui.typeSelect.change();
    assert.equal(ui.expiryInput.disabled, false);
    assert.equal(ui.expiryInput.required, true);
});

test('contract salary input formats safely, submits canonical digits, and does not double-bind', async () => {
    const { bindContractForm, formatVietnameseInteger, parseVietnameseInteger } = await import(
        '../../..//resources/js/frontend/hopdong/hopdong.js'
    );
    const ui = fakeContractUi();

    assert.equal(parseVietnameseInteger('13.000.000'), '13000000');
    assert.equal(formatVietnameseInteger('13000000'), '13.000.000');
    assert.equal(parseVietnameseInteger('13.00.000'), null);

    bindContractForm(ui.root);
    bindContractForm(ui.root);
    ui.salaryInput.input();
    assert.equal(ui.salaryInput.value, '13.000.000');
    assert.equal(ui.form.submitListenerCount(), 1);

    const event = ui.form.submit();
    assert.equal(event.defaultPrevented, false);
    assert.equal(ui.salaryInput.value, '13000000');
});
