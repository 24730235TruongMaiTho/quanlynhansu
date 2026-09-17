import { setButtonLabel } from './button-label.js';

/**
 * Create a guarded destructive action for controls whose target is selected
 * elsewhere in the page. The selection callback is re-read before transport
 * and after the request so a failed mutation can restore the current state.
 */
export function createDeleteAction({
    button,
    getSelection,
    onInvalidSelection,
    confirmAction,
    requestDelete,
    onSuccess,
    onError,
    sync,
    busyLabel = 'Đang xóa…',
}) {
    let inFlight = false;

    return async function runDeleteAction() {
        if (inFlight) return false;

        const selection = getSelection?.();
        if (
            !selection?.id ||
            selection.persisted !== true ||
            selection.canDelete !== true
        ) {
            onInvalidSelection?.(selection);
            sync?.();
            return false;
        }

        const confirmation = confirmAction?.();
        if (
            confirmation &&
            typeof confirmation.then === 'function'
        ) {
            if (!(await confirmation)) return false;
        } else if (!confirmation) {
            return false;
        }

        inFlight = true;
        if (button) {
            button.disabled = true;
            button.setAttribute?.('aria-busy', 'true');
            button.title = busyLabel;
            button.setAttribute?.('aria-label', busyLabel);
            setButtonLabel(button, busyLabel);
        }

        try {
            await requestDelete(selection.id);
            await onSuccess?.();
            return true;
        } catch (error) {
            onError?.(error);
            return false;
        } finally {
            inFlight = false;
            button?.removeAttribute?.('aria-busy');
            sync?.();
        }
    };
}
