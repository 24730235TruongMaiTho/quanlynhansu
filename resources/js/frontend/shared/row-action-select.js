function resetSelect(select) {
    if (select && 'selectedIndex' in select) {
        select.selectedIndex = 0;
    }
}

function elementById(root, id) {
    if (!id) {
        return null;
    }

    if (typeof root?.getElementById === 'function') {
        return root.getElementById(id);
    }

    return root?.querySelector?.(`#${id}`) ?? null;
}

export function handleRowAction(
    select,
    option,
    browser = typeof window !== 'undefined' ? window : {},
    navigation = typeof window !== 'undefined' ? window.location : null,
    root = typeof document !== 'undefined' ? document : null,
    handlers = {},
) {
    if (!option?.dataset?.action) {
        resetSelect(select);
        return;
    }

    const action = option.dataset.action;
    resetSelect(select);

    if (action === 'navigate') {
        if (navigation && option.value) {
            navigation.href = option.value;
        }
        return;
    }

    if (action === 'delete') {
        const confirmed = typeof browser.confirm === 'function'
            && browser.confirm(option.dataset.confirmMessage || 'Xác nhận thao tác xóa?');
        if (!confirmed) {
            return;
        }

        elementById(root, option.dataset.formId)?.submit?.();
        return;
    }

    if (action === 'dialog') {
        const dialog = elementById(root, option.dataset.dialogId);
        if (typeof dialog?.showModal === 'function') {
            dialog.showModal();
            dialog.querySelector?.('[data-dialog-cancel], [data-dialog-submit]')?.focus?.();
            return;
        }

        const form = dialog?.querySelector?.('[data-dialog-form]');
        const confirmed = typeof browser.confirm === 'function'
            && browser.confirm(form?.getAttribute?.('data-confirm-message') || 'Xác nhận thao tác?');
        if (confirmed) {
            if (typeof form?.requestSubmit === 'function') {
                form.requestSubmit();
            } else {
                form?.submit?.();
            }
        }
    }

    if (action === 'modal') {
        handlers?.modal?.(option, select);
    }
}

export function bindRowActionSelects(
    root = typeof document !== 'undefined' ? document : null,
    browser = typeof window !== 'undefined' ? window : {},
    navigation = typeof window !== 'undefined' ? window.location : null,
    handlers = {},
) {
    if (!root?.querySelectorAll) {
        return;
    }

    root.querySelectorAll('[data-row-action-select]').forEach((select) => {
        select.addEventListener('change', () => {
            handleRowAction(select, select.selectedOptions?.[0], browser, navigation, root, handlers);
        });
    });
}
