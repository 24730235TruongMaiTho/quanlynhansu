import assert from 'node:assert/strict';
import fs from 'node:fs';
import path from 'node:path';
import vm from 'node:vm';
import test from 'node:test';
import { fileURLToPath } from 'node:url';

const root = fileURLToPath(new URL('../../../', import.meta.url));
const source = fs.readFileSync(
    path.join(root, 'public/backend/js/sidebar-state.js'),
    'utf8',
);

test('fresh active sidebar group resolves its direct submenu in a fake DOM', () => {
    const submenu = { id: 'leave-submenu' };
    const activeGroup = {
        matches: () => false,
        querySelector: (selector) => selector === ':scope > .sub-menu' ? submenu : null,
    };
    const documentRef = {
        querySelector: (selector) => {
            assert.equal(
                selector,
                '[data-route-active="true"], [data-route-active="1"]',
            );
            return activeGroup;
        },
    };
    const context = { window: {} };

    vm.runInNewContext(source, context);

    assert.equal(
        context.window.qlnsSidebarState.findActiveSubmenu(documentRef),
        submenu,
    );
});

test('sidebar active submenu resolver fails closed without an active group', () => {
    const context = { window: {} };
    vm.runInNewContext(source, context);

    assert.equal(
        context.window.qlnsSidebarState.findActiveSubmenu({ querySelector: () => null }),
        null,
    );
});

test('sidebar markup marks server-rendered active submenu as ready for first paint', () => {
    const sidebar = fs.readFileSync(
        path.join(root, 'resources/views/backend/layouts/sidebar.blade.php'),
        'utf8',
    );

    assert.match(sidebar, /sub-menu \{\{ \$leaveGroupActive \? 'open' : '' \}\}[^>]*data-submenu-ready="initial"/);
    assert.match(sidebar, /data-route-active="\{\{ \$leaveGroupActive \? 'true' : 'false' \}\}"/);
    assert.doesNotMatch(sidebar, /route\('backend\.nghiphep\.duyet-nghi-phep'\)/);
});
