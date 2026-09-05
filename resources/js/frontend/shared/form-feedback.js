function messagesFor(value) {
    if (Array.isArray(value)) {
        return value.filter((message) => typeof message === 'string' && message.trim() !== '');
    }

    return typeof value === 'string' && value.trim() !== '' ? [value] : [];
}

function fieldFor(form, name, labels = {}) {
    const label = labels?.[name];
    const candidates = [
        name,
        typeof label === 'object' ? label?.name : null,
        typeof label === 'object' ? label?.id : null,
    ].filter(Boolean);

    for (const candidate of candidates) {
        const field = form?.elements?.namedItem?.(candidate);
        if (field && typeof field.setAttribute === 'function') return field;
    }

    const escapedName = String(name).replace(/(["\\])/g, '\\$1');
    return form?.querySelector?.('[name="' + escapedName + '"]') ?? null;
}

function clearExistingFeedback(form) {
    const feedbackIds = new Set();
    form?.querySelectorAll?.('[data-form-feedback]').forEach((error) => {
        if (error.id) feedbackIds.add(error.id);
    });
    form?.querySelectorAll?.('.is-invalid').forEach((field) => {
        field.classList?.remove('is-invalid');
        field.removeAttribute?.('aria-invalid');
    });
    form?.querySelectorAll?.('[aria-describedby]').forEach((field) => {
        const describedBy = (field.getAttribute?.('aria-describedby') || '')
            .split(/\s+/)
            .filter((id) => id && !feedbackIds.has(id));
        if (describedBy.length > 0) {
            field.setAttribute?.('aria-describedby', describedBy.join(' '));
        } else {
            field.removeAttribute?.('aria-describedby');
        }
    });
    form?.querySelectorAll?.('[data-form-feedback]').forEach((error) => {
        error.remove?.();
    });
}

function errorElementFor(field, form, fieldId) {
    const document = field?.ownerDocument
        || form?.ownerDocument
        || (typeof globalThis.document !== 'undefined' ? globalThis.document : null);
    const error = document?.createElement?.('div');
    if (!error) return null;

    error.className = 'invalid-feedback';
    error.dataset ??= {};
    error.dataset.formFeedback = 'true';
    error.id = `${fieldId || field.id}-error`;
    error.setAttribute?.('role', 'alert');
    field.parentElement?.append?.(error);

    return error;
}

export function applyFieldErrors(form, errors = {}, labels = {}) {
    clearExistingFeedback(form);

    Object.entries(errors ?? {}).forEach(([name, value]) => {
        const field = fieldFor(form, name, labels);
        const message = messagesFor(value)[0];
        if (!field || !message) return;

        const fieldId = field.id || field.getAttribute?.('id') || name;
        const errorId = `${fieldId}-error`;
        const error = errorElementFor(field, form, fieldId);
        if (!error) return;

        error.id = errorId;
        error.textContent = message;
        field.classList?.add('is-invalid');
        field.setAttribute?.('aria-invalid', 'true');

        const describedBy = (field.getAttribute?.('aria-describedby') || '')
            .split(/\s+/)
            .filter(Boolean)
            .filter((id) => id !== errorId);
        describedBy.push(errorId);
        field.setAttribute?.('aria-describedby', describedBy.join(' '));
    });
}
