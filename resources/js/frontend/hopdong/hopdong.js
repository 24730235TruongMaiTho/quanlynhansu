import { initializeSimpleEditModal } from '../shared/edit-modal.js';

function parseVietnameseInteger(value) {
    const raw = String(value ?? '').trim();
    if (raw.includes('.') && !/^\d{1,3}(?:\.\d{3})+$/.test(raw)) return null;

    const normalized = raw.replace(/\./g, '');
    return /^\d+$/.test(normalized) ? normalized : null;
}

function formatVietnameseInteger(value) {
    const normalized = parseVietnameseInteger(value);
    if (normalized === null) return '';

    return normalized.replace(/\B(?=(\d{3})+(?!\d))/g, '.');
}

function formatVietnameseIntegerInput(value) {
    const digits = String(value ?? '').replace(/\D/g, '');
    return digits.replace(/\B(?=(\d{3})+(?!\d))/g, '.');
}

function bindConfirmDeleteForms(root = typeof document !== 'undefined' ? document : null, browser = typeof window !== 'undefined' ? window : {}) {
    root?.querySelectorAll?.('[data-confirm-delete]')?.forEach((form) => {
        if (form.dataset.confirmDeleteBound === '1') return;
        form.dataset.confirmDeleteBound = '1';
        form.addEventListener('submit', (event) => {
            if (typeof browser.confirm === 'function'
                && !browser.confirm(form.dataset.confirmDelete || 'Xác nhận xóa hợp đồng?')) {
                event.preventDefault();
            }
        });
    });
}

function bindContractForm(root = typeof document !== 'undefined' ? document : null) {
    const form = root?.querySelector?.('[data-contract-form]');
    if (!form || form.dataset.contractFormBound === '1') return;
    form.dataset.contractFormBound = '1';

    const typeSelect = form.querySelector?.('[name="ma_lhd"]');
    const expiryInput = form.querySelector?.('[name="ngay_het_han"]');
    const salaryInput = form.querySelector?.('[name="luong_co_ban"]');
    const expiryMarker = root.querySelector?.('[data-expiry-required-marker]');

    if (salaryInput) salaryInput.value = formatVietnameseIntegerInput(salaryInput.value);

    const syncExpiry = () => {
        const term = typeSelect?.selectedOptions?.[0]?.dataset?.contractTerm;
        const indefinite = term === 'indefinite';
        if (expiryInput) {
            expiryInput.disabled = indefinite;
            expiryInput.required = !indefinite;
            if (indefinite) expiryInput.value = '';
        }
        if (expiryMarker) expiryMarker.hidden = !indefinite;
    };

    typeSelect?.addEventListener?.('change', syncExpiry);
    syncExpiry();

    salaryInput?.addEventListener?.('input', () => {
        const before = String(salaryInput.value ?? '');
        const digitsBeforeCaret = before.slice(0, salaryInput.selectionStart ?? before.length).replace(/\D/g, '').length;
        salaryInput.value = formatVietnameseIntegerInput(before);
        const nextCaret = salaryInput.value.length
            ? salaryInput.value.length - Math.max(0, salaryInput.value.replace(/\D/g, '').length - digitsBeforeCaret)
            : 0;
        salaryInput.setSelectionRange?.(Math.max(0, nextCaret), Math.max(0, nextCaret));
    });

    form.addEventListener('submit', () => {
        const digits = parseVietnameseInteger(salaryInput?.value);
        if (salaryInput && digits !== null) salaryInput.value = digits;
    });
}

function bindExpiringFilter(root = typeof document !== 'undefined' ? document : null) {
    const checkbox = root?.querySelector?.('#sap_het_han');
    const form = checkbox?.form || root?.querySelector?.('#contract-filter-form');
    if (!checkbox || !form || checkbox.dataset.expiringFilterBound === '1') return;

    checkbox.dataset.expiringFilterBound = '1';
    checkbox.addEventListener('change', () => {
        if (typeof form.requestSubmit === 'function') {
            form.requestSubmit();
            return;
        }

        form.submit?.();
    });
}

if (typeof document !== 'undefined') {
    document.addEventListener('DOMContentLoaded', () => {
        bindConfirmDeleteForms();
        bindContractForm();
        bindExpiringFilter();
    });

    const contractModal = initializeSimpleEditModal(document, window);
    document.addEventListener('click', (event) => {
        const trigger = event.target?.closest?.('[data-action="modal"][data-modal-mode]');
        if (!trigger || !contractModal) return;

        event.preventDefault();
        contractModal.open(trigger, trigger.dataset.modalUrl || trigger.href, trigger.dataset.modalMode)
            .then(() => bindContractForm(document));
    });
    document.addEventListener('click', (event) => {
        const closeTrigger = event.target?.closest?.('[data-contract-modal-close]');
        if (!closeTrigger || !contractModal) return;

        event.preventDefault();
        contractModal.close();
    });
}

export {
    bindConfirmDeleteForms,
    bindContractForm,
    bindExpiringFilter,
    formatVietnameseInteger,
    formatVietnameseIntegerInput,
    parseVietnameseInteger,
};
