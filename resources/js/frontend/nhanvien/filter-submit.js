const initializedFilters = new WeakSet();

function setSubmittingState(form, button) {
    form.setAttribute('aria-busy', 'true');
    button.disabled = true;
    button.setAttribute('aria-disabled', 'true');
    button.textContent = button.dataset.submittingText || 'Đang lọc…';
}

export function initializeEmployeeFilter(form) {
    const submitButton = form?.querySelector?.('[data-disable-on-submit]');
    if (!form || !submitButton) {
        return;
    }

    if (initializedFilters.has(form)) {
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
        setSubmittingState(form, submitButton);
    });
}

export function initializeEmployeeFilters(root = typeof document !== 'undefined' ? document : null) {
    root?.querySelectorAll?.('[data-employee-filter]')?.forEach(initializeEmployeeFilter);
}
