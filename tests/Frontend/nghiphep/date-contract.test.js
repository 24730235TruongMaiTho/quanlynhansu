import test from 'node:test';
import assert from 'node:assert/strict';
import fs from 'node:fs';

const root = new URL('../../../', import.meta.url);
const read = (path) => fs.readFileSync(new URL(path, root), 'utf8');

const blades = [
    'resources/views/backend/nghiphep/index.blade.php',
    'resources/views/backend/nghiphep/create.blade.php',
    'resources/views/backend/nghiphep/duyet-nghi-phep.blade.php',
];
const scripts = [
    'resources/js/frontend/nghiphep/nghiphep.js',
    'resources/js/frontend/nghiphep/create.js',
    'resources/js/frontend/nghiphep/duyet-nghi-phep.js',
];

test('all leave date inputs use the shared text/display-date contract', () => {
    const source = blades.map(read).join('\n');

    assert.doesNotMatch(source, /type="date"/);
    assert.ok((source.match(/placeholder="dd\/mm\/yyyy"/g) || []).length >= 6);
    assert.ok((source.match(/inputmode="numeric"/g) || []).length >= 6);
    assert.ok((source.match(/maxlength="10"/g) || []).length >= 6);
});

test('leave scripts use strict shared conversion and avoid locale date parsing', () => {
    const source = scripts.map(read).join('\n');

    for (const name of ['toIsoDate', 'formatDisplayDate']) {
        assert.match(source, new RegExp(`\\b${name}\\b`));
    }
    assert.doesNotMatch(source, /new Date\s*\(/);
});

test('leave requests normalize every date-bearing request boundary', () => {
    for (const path of [
        'app/Http/Requests/StoreNghiPhepRequest.php',
        'app/Http/Requests/UpdateNghiPhepRequest.php',
    ]) {
        const source = read(path);
        assert.match(source, /use NormalizesDisplayDates/);
        assert.match(source, /normalizeDisplayDateFields\(\['tu_ngay', 'den_ngay'\]\)/);
        assert.match(source, /date_format:Y-m-d/);
        assert.match(source, /'tu_ngay'\s*=>\s*'Từ ngày'/);
        assert.match(source, /'den_ngay'\s*=>\s*'Đến ngày'/);
    }
});

test('leave invalid date feedback is field-level and blocks transport', () => {
    const create = read('resources/js/frontend/nghiphep/create.js');
    const admin = read('resources/js/frontend/nghiphep/nghiphep.js');
    const approval = read('resources/js/frontend/nghiphep/duyet-nghi-phep.js');

    assert.match(create, /leave-from-date-error/);
    assert.match(create, /toIsoDate/);
    assert.match(admin, /leave-from-date-error/);
    assert.match(approval, /leave-filter-from-error/);
    assert.match(approval, /toIsoDate/);
});

test('leave log row actions use canonical Bootstrap Icons', () => {
    const create = read('resources/js/frontend/nghiphep/create.js');

    assert.match(create, /bi-pencil-square/);
    assert.match(create, /bi-trash/);
});
