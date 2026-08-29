const LOAD_ERROR_MESSAGE = 'Không tải được biểu mẫu chỉnh sửa. Bạn có thể mở trang đầy đủ để tiếp tục.';
const UPDATE_ERROR_MESSAGE = 'Không thể cập nhật lúc này. Vui lòng thử lại sau.';
const FORM_ERROR_FALLBACK = 'Không thể cập nhật. Vui lòng kiểm tra lại thông tin hoặc thử lại sau.';

function setHidden(element, hidden) {
    if (element) {
        element.hidden = hidden;
    }
}

function messagesFor(value) {
    if (Array.isArray(value)) {
        return value.filter((message) => typeof message === 'string' && message.trim() !== '');
    }

    return typeof value === 'string' && value.trim() !== '' ? [value] : [];
}

function safeMessage(...candidates) {
    const message = candidates
        .flatMap((candidate) => messagesFor(candidate))
        .find((candidate) => candidate.trim() !== '');

    return !message || /sqlstate|exception|stack trace|in file/i.test(message)
        ? FORM_ERROR_FALLBACK
        : message;
}

function fieldFor(form, name) {
    const named = form?.elements?.namedItem?.(name);
    if (named && typeof named.focus === 'function') {
        return named;
    }

    const escapedName = String(name).replace(/(["\\])/g, '\\$1');
    return form?.querySelector?.('[name="' + escapedName + '"]') ?? null;
}

function clearValidationErrors(form) {
    form?.querySelectorAll?.('.is-invalid').forEach((field) => {
        field.classList.remove('is-invalid');
        field.removeAttribute?.('aria-invalid');
    });
    form?.querySelectorAll?.('[data-edit-modal-field-error]').forEach((error) => {
        error.remove?.();
        error.textContent = '';
    });
    const formError = form?.querySelector?.('[data-modal-form-error]');
    if (formError) {
        formError.hidden = true;
        formError.textContent = '';
    }
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
        let error = form.querySelector?.('[data-edit-modal-field-error="' + name + '"]');
        if (!error && form.ownerDocument?.createElement) {
            error = form.ownerDocument.createElement('div');
            error.className = 'invalid-feedback';
            error.dataset.editModalFieldError = name;
            error.id = 'edit-modal-' + name + '-error';
            field.parentElement?.append(error);
        }
        if (error) {
            error.textContent = firstMessage;
            error.setAttribute?.('role', 'alert');
            const describedBy = (field.getAttribute?.('aria-describedby') || '')
                .split(/\s+/)
                .filter(Boolean)
                .filter((id) => id !== error.id);
            describedBy.push(error.id || 'edit-modal-' + name + '-error');
            field.setAttribute?.('aria-describedby', describedBy.join(' '));
        }
        firstField ??= field;
    });

    if (!firstField) {
        const formError = form?.querySelector?.('[data-modal-form-error]');
        if (formError) {
            formError.textContent = safeMessage(formMessages, fallbackMessage);
            formError.hidden = false;
            formError.setAttribute?.('role', 'alert');
        }
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

export function createSimpleEditModalController(ui, dependencies = {}) {
    const fetchImpl = dependencies.fetch
        || (typeof fetch === 'function' ? fetch.bind(globalThis) : null);
    const navigation = dependencies.navigation
        || (typeof window !== 'undefined' ? window.location : null);
    const createFormData = dependencies.createFormData
        || ((form) => new FormData(form));

    let opener = null;
    let activeUrl = null;
    let loadingState = null;
    let loadGeneration = 0;
    let submitting = false;

    const resetRecovery = () => {
        setHidden(ui.error, true);
        setHidden(ui.fallback, true);
        setHidden(ui.retry, true);
        setHidden(ui.recovery, true);
        if (ui.error) ui.error.textContent = '';
    };

    const showRecovery = (message, allowRetry = true) => {
        if (ui.error) ui.error.textContent = message;
        setHidden(ui.error, false);
        setHidden(ui.fallback, false);
        setHidden(ui.retry, !allowRetry);
        setHidden(ui.recovery, false);
    };

    const setLoading = (loading) => {
        setHidden(ui.loading, !loading);
        ui.dialog?.setAttribute?.('aria-busy', loading ? 'true' : 'false');
    };

    const setCloseDisabled = (disabled) => {
        if (!ui.close) return;
        ui.close.disabled = disabled;
        ui.close.setAttribute?.('aria-disabled', disabled ? 'true' : 'false');
    };

    const restoreFocus = () => {
        opener?.focus?.();
        opener = null;
    };

    const close = (force = false) => {
        // Submit đang chờ phải giữ nguyên dialog để response không tác động hàng khác.
        if (submitting && !force) return false;

        if (typeof ui.dialog?.close === 'function' && ui.dialog.open) {
            ui.dialog.close();
        } else if (ui.dialog) {
            ui.dialog.hidden = true;
            ui.dialog.open = false;
        }

        loadGeneration += 1;
        activeUrl = null;
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
            showRecovery(LOAD_ERROR_MESSAGE, false);
            return;
        }
        if (loadingState?.url === activeUrl) return loadingState.promise;

        const generation = ++loadGeneration;
        setLoading(true);
        resetRecovery();
        if (ui.content) ui.content.innerHTML = '';

        const promise = (async () => {
            try {
                const response = await fetchImpl(activeUrl, {
                    headers: {
                        Accept: 'text/html',
                        'X-Requested-With': 'XMLHttpRequest',
                        'X-Edit-Modal': '1',
                    },
                    credentials: 'same-origin',
                });
                const html = await response.text();
                if (!response.ok || !html.includes('data-simple-edit-form') || generation !== loadGeneration) {
                    throw new Error('simple-edit-modal-load-failed');
                }
                ui.content.innerHTML = html;
                ui.content.querySelector?.('[data-simple-edit-form]')?.querySelector?.('input, select, textarea, button')?.focus?.();
                setLoading(false);
            } catch {
                if (generation === loadGeneration) {
                    setLoading(false);
                    showRecovery(LOAD_ERROR_MESSAGE);
                }
            } finally {
                if (loadingState?.promise === promise) loadingState = null;
            }
        })();
        loadingState = { url: activeUrl, promise };
        return promise;
    };

    const finishSubmit = (form) => {
        submitting = false;
        setCloseDisabled(false);
        form?.removeAttribute?.('aria-busy');
        form?.querySelectorAll?.('[data-submit-edit]').forEach((button) => {
            button.disabled = false;
            button.setAttribute?.('aria-disabled', 'false');
            if (button.dataset.previousText !== undefined) {
                button.textContent = button.dataset.previousText;
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
            showRecovery(UPDATE_ERROR_MESSAGE, false);
            return null;
        }
        event.preventDefault?.();
        submitting = true;
        setCloseDisabled(true);
        clearValidationErrors(form);
        form.setAttribute?.('aria-busy', 'true');
        form.querySelectorAll?.('[data-submit-edit]').forEach((button) => {
            button.disabled = true;
            button.setAttribute?.('aria-disabled', 'true');
            button.dataset.previousText ??= button.textContent;
            button.textContent = button.dataset.submittingText || 'Đang lưu…';
        });

        try {
            const response = await fetchImpl(action, {
                method: formMethod(form),
                body: createFormData(form),
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
                showRecovery(UPDATE_ERROR_MESSAGE, false);
                finishSubmit(form);
                return payload;
            }
            close(true);
            navigation?.reload?.();
            return payload;
        } catch {
            showRecovery(UPDATE_ERROR_MESSAGE, false);
            finishSubmit(form);
            return null;
        }
    };

    ui.retry?.addEventListener?.('click', (event) => {
        event.preventDefault?.();
        load();
    });
    ui.close?.addEventListener?.('click', (event) => {
        event.preventDefault?.();
        close();
    });
    ui.dialog?.addEventListener?.('cancel', (event) => {
        event.preventDefault?.();
        if (!submitting) close();
    });
    ui.dialog?.addEventListener?.('keydown', (event) => {
        if (event.key !== 'Escape') return;
        event.preventDefault?.();
        if (!submitting) close();
    });
    ui.content?.addEventListener?.('submit', (event) => {
        submit(event.target, event);
    });

    async function open(nextOpener, url) {
        if (!url || submitting) return null;
        opener = nextOpener;
        activeUrl = url;
        if (ui.fallback) ui.fallback.href = url;
        resetRecovery();
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

    return { open, close, load, submit, handleKeydown: (event) => {
        if (event.key !== 'Escape') return;
        event.preventDefault?.();
        if (!submitting) close();
    } };
}

export function initializeSimpleEditModal(
    root = typeof document !== 'undefined' ? document : null,
    browser = typeof window !== 'undefined' ? window : {},
) {
    const dialog = root?.querySelector?.('[data-simple-edit-modal]');
    if (!dialog) return null;

    return createSimpleEditModalController({
        dialog,
        content: dialog.querySelector('[data-edit-modal-content]'),
        loading: dialog.querySelector('[data-edit-modal-loading]'),
        error: dialog.querySelector('[data-edit-modal-error]'),
        recovery: dialog.querySelector('[data-edit-modal-recovery]'),
        fallback: dialog.querySelector('[data-edit-modal-fallback]'),
        retry: dialog.querySelector('[data-edit-modal-retry]'),
        close: dialog.querySelector('[data-edit-modal-close]'),
    }, {
        fetch: typeof browser.fetch === 'function' ? browser.fetch.bind(browser) : undefined,
        navigation: browser.location || (typeof window !== 'undefined' ? window.location : null),
    });
}
