const TOAST_VARIANTS = Object.freeze({
    danger: Object.freeze({
        title: 'Có lỗi xảy ra',
        icon: 'bi-exclamation-triangle-fill',
        role: 'alert',
        live: 'assertive',
        delay: 7000,
    }),
    warning: Object.freeze({
        title: 'Cảnh báo',
        icon: 'bi-exclamation-circle-fill',
        role: 'status',
        live: 'polite',
        delay: 5000,
    }),
    info: Object.freeze({
        title: 'Thông tin',
        icon: 'bi-info-circle-fill',
        role: 'status',
        live: 'polite',
        delay: 5000,
    }),
    success: Object.freeze({
        title: 'Thành công',
        icon: 'bi-check-circle-fill',
        role: 'status',
        live: 'polite',
        delay: 3500,
    }),
});

const TOAST_CONTAINER_SELECTOR = '[data-qlns-toast-container]';

function appendChildren(parent, ...children) {
    children.forEach((child) => parent.appendChild(child));
}

function currentDocument() {
    return typeof globalThis.document !== 'undefined'
        && typeof globalThis.document.createElement === 'function'
        ? globalThis.document
        : null;
}

function normalizedVariant(variant) {
    return Object.hasOwn(TOAST_VARIANTS, variant)
        ? variant
        : 'info';
}

function normalizedDelay(delay, fallback) {
    const value = Number(delay);

    return Number.isFinite(value) && value >= 0
        ? value
        : fallback;
}

function getToastContainer(document) {
    const existing = document.querySelector?.(TOAST_CONTAINER_SELECTOR);
    if (existing) return existing;

    const root = document.body || document.documentElement;
    if (!root?.appendChild) return null;

    const container = document.createElement('div');
    container.className = 'toast-container position-fixed top-0 end-0 p-3 qlns-toast-container';
    container.setAttribute('data-qlns-toast-container', 'true');
    container.setAttribute('role', 'status');
    container.setAttribute('aria-live', 'polite');
    container.setAttribute('aria-atomic', 'true');
    root.appendChild(container);

    return container;
}

function removeToast(toast, container) {
    toast.classList?.remove('show');
    toast.remove?.();

    if (container?.children?.length === 0) {
        container.remove?.();
    }
}

function bootstrapToastFor(toast, delay) {
    const BootstrapToast = (globalThis.bootstrap || globalThis.window?.bootstrap)?.Toast;
    if (typeof BootstrapToast?.getOrCreateInstance !== 'function') {
        return null;
    }

    try {
        const instance = BootstrapToast.getOrCreateInstance(toast, {
            autohide: true,
            delay,
        });
        instance.show();
        return instance;
    } catch {
        return null;
    }
}

/**
 * Render a shared Bootstrap toast without interpolating message content into HTML.
 * @param {unknown} message
 * @param {{ variant?: 'danger'|'warning'|'info'|'success', title?: string, delay?: number }} options
 * @returns {HTMLElement|null}
 */
export function showToast(message, options = {}) {
    const document = currentDocument();
    if (!document) return null;

    const settings = options && typeof options === 'object'
        ? options
        : {};
    const variant = normalizedVariant(settings.variant);
    const config = TOAST_VARIANTS[variant];
    const rawMessage = String(message ?? '');
    const toastMessage = rawMessage.trim() ? rawMessage : config.title;
    const toastTitle = String(settings.title ?? '').trim() || config.title;
    const delay = normalizedDelay(settings.delay, config.delay);
    const container = getToastContainer(document);
    if (!container) return null;

    const toast = document.createElement('div');
    toast.className = 'toast qlns-toast';
    toast.setAttribute('role', config.role);
    toast.setAttribute('aria-live', config.live);
    toast.setAttribute('aria-atomic', 'true');
    toast.setAttribute('data-toast-variant', variant);
    toast.setAttribute('data-bs-autohide', 'true');
    toast.setAttribute('data-bs-delay', String(delay));

    const content = document.createElement('div');
    content.className = 'd-flex';

    const body = document.createElement('div');
    body.className = 'toast-body qlns-toast-body';

    const heading = document.createElement('div');
    heading.className = 'qlns-toast-heading';

    const icon = document.createElement('i');
    icon.className = `bi ${config.icon} qlns-toast-icon`;
    icon.setAttribute('aria-hidden', 'true');
    icon.setAttribute('data-toast-icon', 'true');

    const title = document.createElement('strong');
    title.className = 'qlns-toast-title';
    title.setAttribute('data-toast-title', 'true');
    title.textContent = toastTitle;

    const messageNode = document.createElement('div');
    messageNode.className = 'qlns-toast-message';
    messageNode.setAttribute('data-toast-message', 'true');
    messageNode.textContent = toastMessage;

    appendChildren(heading, icon, title);
    appendChildren(body, heading, messageNode);

    const close = document.createElement('button');
    close.type = 'button';
    close.className = 'btn-close me-2 m-auto';
    close.setAttribute('aria-label', 'Đóng thông báo');
    close.setAttribute('data-bs-dismiss', 'toast');

    appendChildren(content, body, close);
    toast.appendChild(content);
    container.appendChild(toast);

    let fallbackTimer = null;
    let bootstrapInstance = null;
    const cleanup = () => {
        if (fallbackTimer !== null) {
            globalThis.clearTimeout?.(fallbackTimer);
            fallbackTimer = null;
        }
        removeToast(toast, container);
    };

    toast.addEventListener?.('hidden.bs.toast', cleanup);
    close.addEventListener?.('click', () => {
        if (bootstrapInstance?.hide) {
            bootstrapInstance.hide();
            return;
        }
        cleanup();
    });

    bootstrapInstance = bootstrapToastFor(toast, delay);
    if (!bootstrapInstance) {
        toast.classList?.add('show');
        if (typeof globalThis.setTimeout === 'function') {
            fallbackTimer = globalThis.setTimeout(cleanup, delay);
        }
    }

    return toast;
}

export { TOAST_VARIANTS };
