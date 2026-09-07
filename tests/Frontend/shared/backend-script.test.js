import test from 'node:test';
import assert from 'node:assert/strict';
import fs from 'node:fs';

const source = fs.readFileSync(
    new URL('../../../public/backend/js/script.js', import.meta.url),
    'utf8',
);
const styleSource = fs.readFileSync(
    new URL('../../../public/backend/css/style.css', import.meta.url),
    'utf8',
);

test('global backend script binds the optional search input safely', () => {
    assert.match(
        source,
        /if\s*\(searchInput\)\s*\{[\s\S]*searchInput\.addEventListener\(\s*['"]keyup['"]/
    );
});

test('global backend script exposes a safe display-date formatter for inline dashboard widgets', () => {
    assert.match(
        source,
        /formatDisplayDate\s*=\s*function\s*\(value\)[\s\S]*?getUTCFullYear\(\)/,
    );
});

test('dropdown triggers mirror open and close state through aria-expanded', () => {
    assert.match(
        source,
        /function\s+setDropdownExpanded\(dropdownId,\s*expanded\)/,
    );
    assert.match(source, /setDropdownExpanded\(dropdownId,\s*true\)/);
    assert.match(source, /setDropdownExpanded\(dropdownId,\s*false\)/);
    assert.ok(
        (source.match(/setDropdownExpanded\(d\.id,\s*false\)/g) || []).length >= 2,
        'closing another dropdown, clicking outside, and Escape must reset its trigger',
    );
});

test('sidebar persists the open group and scroll position per session', () => {
    assert.match(source, /qlns\.sidebar\.openGroup/);
    assert.match(source, /qlns\.sidebar\.scrollTop/);
    assert.match(source, /sessionStorage\.setItem/);
    assert.match(source, /sessionStorage\.getItem/);
});

test('sidebar clears the persisted group when a submenu closes or no group is open on pagehide', () => {
    assert.match(source, /function\s+removeSessionValue\(key\)/);
    assert.match(source, /sessionStorage\.removeItem/);
    assert.match(source, /removeSessionValue\(SIDEBAR_STORAGE\.openGroup\)/);
    assert.match(source, /saveSidebarState\(openGroup \? openGroup\.closest\('\[data-sidebar-group\]'\) : null\)/);
});

test('active route wins and submenu aria state stays synchronized', () => {
    assert.match(source, /qlnsSidebarState\?\.findActiveSubmenu\(document\)/);
    assert.match(source, /setAttribute\(['"]aria-expanded['"]/);
    assert.match(source, /requestAnimationFrame/);
    assert.match(source, /link\.setAttribute\(['"]aria-expanded['"]/);
    assert.match(source, /savedGroup\.querySelector\('\[data-toggle="submenu"\]'\)/);
});

test('server-active and session-restored submenus skip initialization animation', () => {
    assert.match(source, /setSubmenuState\(activeSubmenu,\s*true,\s*true\)/);
    assert.match(source, /setSubmenuState\(savedSubmenu,\s*true,\s*true\)/);
    assert.match(source, /submenuNoAnimation/);
    assert.match(styleSource, /data-submenu-no-animation/);
    assert.match(styleSource, /data-submenu-ready="initial"/);
});

test('manual submenu clicks use the animated path after the initial marker is cleared', () => {
    const stateStart = source.indexOf('function setSubmenuState');
    const stateEnd = source.indexOf('function setDropdownExpanded', stateStart);
    const toggleStart = source.indexOf('function toggleSubMenu');
    const toggleEnd = source.indexOf("document.querySelectorAll('[data-toggle=\"submenu\"]')", toggleStart);
    const stateBlock = source.slice(stateStart, stateEnd);
    const toggleBlock = source.slice(toggleStart, toggleEnd);

    assert.match(stateBlock, /dataset\.submenuReady === 'initial'/);
    assert.match(stateBlock, /subMenu\.dataset\.submenuReady = '1'/);
    assert.match(stateBlock, /delete subMenu\.dataset\.submenuNoAnimation/);
    assert.match(toggleBlock, /setSubmenuState\(subMenu, expanded\);/);
    assert.doesNotMatch(toggleBlock, /setSubmenuState\(subMenu, expanded, true\)/);
});

test('sidebar measures each submenu and animates without a fixed max-height cap', () => {
    assert.match(source, /scrollHeight/);
    assert.match(source, /--submenu-height/);
    assert.doesNotMatch(styleSource, /2000px/);
    assert.match(styleSource, /prefers-reduced-motion/);
});

test('sidebar opens submenu visibility immediately but delays hiding until collapse ends', () => {
    assert.match(
        styleSource,
        /\.sub-menu\[data-submenu-ready\][\s\S]*visibility\s+0s\s+linear\s+0\.3s;/,
    );
    assert.match(
        styleSource,
        /\.sub-menu\.open\[data-submenu-ready\]\s*\{[\s\S]*visibility\s+0s\s+linear\s+0s;/,
    );
});
