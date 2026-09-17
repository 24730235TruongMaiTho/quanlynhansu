import assert from 'node:assert/strict';
import fs from 'node:fs';
import test from 'node:test';
import path from 'node:path';
import { fileURLToPath } from 'node:url';

const root = fileURLToPath(new URL('../../../', import.meta.url));
const read = (relativePath) => fs.readFileSync(path.join(root, relativePath), 'utf8');

function filesUnder(relativePath, extension) {
    const directory = path.join(root, relativePath);
    const result = [];
    const visit = (current) => {
        fs.readdirSync(current, { withFileTypes: true }).forEach((entry) => {
            const absolute = path.join(current, entry.name);
            if (entry.isDirectory()) visit(absolute);
            else if (entry.name.endsWith(extension)) result.push(absolute);
        });
    };
    visit(directory);
    return result;
}

function iconLabelControls(source) {
    return [...source.matchAll(/<(button|a)\b[\s\S]*?<\/\1>/giu)]
        .map((match) => match[0])
        .filter((control) => {
            const classAttribute = control.match(/class\s*=\s*["']([^"']*)["']/iu)?.[1] || '';
            return classAttribute.split(/\s+/u).includes('btn');
        })
        .filter((control) => {
            const classAttribute = control.match(/class\s*=\s*["']([^"']*)["']/iu)?.[1] || '';
            const classes = classAttribute.split(/\s+/u);
            return !classes.includes('btn-icon-action') && !classes.includes('btn-close');
        })
        // Generated controls use either literal Bootstrap classes or shared
        // icon helper calls (iconEdit(), iconCreate(), ...).
        .filter((control) => /\bbi-[\w-]+\b|\bicon[A-Z][\w]*\s*\(/u.test(control))
        .filter((control) => /data-button-label|>\s*(?:[^<{]|\{\{)[^<]*\S/iu.test(control));
}

test('all management tables expose the shared sort icon/state contract', () => {
    const views = [
        'resources/views/backend/nhanvien/index.blade.php',
        'resources/views/backend/phongban/index.blade.php',
        'resources/views/backend/chucvu/index.blade.php',
        'resources/views/backend/hopdong/index.blade.php',
        'resources/views/backend/vaitro/index.blade.php',
        'resources/views/backend/taikhoan/index.blade.php',
        'resources/views/backend/chamcong/index.blade.php',
        'resources/views/backend/nghiphep/index.blade.php',
        'resources/views/backend/luong/index.blade.php',
    ];

    for (const viewPath of views) {
        const view = read(viewPath);
        assert.match(view, /table-sort-control|x-backend\.table-sort|data-[a-z-]+-sort/iu, viewPath);
        assert.doesNotMatch(view, /[↑↓↕]/u, viewPath);
    }
});

test('every backend icon plus visible-label button uses the shared contract', () => {
    const views = filesUnder('resources/views/backend', '.blade.php');
    assert.ok(views.length > 0);

    for (const absolutePath of views) {
        const source = fs.readFileSync(absolutePath, 'utf8');
        assert.doesNotMatch(source, /[↑↓↕]/u, path.relative(root, absolutePath));
        for (const control of iconLabelControls(source)) {
            assert.match(control, /\bbtn-icon-text\b/iu, path.relative(root, absolutePath));
        }
    }
});

test('generated icon plus visible-label controls use the shared contract', () => {
    const scripts = filesUnder('resources/js/frontend', '.js');

    for (const absolutePath of scripts) {
        const source = fs.readFileSync(absolutePath, 'utf8');
        const controls = iconLabelControls(source);
        for (const control of controls) {
            assert.match(control, /\bbtn-icon-text\b/iu, path.relative(root, absolutePath));
        }
    }
});

test('icon and label buttons use one semantic class and nested label', () => {
    const views = [
        read('resources/views/backend/profile/edit.blade.php'),
        read('resources/views/backend/profile/password.blade.php'),
        read('resources/views/backend/chamcong/index.blade.php'),
        read('resources/views/backend/vaitro/index.blade.php'),
    ].join('\n');

    assert.match(views, /btn-icon-text/iu);
    assert.match(views, /data-button-label/iu);
});

test('role sorting synchronizes bootstrap icon and aria state', () => {
    const source = read('resources/js/frontend/vaitro/vaitro.js');

    assert.match(source, /bi-arrow-down-up/iu);
    assert.match(source, /bi-arrow-up-short/iu);
    assert.match(source, /bi-arrow-down-short/iu);
    assert.match(source, /aria-sort/iu);
});

test('all client sort controls expose valid ARIA sort values', () => {
    const sources = [
        read('resources/js/frontend/chamcong/chamcong.js'),
        read('resources/js/frontend/nghiphep/nghiphep.js'),
        read('resources/js/frontend/luong/luong.js'),
        read('resources/js/frontend/luong/luongHeSo.js'),
        read('resources/js/frontend/vaitro/vaitro.js'),
    ];

    for (const source of sources) {
        assert.match(source, /ascending/iu);
        assert.match(source, /descending/iu);
        assert.doesNotMatch(source, /setAttribute\(['"]aria-sort['"],\s*active\s*\?\s*(?:direction|state\.(?:direction|coefficientDirection))/iu);
    }
});

test('attendance sortable headers stay within the server allowlist', () => {
    const view = read('resources/views/backend/chamcong/index.blade.php');
    const request = read('app/Http/Requests/ListChamCongEmployeeRequest.php');
    const headerColumns = [...view.matchAll(/data-attendance-employee-sort="\{\{\s*\$sortColumn\s*\}\}"/gu)];

    assert.ok(headerColumns.length > 0);
    assert.match(view, /'tong_gio_lam'\s*=>\s*'Tổng giờ'/u);
    assert.match(request, /'tong_gio_lam'/u);
});

test('attendance total-hours cell belongs only to the employee table', () => {
    const source = read('resources/js/frontend/chamcong/chamcong.js');
    const employeeRows = source.slice(source.indexOf('function renderEmployeeRows'), source.indexOf('async function loadEmployees'));
    const attendanceRows = source.slice(source.indexOf('function renderAttendanceRows'), source.indexOf('function updateSummary'));
    const totalCell = /<td[^>]*>\$\{number\(item\.tong_gio_lam, 1\)\}<\/td>/u;

    assert.match(employeeRows, totalCell);
    assert.doesNotMatch(attendanceRows, totalCell);
    assert.match(attendanceRows, /colspan="10"/u);
});
