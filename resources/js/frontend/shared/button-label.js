/**
 * Change the visible label of an icon button without replacing its icon.
 * Every icon + text control should keep a [data-button-label] element.
 */
export function setButtonLabel(button, text) {
    if (!button) return;

    // Test doubles and non-DOM adapters may not expose querySelector. The
    // browser path always uses the nested label contract; this fallback keeps
    // the helper safe for those adapters without affecting real controls.
    const label = button.querySelector?.('[data-button-label]');
    if (label) {
        label.textContent = text;
    } else if (typeof button.querySelector !== 'function') {
        button.textContent = text;
    } else {
        button.setAttribute?.('aria-label', text);
        button.setAttribute?.('title', text);
    }
}

export function getButtonLabel(button) {
    return button?.querySelector?.('[data-button-label]')?.textContent ?? button?.textContent ?? '';
}
