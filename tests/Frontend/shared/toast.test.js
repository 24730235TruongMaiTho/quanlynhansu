import assert from 'node:assert/strict';
import test from 'node:test';

import { showToast } from '../../../resources/js/frontend/shared/toast.js';

class FakeClassList {
    constructor() {
        this.values = new Set();
    }

    add(...values) {
        values.forEach((value) => this.values.add(value));
    }

    remove(...values) {
        values.forEach((value) => this.values.delete(value));
    }

    contains(value) {
        return this.values.has(value);
    }
}

class FakeElement {
    constructor(tagName, ownerDocument) {
        this.tagName = tagName.toUpperCase();
        this.ownerDocument = ownerDocument;
        this.children = [];
        this.parentElement = null;
        this.attributes = new Map();
        this.classList = new FakeClassList();
        this.dataset = {};
        this.listeners = new Map();
        this.innerHTMLWrites = 0;
        this.innerHTMLValue = '';
        this.textContent = '';

        Object.defineProperty(this, 'innerHTML', {
            get: () => this.innerHTMLValue,
            set: (value) => {
                this.innerHTMLWrites += 1;
                this.innerHTMLValue = String(value);
            },
        });
    }

    set className(value) {
        this.classList = new FakeClassList();
        String(value)
            .split(/\s+/)
            .filter(Boolean)
            .forEach((className) => this.classList.add(className));
    }

    get className() {
        return [...this.classList.values].join(' ');
    }

    setAttribute(name, value) {
        const stringValue = String(value);
        this.attributes.set(name, stringValue);
        if (name.startsWith('data-')) {
            const key = name
                .slice(5)
                .replace(/-([a-z])/g, (_, letter) => letter.toUpperCase());
            this.dataset[key] = stringValue;
        }
    }

    getAttribute(name) {
        return this.attributes.get(name) ?? null;
    }

    removeAttribute(name) {
        this.attributes.delete(name);
    }

    append(...children) {
        children.forEach((child) => this.appendChild(child));
    }

    appendChild(child) {
        child.parentElement = this;
        this.children.push(child);
        return child;
    }

    remove() {
        if (!this.parentElement) return;
        this.parentElement.children = this.parentElement.children.filter(
            (child) => child !== this,
        );
        this.parentElement = null;
    }

    addEventListener(type, listener) {
        const listeners = this.listeners.get(type) ?? [];
        listeners.push(listener);
        this.listeners.set(type, listeners);
    }

    dispatchEvent(event) {
        (this.listeners.get(event.type) ?? []).forEach((listener) => listener(event));
    }

    matches(selector) {
        const attributeMatch = selector.match(/^\[([^=\]]+)(?:=["']?([^\]"']+)["']?)?\]$/);
        if (attributeMatch) {
            const [, name, expected] = attributeMatch;
            const actual = this.getAttribute(name);
            return actual !== null && (expected === undefined || actual === expected);
        }

        if (selector.startsWith('.')) {
            return this.classList.contains(selector.slice(1));
        }

        return this.tagName.toLowerCase() === selector.toLowerCase();
    }

    querySelector(selector) {
        return this.querySelectorAll(selector)[0] ?? null;
    }

    querySelectorAll(selector) {
        const matches = [];
        const visit = (element) => {
            element.children.forEach((child) => {
                if (child.matches(selector)) matches.push(child);
                visit(child);
            });
        };
        visit(this);
        return matches;
    }
}

class FakeDocument {
    constructor() {
        this.body = new FakeElement('body', this);
    }

    createElement(tagName) {
        return new FakeElement(tagName, this);
    }

    querySelector(selector) {
        return this.body.querySelector(selector);
    }
}

function withFakeDocument(callback) {
    const previousDocument = globalThis.document;
    const previousBootstrap = globalThis.bootstrap;
    const document = new FakeDocument();
    globalThis.document = document;
    delete globalThis.bootstrap;

    return Promise.resolve()
        .then(() => callback(document))
        .finally(() => {
            globalThis.document = previousDocument;
            if (previousBootstrap === undefined) {
                delete globalThis.bootstrap;
            } else {
                globalThis.bootstrap = previousBootstrap;
            }
        });
}

test('creates an accessible danger toast with safe text content', async () => {
    await withFakeDocument((document) => {
        const message = '<img src=x onerror=alert(1)> Báo cáo lỗi';
        const toast = showToast(message, {
            variant: 'danger',
            title: 'Lỗi xuất báo cáo',
            delay: 0,
        });

        assert.ok(toast);
        assert.equal(document.body.querySelector('[data-qlns-toast-container]')?.getAttribute('aria-live'), 'polite');
        assert.equal(toast.getAttribute('role'), 'alert');
        assert.equal(toast.getAttribute('aria-live'), 'assertive');
        assert.equal(toast.getAttribute('aria-atomic'), 'true');
        assert.equal(toast.querySelector('[data-toast-message]')?.textContent, message);
        assert.equal(toast.querySelector('[data-toast-title]')?.textContent, 'Lỗi xuất báo cáo');
        assert.equal(toast.querySelector('[data-toast-icon]')?.getAttribute('aria-hidden'), 'true');
        assert.equal(toast.querySelector('[data-toast-label]'), null);
        assert.equal(toast.querySelector('button')?.getAttribute('aria-label'), 'Đóng thông báo');
        assert.equal(toast.innerHTMLWrites, 0);
    });
});

test('uses Bootstrap Toast when available and keeps non-error messages polite', async () => {
    await withFakeDocument((document) => {
        let showCalls = 0;
        let receivedElement = null;
        let receivedOptions = null;
        globalThis.bootstrap = {
            Toast: {
                getOrCreateInstance(element, options) {
                    receivedElement = element;
                    receivedOptions = options;
                    return {
                        show() {
                            showCalls += 1;
                        },
                    };
                },
            },
        };

        const toast = showToast('Đã lưu thay đổi.', {
            variant: 'success',
            delay: 3500,
        });

        assert.equal(showCalls, 1);
        assert.equal(receivedElement, toast);
        assert.deepEqual(receivedOptions, { autohide: true, delay: 3500 });
        assert.equal(document.body.querySelector('[data-qlns-toast-container]')?.getAttribute('aria-live'), 'polite');
        assert.equal(toast.getAttribute('role'), 'status');
        assert.equal(toast.getAttribute('aria-live'), 'polite');
        assert.equal(toast.querySelector('[data-toast-icon]')?.getAttribute('aria-hidden'), 'true');
        assert.equal(toast.querySelector('[data-toast-label]'), null);
        assert.equal(toast.querySelector('[data-toast-title]')?.textContent, 'Thành công');
    });
});

test('maps warning and info variants to distinct semantic icons and labels', async () => {
    await withFakeDocument(() => {
        globalThis.bootstrap = {
            Toast: {
                getOrCreateInstance() {
                    return { show() {} };
                },
            },
        };

        const warning = showToast('Kiểm tra lại dữ liệu.', { variant: 'warning' });
        const info = showToast('Đang đồng bộ dữ liệu.', { variant: 'info' });

        assert.equal(warning.getAttribute('data-toast-variant'), 'warning');
        assert.equal(warning.getAttribute('role'), 'status');
        assert.equal(warning.getAttribute('aria-live'), 'polite');
        assert.equal(warning.querySelector('[data-toast-icon]')?.classList.contains('bi-exclamation-circle-fill'), true);
        assert.equal(warning.querySelector('[data-toast-label]'), null);
        assert.equal(warning.querySelector('[data-toast-title]')?.textContent, 'Cảnh báo');
        assert.equal(info.getAttribute('data-toast-variant'), 'info');
        assert.equal(info.querySelector('[data-toast-icon]')?.classList.contains('bi-info-circle-fill'), true);
        assert.equal(info.querySelector('[data-toast-label]'), null);
        assert.equal(info.querySelector('[data-toast-title]')?.textContent, 'Thông tin');
    });
});

test('fallback removes the toast after its delay when Bootstrap JS is unavailable', async () => {
    await withFakeDocument(async (document) => {
        const toast = showToast('Không thể tải dữ liệu.', {
            variant: 'info',
            delay: 5,
        });

        assert.equal(document.body.querySelector('[data-qlns-toast-container]')?.children.length, 1);
        await new Promise((resolve) => setTimeout(resolve, 20));
        assert.equal(toast.parentElement, null);
        assert.equal(document.body.querySelector('[data-qlns-toast-container]')?.children.length ?? 0, 0);
    });
});
