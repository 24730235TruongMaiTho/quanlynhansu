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

async function createPosition(form) {
    const submit = form.querySelector('#position-submit');
    const errorBox = form.querySelector('#position-form-error');
    const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content;

    submit.disabled = true;
    errorBox.classList.add('d-none');

    try {
        const response = await fetch(form.action, {
            method: 'POST',
            headers: {
                Accept: 'application/json',
                'X-CSRF-TOKEN': csrfToken,
            },
            body: new FormData(form),
        });
        const payload = await response.json().catch(() => ({}));

        if (!response.ok) {
            const errors = Object.values(payload.errors || {}).flat();
            throw new Error(errors[0] || payload.message || 'Không thể tạo chức vụ.');
        }

        bootstrap.Modal.getOrCreateInstance(document.querySelector('#position-modal')).hide();
        window.location.reload();
    } catch (error) {
        errorBox.textContent = error.message;
        errorBox.classList.remove('d-none');
    } finally {
        submit.disabled = false;
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

    const createButton = document.querySelector('[data-position-create]');
    const createForm = document.querySelector('#position-form');
    const positionModal = document.querySelector('#position-modal');

    if (createButton && createForm && positionModal) {
        const modal = bootstrap.Modal.getOrCreateInstance(positionModal);
        createButton.addEventListener('click', () => {
            createForm.reset();
            createForm.querySelector('#position-form-error').classList.add('d-none');
            modal.show();
        });
        createForm.addEventListener('submit', (event) => {
            event.preventDefault();
            createPosition(createForm);
        });
    }
}

export { createPosition, disableSubmit };
