import test from 'node:test';
import assert from 'node:assert/strict';
import fs from 'node:fs';

const read = (relativePath) => fs.readFileSync(
    new URL(`../../../${relativePath}`, import.meta.url),
    'utf8',
);

test('shared paginator renderer owns the role visual and accessibility contract', () => {
    const source = read('resources/js/frontend/shared/pagination.js');

    assert.match(source, /backend-pagination/);
    assert.match(source, /pagination pagination-sm mb-0/);
    assert.match(source, /page-link/);
    assert.match(source, /aria-label/);
    assert.match(source, /aria-current/);
    assert.match(source, /bi-chevron-left/);
    assert.match(source, /bi-chevron-right/);
    assert.match(source, /disabled/);
    assert.match(source, /showSinglePage/);
});

test('shared paginator output keeps current, disabled, labelled, and icon controls accessible', async () => {
    const { renderSharedPagination } = await import('../../../resources/js/frontend/shared/pagination.js');
    const makeNode = (tagName) => {
        const classes = new Set();
        const node = {
            tagName,
            attributes: {},
            children: [],
            dataset: {},
            disabled: false,
            textContent: '',
            className: '',
            classList: {
                add(...names) {
                    names.forEach((name) => classes.add(name));
                    node.className = [...classes].join(' ');
                },
            },
            setAttribute(name, value) {
                node.attributes[name] = String(value);
            },
            appendChild(child) {
                node.children.push(child);
                return child;
            },
            replaceChildren(...children) {
                node.children = children;
            },
        };
        return node;
    };
    const documentRef = { createElement: makeNode };
    const container = makeNode('nav');
    container.ownerDocument = documentRef;

    renderSharedPagination(container, { current_page: 2, last_page: 4 }, {
        document: documentRef,
        pageAttribute: 'rolePage',
    });

    assert.match(container.className, /backend-pagination/);
    const list = container.children[0];
    assert.match(list.className, /pagination pagination-sm mb-0/);
    const controls = list.children.flatMap((item) => item.children);
    const current = controls.find((control) => control.attributes['aria-current'] === 'page');
    const previous = controls.find((control) => control.attributes['aria-label'] === 'Trang trước');
    const next = controls.find((control) => control.attributes['aria-label'] === 'Trang sau');

    assert.equal(current.textContent, '2');
    assert.equal(previous.children[0].className, 'bi bi-chevron-left');
    assert.equal(next.children[0].className, 'bi bi-chevron-right');
    assert.equal(previous.attributes['aria-label'], 'Trang trước');
    assert.equal(next.attributes['aria-label'], 'Trang sau');
    assert.equal(previous.disabled, false);
    assert.equal(current.attributes['aria-current'], 'page');
});

test('single-page role metadata keeps the visible disabled navigation controls', async () => {
    const { renderSharedPagination } = await import('../../../resources/js/frontend/shared/pagination.js');
    const makeNode = (tagName) => {
        const node = {
            tagName,
            attributes: {},
            children: [],
            dataset: {},
            disabled: false,
            textContent: '',
            className: '',
            classList: { add() {} },
            setAttribute(name, value) {
                node.attributes[name] = String(value);
            },
            appendChild(child) {
                node.children.push(child);
                return child;
            },
            replaceChildren(...children) {
                node.children = children;
            },
        };
        return node;
    };
    const documentRef = { createElement: makeNode };
    const container = makeNode('nav');
    container.ownerDocument = documentRef;

    renderSharedPagination(container, {
        current_page: 1,
        last_page: 1,
        total: 6,
    }, {
        document: documentRef,
        pageAttribute: 'rolePage',
        showSinglePage: true,
    });

    const controls = container.children[0].children.flatMap((item) => item.children);
    assert.equal(controls.length, 3);
    assert.equal(controls[0].attributes['aria-label'], 'Trang trước');
    assert.equal(controls[0].disabled, true);
    assert.equal(controls[1].attributes['aria-current'], 'page');
    assert.equal(controls[1].textContent, '1');
    assert.equal(controls[2].attributes['aria-label'], 'Trang sau');
    assert.equal(controls[2].disabled, true);
});

test('every dynamic paginated module delegates its controls to the shared renderer', () => {
    const modules = [
        ['resources/js/frontend/chamcong/chamcong.js', 'paginationType'],
        ['resources/js/frontend/nghiphep/nghiphep.js', 'leavePage'],
        ['resources/js/frontend/nghiphep/duyet-nghi-phep.js', 'page'],
        ['resources/js/frontend/luong/luong.js', 'page'],
        ['resources/js/frontend/luong/luongHeSo.js', 'coefficientPage'],
        ['resources/js/frontend/vaitro/vaitro.js', 'rolePage'],
    ];

    for (const [path, pageAttribute] of modules) {
        const source = read(path);
        assert.match(source, /import\s*\{\s*renderSharedPagination\s*\}\s*from\s*['"]\.\.\/shared\/pagination\.js['"]/,
            `${path} must import the shared pagination renderer`);
        assert.match(source, /renderSharedPagination/,
            `${path} must use the shared pagination renderer`);
        assert.match(source, new RegExp(`pageAttribute\\s*:\\s*['"]${pageAttribute}['"]`),
            `${path} must preserve its existing page data attribute`);
        assert.doesNotMatch(source, /btn-group(?:\s+btn-group-sm)?/,
            `${path} must not render legacy button-group pagination`);
    }
});
