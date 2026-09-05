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

    const typeSelect = root.getElementById?.('ma_lhd');
    const expiryInput = root.getElementById?.('ngay_het_han');
    const salaryInput = root.getElementById?.('luong_co_ban');
    const expiryMarker = root.querySelector?.('[data-expiry-required-marker]');

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
        salaryInput.value = formatVietnameseInteger(before);
        const nextCaret = salaryInput.value.length
            ? salaryInput.value.length - Math.max(0, parseVietnameseInteger(salaryInput.value)?.length - digitsBeforeCaret)
            : 0;
        salaryInput.setSelectionRange?.(Math.max(0, nextCaret), Math.max(0, nextCaret));
    });

    form.addEventListener('submit', () => {
        const digits = parseVietnameseInteger(salaryInput?.value);
        if (salaryInput && digits !== null) salaryInput.value = digits;
    });
}

if (typeof document !== 'undefined') {
    document.addEventListener('DOMContentLoaded', () => {
        bindConfirmDeleteForms();
        bindContractForm();
    });
}

export {
    bindConfirmDeleteForms,
    bindContractForm,
    formatVietnameseInteger,
    parseVietnameseInteger,
};
