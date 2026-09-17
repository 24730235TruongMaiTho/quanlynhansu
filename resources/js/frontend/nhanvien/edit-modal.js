import { setButtonLabel, getButtonLabel } from '../shared/button-label.js';
import { initializeEmployeeWizards } from './wizard.js';

const LOAD_ERROR_MESSAGE = 'Không tải được biểu mẫu chỉnh sửa. Vui lòng đóng popup và thử lại sau.';
const UPDATE_ERROR_MESSAGE = 'Không thể cập nhật nhân viên lúc này. Vui lòng thử lại sau.';
const FORM_ERROR_FALLBACK = 'Không thể cập nhật hồ sơ. Vui lòng kiểm tra lại thông tin hoặc thử lại sau.';

function setHidden(element, hidden) {
    if (element) {
        element.hidden = hidden;
    }
}

function fieldFor(form, name) {
    const named = form?.elements?.namedItem?.(name);
    if (named && typeof named.focus === 'function') {
        return named;
    }

    const escapedName = String(name).replace(/(["\\])/g, '\\$1');
    return form?.querySelector?.('[name="' + escapedName + '"]') ?? null;
}

function messagesFor(value) {
    if (Array.isArray(value)) {
        return value.filter((message) => typeof message === 'string' && message.trim() !== '');
    }

    return typeof value === 'string' && value.trim() !== '' ? [value] : [];
}

function clearValidationErrors(form) {
    form?.querySelectorAll?.('.is-invalid').forEach((field) => {
        field.classList.remove('is-invalid');
        field.removeAttribute?.('aria-invalid');
    });
    form?.querySelectorAll?.('[data-modal-field-error]').forEach((error) => {
        error.remove?.();
        error.textContent = '';
    });
    const formError = form?.querySelector?.('[data-modal-form-error]');
    if (formError) {
        formError.hidden = true;
        formError.textContent = '';
    }
}

function safeFormMessage(...candidates) {
    const message = candidates
        .flatMap((candidate) => messagesFor(candidate))
        .find((candidate) => candidate.trim() !== '');

    if (!message || /sqlstate|exception|stack trace|in file/i.test(message)) {
        return FORM_ERROR_FALLBACK;
    }

    return message;
}

function renderValidationErrors(form, errors, fallbackMessage = '') {
    clearValidationErrors(form);
    let firstField = null;
    const formMessages = [];

    Object.entries(errors ?? {}).forEach(([name, messages]) => {
        const field = fieldFor(form, name);
        const firstMessage = messagesFor(messages)[0];
        if (!field || !firstMessage) {
            if (firstMessage) {
                formMessages.push(firstMessage);
            }
            return;
        }

        field.classList?.add('is-invalid');
        field.setAttribute?.('aria-invalid', 'true');

        let error = form.querySelector?.('[data-modal-field-error="' + name + '"]');
        if (!error && form.ownerDocument?.createElement) {
            error = form.ownerDocument.createElement('div');
            error.className = 'invalid-feedback';
            error.dataset.modalFieldError = name;
            error.id = 'modal-' + name + '-error';
            const checkboxGroup = field.closest?.('.form-check');
            if (checkboxGroup) {
                checkboxGroup.append(error);
            } else {
                field.parentElement?.append(error);
            }
        }

        if (error) {
            error.textContent = firstMessage;
            error.setAttribute?.('role', 'alert');
            const describedBy = (field.getAttribute?.('aria-describedby') || '')
                .split(/\s+/)
                .filter(Boolean)
                .filter((id) => id !== error.id);
            describedBy.push(error.id || 'modal-' + name + '-error');
            field.setAttribute?.('aria-describedby', describedBy.join(' '));
        }

        firstField ??= field;
    });

    if (!firstField) {
        const formError = form?.querySelector?.('[data-modal-form-error]');
        if (formError) {
            formError.textContent = safeFormMessage(formMessages, fallbackMessage);
            formError.hidden = false;
            formError.setAttribute?.('role', 'alert');
        }
    }

    const wizard = firstField?.closest?.('[data-employee-wizard]');
    const step = firstField?.closest?.('[data-wizard-step]');
    if (wizard && step) {
        wizard.showEmployeeWizardStep?.(Number(step.dataset.wizardStep), firstField);
    }
    firstField?.focus?.();
    return firstField;
}

function formAction(form) {
    return form?.getAttribute?.('action') || form?.action || '';
}

function formMethod(form) {
    return (form?.getAttribute?.('method') || form?.method || 'POST').toUpperCase();
}

async function readJson(response) {
    try {
        return await response.json();
    } catch {
        return null;
    }
}

function firstFormControl(content) {
    return content?.querySelector?.('[data-step-heading], input, select, textarea, button');
}

export function createEmployeeEditModalController(ui, dependencies = {}) {
    const fetchImpl = dependencies.fetch
        || (typeof fetch === 'function' ? fetch.bind(globalThis) : null);
    const navigation = dependencies.navigation
        || (typeof window !== 'undefined' ? window.location : null);
    const initializeWizards = dependencies.initializeWizards || (() => {});
    const createFormData = dependencies.createFormData
        || ((form) => new FormData(form));

    let opener = null;
    let activeUrl = null;
    let modalMode = 'edit';
    let loadingState = null;
    let loadGeneration = 0;
    let submitting = false;

    const resetError = () => {
        setHidden(ui.error, true);
        if (ui.error) ui.error.textContent = '';
    };

    const showError = (message) => {
        if (ui.error) ui.error.textContent = message;
        setHidden(ui.error, false);
    };

    const setLoading = (loading) => {
        setHidden(ui.loading, !loading);
        if (ui.dialog) {
            ui.dialog.setAttribute('aria-busy', loading ? 'true' : 'false');
        }
    };

    const setCloseDisabled = (disabled) => {
        if (!ui.close) {
            return;
        }

        ui.close.disabled = disabled;
        ui.close.setAttribute('aria-disabled', disabled ? 'true' : 'false');
    };

    const setModalTitle = () => {
        if (!ui.title) return;
        const title = ui.title.getAttribute?.(modalMode === 'create' ? 'data-create-title' : 'data-edit-title');
        if (title) ui.title.textContent = title;
    };

    const restoreFocus = () => {
        opener?.focus?.();
        opener = null;
    };

    const close = (force = false) => {
        // Khi submit đang chờ, mọi đường đóng đều bị khóa để không nhận response lệch modal.
        if (submitting && !force) {
            return false;
        }

        if (typeof ui.dialog?.close === 'function' && ui.dialog.open) {
            ui.dialog.close();
        } else if (ui.dialog) {
            ui.dialog.hidden = true;
            ui.dialog.open = false;
        }

        loadGeneration += 1;
        activeUrl = null;
        modalMode = 'edit';
        loadingState = null;
        submitting = false;
        setCloseDisabled(false);
        if (ui.content) ui.content.innerHTML = '';
        restoreFocus();
        return true;
    };

    const load = async () => {
        if (!activeUrl || !fetchImpl) {
            setLoading(false);
            showError(LOAD_ERROR_MESSAGE);
            return;
        }

        if (loadingState?.url === activeUrl) {
            return loadingState.promise;
        }

        const generation = ++loadGeneration;
        setLoading(true);
        resetError();
        if (ui.content) {
            ui.content.innerHTML = '';
        }

        const promise = (async () => {
            try {
                const headers = {
                        Accept: 'text/html',
                        'X-Requested-With': 'XMLHttpRequest',
                        'X-Form-Modal': modalMode,
                    };
                headers[modalMode === 'create' ? 'X-Employee-Create-Modal' : 'X-Employee-Edit-Modal'] = '1';
                const response = await fetchImpl(activeUrl, {
                    headers,
                    credentials: 'same-origin',
                });
                const html = await response.text();

                if (!response.ok || !html.includes('data-employee-wizard') || generation !== loadGeneration) {
                    throw new Error('modal-load-failed');
                }

                ui.content.innerHTML = html;
                initializeWizards(ui.content);
                firstFormControl(ui.content)?.focus?.();
                setLoading(false);
            } catch {
                if (generation === loadGeneration) {
                    setLoading(false);
                    showError(LOAD_ERROR_MESSAGE);
                }
            } finally {
                if (loadingState?.promise === promise) {
                    loadingState = null;
                }
            }
        })();

        loadingState = { url: activeUrl, promise };
        return promise;
    };

    const finishSubmit = (form) => {
        submitting = false;
        setCloseDisabled(false);
        form?.removeAttribute?.('aria-busy');
        form?.querySelectorAll?.('[data-submit-employee]').forEach((button) => {
            button.disabled = false;
            button.setAttribute('aria-disabled', 'false');
            if (button.dataset.previousText !== undefined) {
                setButtonLabel(button, button.dataset.previousText);
                delete button.dataset.previousText;
            }
        });
    };

    const submit = async (form, event = {}) => {
        if (submitting) {
            event.preventDefault?.();
            return null;
        }

        const action = formAction(form);
        if (!action || !fetchImpl) {
            event.preventDefault?.();
            showError(UPDATE_ERROR_MESSAGE);
            return null;
        }

        event.preventDefault?.();
        submitting = true;
        setCloseDisabled(true);
        clearValidationErrors(form);
        form.setAttribute?.('aria-busy', 'true');
        form.querySelectorAll?.('[data-submit-employee]').forEach((button) => {
            button.disabled = true;
            button.setAttribute('aria-disabled', 'true');
            button.dataset.previousText ??= getButtonLabel(button);
            setButtonLabel(button, button.dataset.submittingText || 'Đang lưu…');
        });

        try {
            const body = createFormData(form);
            const preservedDistrict = form?.dataset?.preservedDistrict;
            if (preservedDistrict && typeof body?.append === 'function') {
                body.append('quan_huyen', preservedDistrict);
            }

            const response = await fetchImpl(action, {
                method: formMethod(form),
                body,
                headers: {
                    Accept: 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                },
                credentials: 'same-origin',
            });
            const payload = await readJson(response);

            if (response.status === 422 && payload?.errors) {
                renderValidationErrors(form, payload.errors, payload.message);
                finishSubmit(form);
                return payload;
            }

            if (!response.ok || payload?.success !== true) {
                showError(UPDATE_ERROR_MESSAGE);
                finishSubmit(form);
                return payload;
            }

            close(true);
            navigation?.reload?.();
            return payload;
        } catch {
            showError(UPDATE_ERROR_MESSAGE);
            finishSubmit(form);
            return null;
        }
    };

    ui.close?.addEventListener?.('click', (event) => {
        event.preventDefault?.();
        close();
    });
    ui.dialog?.addEventListener?.('keydown', (event) => {
        handleKeydown(event);
    });
    ui.dialog?.addEventListener?.('cancel', (event) => {
        event.preventDefault?.();
        if (!submitting) {
            close();
        }
    });
    ui.content?.addEventListener?.('submit', (event) => {
        submit(event.target, event);
    });

    function handleKeydown(event) {
        if (event.key !== 'Escape') {
            return;
        }

        event.preventDefault?.();
        if (submitting) {
            return;
        }
        close();
    }

    async function open(nextOpener, url, requestedMode = null) {
        if (!url || submitting) {
            return null;
        }

        opener = nextOpener;
        activeUrl = url;
        modalMode = requestedMode || nextOpener?.dataset?.employeeModalMode || nextOpener?.dataset?.modalMode || 'edit';
        setModalTitle();
        resetError();
        setLoading(true);
        if (typeof ui.dialog?.showModal === 'function' && !ui.dialog.open) {
            ui.dialog.showModal();
        } else if (ui.dialog) {
            ui.dialog.hidden = false;
            ui.dialog.open = true;
        }
        ui.close?.focus?.();

        return load();
    }

    return {
        open,
        close,
        load,
        submit,
        handleKeydown,
    };
}

export function initializeEmployeeEditModal(
    root = typeof document !== 'undefined' ? document : null,
    browser = typeof window !== 'undefined' ? window : {},
) {
    const dialog = root?.querySelector?.('[data-employee-modal], [data-employee-edit-modal]');
    if (!dialog) {
        return null;
    }

    return createEmployeeEditModalController({
        dialog,
        content: dialog.querySelector('[data-employee-edit-content]'),
        loading: dialog.querySelector('[data-employee-edit-loading]'),
        error: dialog.querySelector('[data-employee-edit-error]'),
        close: dialog.querySelector('[data-employee-edit-close]'),
        title: dialog.querySelector('[data-employee-edit-title]'),
    }, {
        fetch: typeof browser.fetch === 'function' ? browser.fetch.bind(browser) : undefined,
        navigation: browser.location || (typeof window !== 'undefined' ? window.location : null),
        initializeWizards: initializeEmployeeWizards,
    });
}
