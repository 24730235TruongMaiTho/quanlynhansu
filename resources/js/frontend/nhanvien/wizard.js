import {
    firstInvalidStep,
    nextStep,
    previousStep,
    reconcileAvatarChoice,
} from './wizard-state.js';

function fieldValue(field) {
    if (field instanceof HTMLSelectElement) {
        return field.selectedOptions[0]?.textContent?.trim() || '';
    }

    if (field instanceof HTMLInputElement && field.type === 'file') {
        return field.files?.[0]?.name || '';
    }

    return field.value.trim();
}

function initializeWizard(form) {
    const page = form.closest('.employee-page');
    const steps = [...form.querySelectorAll('[data-wizard-step]')];
    const submitButton = form.querySelector('[data-submit-employee]');
    let currentStep = Math.min(3, Math.max(1, Number(form.dataset.initialStep) || 1));
    let submitting = false;
    let invalidFrame = null;

    if (!page || steps.length !== 3 || !submitButton) {
        return;
    }

    const indicators = [...page.querySelectorAll('[data-step-indicator]')];
    const avatarUpload = form.querySelector('[data-avatar-upload]');
    const avatarDelete = form.querySelector('[data-avatar-delete]');

    page.classList.add('is-enhanced');

    const updateReview = () => {
        form.querySelectorAll('[data-review-output]').forEach((output) => {
            const source = form.elements.namedItem(output.dataset.reviewOutput);
            output.textContent = source instanceof HTMLElement && fieldValue(source)
                ? fieldValue(source)
                : 'Chưa nhập';
        });
    };

    const showStep = (step, focusTarget = null) => {
        currentStep = Math.min(3, Math.max(1, step));

        steps.forEach((panel) => {
            panel.hidden = Number(panel.dataset.wizardStep) !== currentStep;
        });

        indicators.forEach((indicator) => {
            const active = Number(indicator.dataset.stepIndicator) === currentStep;
            indicator.setAttribute('aria-current', active ? 'step' : 'false');
        });

        if (currentStep === 3) {
            updateReview();
        }

        const target = focusTarget || steps[currentStep - 1]?.querySelector('[data-step-heading]');
        target?.focus({ preventScroll: true });
    };

    const firstInvalidInStep = (step) => [...step.querySelectorAll('input, select, textarea')]
        .find((field) => !field.checkValidity());

    form.addEventListener('click', (event) => {
        const nextButton = event.target.closest('[data-wizard-next]');
        const previousButton = event.target.closest('[data-wizard-previous]');

        if (nextButton) {
            const invalidField = firstInvalidInStep(steps[currentStep - 1]);

            if (invalidField) {
                invalidField.reportValidity();
                invalidField.focus();
                return;
            }

            showStep(nextStep(currentStep));
        }

        if (previousButton) {
            showStep(previousStep(currentStep));
        }
    });

    form.addEventListener('input', updateReview);
    form.addEventListener('change', updateReview);
    avatarUpload?.addEventListener('change', () => {
        const state = reconcileAvatarChoice(
            'file',
            (avatarUpload.files?.length || 0) > 0,
            avatarDelete?.checked || false,
        );
        if (avatarDelete) {
            avatarDelete.checked = state.deleteChecked;
        }
    });
    avatarDelete?.addEventListener('change', () => {
        const state = reconcileAvatarChoice(
            'delete',
            (avatarUpload?.files?.length || 0) > 0,
            avatarDelete.checked,
        );
        if (avatarUpload && !state.hasFile) {
            avatarUpload.value = '';
        }
    });
    form.addEventListener('invalid', () => {
        if (invalidFrame !== null) {
            return;
        }

        invalidFrame = requestAnimationFrame(() => {
            invalidFrame = null;
            const invalidFields = [...form.querySelectorAll(':invalid')];
            const target = invalidFields[0];

            if (!target) {
                return;
            }

            showStep(firstInvalidStep(invalidFields.map((field) => field.name)), target);
        });
    }, true);

    form.addEventListener('submit', (event) => {
        if (submitting) {
            event.preventDefault();
            return;
        }

        submitting = true;
        form.setAttribute('aria-busy', 'true');
        submitButton.disabled = true;
        submitButton.setAttribute('aria-disabled', 'true');
        submitButton.textContent = submitButton.dataset.submittingText || 'Đang lưu…';
    });

    showStep(currentStep, form.querySelector('[data-error-focus]'));
}

export function initializeEmployeeWizards() {
    document.querySelectorAll('[data-employee-wizard]').forEach(initializeWizard);
}
