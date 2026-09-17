import test from 'node:test';
import assert from 'node:assert/strict';
import fs from 'node:fs';

const contractView = fs.readFileSync(
    new URL('../../../resources/views/backend/hopdong/index.blade.php', import.meta.url),
    'utf8',
);
const fullContractFormView = fs.readFileSync(
    new URL('../../../resources/views/backend/hopdong/form.blade.php', import.meta.url),
    'utf8',
);
const simpleModalView = fs.readFileSync(
    new URL('../../../resources/views/backend/partials/simple-edit-modal.blade.php', import.meta.url),
    'utf8',
);
const hopDongSource = fs.readFileSync(
    new URL('../../../resources/js/frontend/hopdong/hopdong.js', import.meta.url),
    'utf8',
);

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
        querySelector(selector) {
            return {
                '[name="ma_lhd"]': typeSelect,
                '[name="ngay_het_han"]': expiryInput,
                '[name="luong_co_ban"]': salaryInput,
            }[selector] || null;
        },
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

function fakeExpiringFilterUi() {
    const listeners = {};
    const checkbox = {
        dataset: {},
        addEventListener(type, listener) {
            listeners[type] = listener;
        },
        change() {
            listeners.change?.({ target: checkbox });
        },
    };
    const form = {
        requestSubmitCount: 0,
        requestSubmit() {
            this.requestSubmitCount += 1;
        },
    };

    return {
        checkbox,
        form,
        root: {
            querySelector(selector) {
                if (selector === '#sap_het_han') return checkbox;
                if (selector === '#contract-filter-form') return form;
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

test('contract salary input keeps sequential typing formatted instead of clearing the field', async () => {
    const { bindContractForm } = await import(
        '../../..//resources/js/frontend/hopdong/hopdong.js'
    );
    const ui = fakeContractUi();

    bindContractForm(ui.root);
    ui.salaryInput.value = '12a.345';
    ui.salaryInput.selectionStart = ui.salaryInput.value.length;
    ui.salaryInput.input();
    assert.equal(ui.salaryInput.value, '12.345');

    ui.salaryInput.value = '';
    ui.salaryInput.selectionStart = 0;

    for (const digit of '1234') {
        ui.salaryInput.value += digit;
        ui.salaryInput.selectionStart = ui.salaryInput.value.length;
        ui.salaryInput.input();
    }
    assert.equal(ui.salaryInput.value, '1.234');

    ui.salaryInput.value += '5';
    ui.salaryInput.selectionStart = ui.salaryInput.value.length;
    ui.salaryInput.input();
    assert.equal(ui.salaryInput.value, '12.345');

    for (const digit of '6789012345678') {
        ui.salaryInput.value += digit;
        ui.salaryInput.selectionStart = ui.salaryInput.value.length;
        ui.salaryInput.input();
    }
    assert.equal(ui.salaryInput.value, '123.456.789.012.345.678');

    const event = ui.form.submit();
    assert.equal(event.defaultPrevented, false);
    assert.equal(ui.salaryInput.value, '123456789012345678');
});

test('contract list uses employee code as the visible identifier while actions retain contract id', () => {
    assert.doesNotMatch(contractView, /<th\s+scope="col">#<\/th>/u);
    assert.match(contractView, /<th\s+scope="col"\s+aria-sort="\{\{\s*\$sort\s*===\s*'ma_nv'/u);
    assert.match(contractView, /<th\s+scope="row">\s*<span class="identifier-text">\s*\{\{\s*\$contract->ma_nv\s*\}\}/u);
    assert.doesNotMatch(contractView, /<th\s+scope="row">\s*<span class="identifier-text">\s*\{\{\s*\$contract->ma_hd\s*\}\}/u);
    assert.doesNotMatch(contractView, /<small[^>]*>\s*\{\{\s*\$contract->ma_nv\s*\}\}/u);
    assert.match(contractView, /route\('backend\.hopdong\.edit',\s*\$contract->ma_hd\)/u);
    assert.match(contractView, /route\('backend\.hopdong\.destroy',\s*\$contract->ma_hd\)/u);
});

test('expiring-only checkbox submits the contract filter immediately and keeps an explicit form action', async () => {
    const { bindExpiringFilter } = await import(
        '../../..//resources/js/frontend/hopdong/hopdong.js'
    );
    const ui = fakeExpiringFilterUi();

    assert.match(hopDongSource, /bindExpiringFilter/u);
    assert.match(contractView, /id="contract-filter-form"/u);
    bindExpiringFilter(ui.root);
    ui.checkbox.change();

    assert.equal(ui.form.requestSubmitCount, 1);
});

test('contract empty state explains when only the expiring window has no results', () => {
    assert.match(contractView, /expiringFilterOnly/u);
    assert.match(contractView, /Không có hợp đồng nào hết hạn trong \{\{\s*\$expiringWarningDays\s*\}\} ngày tới/u);
});

test('contract edit trigger opens the modal while retaining its real fallback URL', () => {
    assert.match(contractView, /route\('backend\.hopdong\.edit',\s*\$contract->ma_hd\)/u);
    assert.match(contractView, /data-action="modal" data-modal-mode="edit"/u);
    assert.match(contractView, /data-modal-url="\{\{\s*route\('backend\.hopdong\.edit'/u);
    assert.match(simpleModalView, /data-edit-title=/u);
    assert.match(fs.readFileSync(new URL('../../../resources/views/backend/hopdong/partials/edit-modal-content.blade.php', import.meta.url), 'utf8'), /@method\('PUT'\)/u);
});

test('full-page contract form loads the same entrypoint as the popup form', () => {
    assert.match(fullContractFormView, /@push\('scripts'\)[\s\S]*@vite\('resources\/js\/frontend\/hopdong\/hopdong\.js'\)/u);
});

test('contract create trigger is a modal with a real no-script fallback', () => {
    assert.match(contractView, /href="\{\{\s*route\('backend\.hopdong\.create'\)\s*\}\}"/u);
    assert.match(contractView, /data-action="modal"/u);
    assert.match(contractView, /data-modal-mode="create"/u);
    assert.match(contractView, /data-modal-url="\{\{\s*route\('backend\.hopdong\.create'\)\s*\}\}"/u);
    assert.match(simpleModalView, /data-simple-edit-modal/u);
    assert.match(fs.readFileSync(new URL('../../../resources/views/backend/hopdong/partials/create-modal-content.blade.php', import.meta.url), 'utf8'), /data-contract-modal-close/u);
});

test('dynamic contract form binding scopes fields to the injected form', async () => {
    const { bindContractForm } = await import(
        '../../..//resources/js/frontend/hopdong/hopdong.js'
    );
    const ui = fakeContractUi();
    const filterType = { selectedOptions: [{ dataset: { contractTerm: 'finite' } }] };
    const originalQuery = ui.root.querySelector;
    ui.root.getElementById = () => filterType;
    ui.root.querySelector = (selector) => {
        if (selector === '[data-contract-form]') return ui.form;
        return originalQuery(selector);
    };
    ui.form.querySelector = (selector) => ({
        '[name="ma_lhd"]': ui.typeSelect,
        '[name="ngay_het_han"]': ui.expiryInput,
        '[name="luong_co_ban"]': ui.salaryInput,
    }[selector] || null);

    bindContractForm(ui.root);
    assert.equal(ui.expiryInput.disabled, true);
    assert.equal(ui.typeSelect.selectedOptions[0].dataset.contractTerm, 'indefinite');
});
