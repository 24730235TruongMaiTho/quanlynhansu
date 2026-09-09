import assert from 'node:assert/strict';
import fs from 'node:fs';
import test from 'node:test';

const root = new URL('../../', import.meta.url);
const read = (path) => fs.readFileSync(new URL(path, root), 'utf8');
const assertEditIcon = (source, control, path) => {
    if (/bi-pencil-square/iu.test(control)) {
        return;
    }

    assert.match(control, /\$\{iconEdit\(\)\}/u, `${path} edit control must render its edit icon`);
    assert.match(source, /function\s+iconEdit\s*\(\)[\s\S]*?bi-pencil-square/iu, `${path} iconEdit must return pencil-square`);
};

test('edit controls use the blue pencil-square contract across backend modules', () => {
    const views = [
        'resources/views/backend/partials/action-buttons.blade.php',
        'resources/views/backend/nhanvien/index.blade.php',
        'resources/views/backend/nhanvien/show.blade.php',
        'resources/views/backend/phongban/index.blade.php',
        'resources/views/backend/chucvu/index.blade.php',
        'resources/views/backend/hopdong/index.blade.php',
    ];

    for (const path of views) {
        const source = read(path);
        const editControls = source.match(/<(?:a|button)\b[^>]*(?:edit|Sửa|Chỉnh sửa)[^>]*>[\s\S]*?<\/(?:a|button)>/giu) || [];
        assert.ok(editControls.length > 0, `${path} must contain an edit control`);
        for (const control of editControls) {
            assert.match(control, /btn-outline-primary/iu, `${path} edit control must be blue`);
            assertEditIcon(source, control, path);
        }
    }

    const generatedEditControls = [
        {
            path: 'resources/js/frontend/luong/luong.js',
            marker: 'data-salary-action="edit"',
        },
        {
            path: 'resources/js/frontend/luong/luongHeSo.js',
            marker: 'data-coefficient-action="edit"',
        },
        {
            path: 'resources/js/frontend/nghiphep/create.js',
            marker: 'data-edit-leave-id=',
        },
        {
            path: 'resources/js/frontend/nghiphep/nghiphep.js',
            marker: 'data-leave-action="edit"',
        },
        {
            path: 'resources/js/frontend/vaitro/vaitro.js',
            marker: 'data-role-edit=',
        },
    ];

    for (const { path, marker } of generatedEditControls) {
        const source = read(path);
        const controls = (source.match(/<(?:a|button)\b[\s\S]*?<\/(?:a|button)>/giu) || [])
            .filter((control) => control.includes(marker));

        assert.ok(controls.length > 0, `${path} must contain a generated edit control`);
        for (const control of controls) {
            assert.match(control, /class="[^"]*\bbtn-outline-primary\b[^"]*"/iu, `${path} edit control must be blue`);
            assertEditIcon(source, control, path);
        }
    }
});

test('sidebar removes standalone create-position and coefficient links', () => {
    const source = read('resources/views/backend/layouts/sidebar.blade.php');

    assert.doesNotMatch(source, /Thêm chức vụ/);
    assert.doesNotMatch(source, /Danh sách hệ số lương/);
    assert.match(source, /\$canSeeSalary\s*=\s*app/);
    assert.doesNotMatch(source, /\$canSeeSalaryCoefficients/);
});

test('attendance and leave employee search fields do not render magnifier adornments', () => {
    const attendance = read('resources/views/backend/chamcong/index.blade.php');
    const leave = read('resources/views/backend/nghiphep/index.blade.php');
    const approval = read('resources/views/backend/nghiphep/duyet-nghi-phep.blade.php');

    for (const source of [attendance, leave]) {
        assert.doesNotMatch(source, /<div\b[^>]*class="[^"]*\binput-group\b[^"]*"[^>]*>[\s\S]*?id="search-field"/);
    }
    assert.doesNotMatch(approval, /<div\b[^>]*class="[^"]*\binput-group\b[^"]*"[^>]*>[\s\S]*?id="leave-filter-keyword"/);
});

test('salary create header uses the primary action color', () => {
    const source = read('resources/views/backend/luong/index.blade.php');
    const start = source.indexOf('id="create-salary-btn"');
    assert.notEqual(start, -1);
    const control = source.slice(Math.max(0, start - 250), start + 500);
    assert.match(control, /btn-primary/);
    assert.doesNotMatch(control, /btn-success/);
});

test('salary coefficient and shared popup icon-text controls use standard spacing', () => {
    const controls = [
        ['resources/views/backend/luong/index.blade.php', 'add-coefficient-btn'],
        ['resources/views/backend/luong/index.blade.php', 'salary-modal-cancel'],
        ['resources/views/backend/luong/index.blade.php', 'salary-modal-submit'],
        ['resources/views/backend/luong/index.blade.php', 'coefficient-modal-cancel'],
        ['resources/views/backend/luong/index.blade.php', 'coefficient-modal-submit'],
        ['resources/views/backend/nghiphep/index.blade.php', 'leave-modal-cancel'],
        ['resources/views/backend/nghiphep/index.blade.php', 'leave-modal-submit'],
        ['resources/views/backend/chamcong/index.blade.php', 'attendance-import-cancel'],
        ['resources/views/backend/chamcong/index.blade.php', 'attendance-import-remove-file'],
        ['resources/views/backend/chamcong/index.blade.php', 'attendance-import-submit'],
        ['resources/views/backend/chamcong/index.blade.php', 'attendance-export-cancel'],
        ['resources/views/backend/chamcong/index.blade.php', 'attendance-export-submit'],
    ];

    for (const [path, id] of controls) {
        const source = read(path);
        const markerIndex = source.indexOf(`id="${id}"`);
        assert.notEqual(markerIndex, -1, `${path} must contain ${id}`);
        const buttonStart = source.lastIndexOf('<button', markerIndex);
        const openingTag = source.slice(buttonStart, source.indexOf('>', markerIndex) + 1);
        assert.match(openingTag, /d-inline-flex/iu, `${path} ${id} must use inline-flex`);
        assert.match(openingTag, /align-items-center/iu, `${path} ${id} must align its icon and label`);
        assert.match(openingTag, /gap-2/iu, `${path} ${id} must space its icon and label`);
    }
});

test('salary add and save actions use the primary action color', () => {
    const source = read('resources/views/backend/luong/index.blade.php');

    for (const id of ['add-coefficient-btn', 'salary-modal-submit', 'coefficient-modal-submit']) {
        const markerIndex = source.indexOf(`id="${id}"`);
        assert.notEqual(markerIndex, -1, `salary view must contain ${id}`);
        const buttonStart = source.lastIndexOf('<button', markerIndex);
        const openingTag = source.slice(buttonStart, source.indexOf('>', markerIndex) + 1);
        assert.match(openingTag, /btn-primary/iu, `${id} must use the primary action color`);
        assert.doesNotMatch(openingTag, /btn-success/iu, `${id} must not use the success action color`);
    }
});

test('shared edit-modal shells omit popup recovery controls while keeping close actions', () => {
    for (const [path, closeMarker, forbidden] of [
        ['resources/views/backend/partials/simple-edit-modal.blade.php', 'data-edit-modal-close', ['data-edit-modal-recovery', 'data-edit-modal-fallback', 'data-edit-modal-retry', 'Mở trang đầy đủ', 'Thử lại']],
        ['resources/views/backend/nhanvien/partials/edit-modal.blade.php', 'data-employee-edit-close', ['data-employee-edit-recovery', 'data-employee-edit-fallback', 'data-employee-edit-retry', 'Mở trang đầy đủ', 'Thử lại']],
    ]) {
        const source = read(path);
        assert.match(source, new RegExp(closeMarker));
        for (const marker of forbidden) {
            assert.doesNotMatch(source, new RegExp(marker.replace(/[.*+?^${}()|[\]\\]/g, '\\$&'), 'u'), `${path} must omit ${marker}`);
        }
    }
});

test('employee and position form icon-text controls use standard spacing', () => {
    for (const [path, markers] of [
        ['resources/views/backend/chucvu/partials/create-form.blade.php', ['data-submit-edit']],
        ['resources/views/backend/nhanvien/partials/create-form.blade.php', ['data-wizard-next', 'data-wizard-previous', 'data-submit-employee']],
        ['resources/views/backend/nhanvien/partials/edit-form.blade.php', ['data-wizard-next', 'data-wizard-previous', 'data-submit-employee']],
    ]) {
        const source = read(path);
        for (const marker of markers) {
            const markerIndex = source.indexOf(marker);
            assert.notEqual(markerIndex, -1, `${path} must contain ${marker}`);
            const controlStart = Math.max(source.lastIndexOf('<button', markerIndex), source.lastIndexOf('<a', markerIndex));
            const openingTag = source.slice(controlStart, source.indexOf('>', markerIndex) + 1);
            assert.match(openingTag, /d-inline-flex/iu, `${path} ${marker} must use inline-flex`);
            assert.match(openingTag, /align-items-center/iu, `${path} ${marker} must align its icon and label`);
            assert.match(openingTag, /gap-2/iu, `${path} ${marker} must space its icon and label`);
        }
    }
});
