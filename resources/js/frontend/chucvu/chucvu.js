function disableSubmit(form) {
    const submit = form?.querySelector('[data-submit]');

    if (!submit) {
        return;
    }

    const submittingText = submit.dataset.submittingText;
    submit.disabled = true;
    submit.setAttribute('aria-disabled', 'true');

    if (submittingText) {
        submit.textContent = submittingText;
    }
}

if (typeof document !== 'undefined') {
    document.querySelectorAll('[data-chuc-vu-form]').forEach((form) => {
        form.addEventListener('submit', () => disableSubmit(form));
    });

    document.querySelectorAll('[data-confirm-delete]').forEach((form) => {
        form.addEventListener('submit', (event) => {
            if (!window.confirm(form.dataset.confirmDelete)) {
                event.preventDefault();
                return;
            }

            disableSubmit(form);
        });
    });
}

export { disableSubmit };
