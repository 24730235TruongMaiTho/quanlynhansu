function callFocus(element) {
    element?.focus?.();
}

/**
 * Manage one accessible destructive confirmation dialog.
 *
 * The dialog is intentionally fail-closed when the native dialog API is not
 * available. Callers can use onUnavailable to provide a non-destructive toast.
 */
export function createConfirmDialog({
    dialog,
    cancelButton,
    confirmButton,
    onUnavailable,
}) {
    let pendingResolve = null;
    let opener = null;
    let listenersBound = false;

    const notifyUnavailable = () => {
        try {
            onUnavailable?.();
        } catch (_) {
            // A status callback must never turn a safe denial into an action.
        }
    };

    const settle = (confirmed, closeDialog = true) => {
        if (!pendingResolve) return false;

        const resolve = pendingResolve;
        const previousOpener = opener;
        pendingResolve = null;
        opener = null;

        if (
            closeDialog &&
            dialog?.open &&
            typeof dialog.close === 'function'
        ) {
            dialog.close(confirmed ? 'confirm' : 'cancel');
        }

        callFocus(previousOpener);
        resolve(confirmed);
        return true;
    };

    const bindListeners = () => {
        if (listenersBound) return;

        listenersBound = true;

        cancelButton?.addEventListener?.('click', (event) => {
            event.preventDefault?.();
            settle(false);
        });

        confirmButton?.addEventListener?.('click', (event) => {
            event.preventDefault?.();
            settle(true);
        });

        dialog?.addEventListener?.('cancel', (event) => {
            event.preventDefault?.();
            settle(false);
        });

        dialog?.addEventListener?.('keydown', (event) => {
            if (event.key !== 'Escape') return;

            event.preventDefault?.();
            settle(false);
        });

        dialog?.addEventListener?.('click', (event) => {
            if (event.target === dialog) settle(false);
        });

        dialog?.addEventListener?.('close', () => {
            settle(false, false);
        });
    };

    const open = (nextOpener = null) => {
        if (
            typeof dialog?.showModal !== 'function' ||
            typeof dialog?.close !== 'function'
        ) {
            notifyUnavailable();
            return Promise.resolve(false);
        }

        if (pendingResolve) settle(false);

        bindListeners();
        opener = nextOpener;

        const result = new Promise((resolve) => {
            pendingResolve = resolve;
        });

        try {
            if (!dialog.open) dialog.showModal();
        } catch (_) {
            settle(false, false);
            notifyUnavailable();
            return result;
        }

        callFocus(cancelButton);
        return result;
    };

    return {
        open,
        close: () => settle(false),
    };
}
