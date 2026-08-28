const initializedFilters = new WeakSet();

export function initializeListFilter(form) {
    const submitButton = form?.querySelector?.('[data-disable-on-submit]');
    if (!form || !submitButton || initializedFilters.has(form)) {
        return;
    }

    initializedFilters.add(form);
    let submitting = false;

    form.addEventListener('submit', (event) => {
        if (submitting) {
            event.preventDefault();
            return;
        }

        submitting = true;
        form.setAttribute('aria-busy', 'true');
        submitButton.disabled = true;
        submitButton.setAttribute('aria-disabled', 'true');
        submitButton.textContent = submitButton.dataset.submittingText || 'Đang lọc…';
    });
}

export function initializeListFilters(
    root = typeof document !== 'undefined' ? document : null,
    selector = '[data-list-filter]',
) {
    root?.querySelectorAll?.(selector)?.forEach(initializeListFilter);
}
