import { initializeListFilters } from '../shared/list-filter.js';
import { initializeSimpleEditModal } from '../shared/edit-modal.js';

function disableSubmit(form) {
    const submit = form.querySelector('[data-submit]');

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
    initializeListFilters(document, '[data-department-filter]');
    const editModal = initializeSimpleEditModal(document, window);
    document.addEventListener('click', (event) => {
        const trigger = event.target?.closest?.('[data-action="modal"]');
        if (!trigger || !editModal) {
            return;
        }

        event.preventDefault();
        editModal.open(trigger, trigger.dataset.modalUrl || trigger.href);
    });

    document.querySelectorAll('[data-phong-ban-form]').forEach((form) => {
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
