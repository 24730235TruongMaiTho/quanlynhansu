function focusFirstControl(dialog) {
    dialog.querySelector('[data-dialog-cancel], [data-dialog-submit]')?.focus();
}

function submitButtons(dialog) {
    return [...dialog.querySelectorAll('[data-dialog-submit]')];
}

function initializeDialog(dialog, browser) {
    let previousFocus = null;
    let submitting = false;

    const restoreFocus = () => {
        // Returning focus to the opener preserves keyboard context after a modal closes.
        previousFocus?.focus?.();
        previousFocus = null;
    };

    const closeDialog = () => {
        if (typeof dialog.close === 'function' && dialog.open) {
            dialog.close();
        } else {
            dialog.hidden = true;
            dialog.open = false;
        }

        restoreFocus();
    };

    dialog.addEventListener('keydown', (event) => {
        if (event.key !== 'Escape') {
            return;
        }

        event.preventDefault();
        closeDialog();
    });

    const cancelDialog = (event) => {
        event.preventDefault();
        closeDialog();
    };
    dialog.querySelectorAll('[data-dialog-cancel]').forEach((cancel) => {
        cancel.addEventListener('click', cancelDialog);
    });

    dialog.addEventListener('submit', (event) => {
        if (submitting) {
            event.preventDefault();
            return;
        }

        submitting = true;
        dialog.setAttribute('aria-busy', 'true');
        submitButtons(dialog).forEach((button) => {
            button.disabled = true;
            button.setAttribute('aria-disabled', 'true');
        });
    });

    dialog.addEventListener('close', () => {
        submitting = false;
        dialog.removeAttribute('aria-busy');
        submitButtons(dialog).forEach((button) => {
            button.disabled = false;
            button.setAttribute('aria-disabled', 'false');
        });
        restoreFocus();
    });

    return {
        open(opener, event) {
            previousFocus = opener;
            if (typeof dialog.showModal === 'function') {
                dialog.showModal();
                focusFirstControl(dialog);
                return;
            }

            event.preventDefault();
            const form = dialog.querySelector('[data-dialog-form]');
            const message = form?.getAttribute('data-confirm-message')
                || 'Xác nhận thao tác với hồ sơ nhân viên?';
            const confirmed = typeof browser.confirm === 'function'
                && browser.confirm(message);

            if (!confirmed) {
                restoreFocus();
                return;
            }

            if (typeof form?.requestSubmit === 'function') {
                form.requestSubmit();
            } else {
                form?.submit?.();
            }
        },
    };
}

export function initializeActionDialogs(
    root = typeof document !== 'undefined' ? document : null,
    browser = typeof window !== 'undefined' ? window : {},
) {
    if (!root?.querySelectorAll) {
        return;
    }

    const dialogs = [...root.querySelectorAll('[data-action-dialog]')];
    const controls = dialogs.map((dialog) => ({
        dialog,
        controller: initializeDialog(dialog, browser),
    }));

    root.querySelectorAll('[data-dialog-open]').forEach((opener) => {
        const dialogId = opener.getAttribute('data-dialog-open');
        const target = controls.find(({ dialog }) => (
            (dialog.id ?? dialog.getAttribute?.('id')) === dialogId
        ));
        if (!target) {
            return;
        }

        opener.addEventListener('click', (event) => target.controller.open(opener, event));
    });
}
