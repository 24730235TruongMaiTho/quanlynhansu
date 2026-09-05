import assert from 'node:assert/strict';
import fs from 'node:fs';
import path from 'node:path';
import test from 'node:test';
import { fileURLToPath } from 'node:url';

const root = fileURLToPath(new URL('../../../', import.meta.url));
const read = (relativePath) => fs.readFileSync(path.join(root, relativePath), 'utf8');

test('approval filters expose an explicit apply action', () => {
    const view = read('resources/views/backend/nghiphep/duyet-nghi-phep.blade.php');
    const source = read('resources/js/frontend/nghiphep/duyet-nghi-phep.js');

    assert.match(view, /id="leave-filter-apply"/);
    assert.match(view, /Áp dụng bộ lọc/);
    assert.match(source, /applyFilters/);
    assert.match(source, /leave-filter-apply/);
});

test('approval filters do not fetch while individual controls change', () => {
    const source = read('resources/js/frontend/nghiphep/duyet-nghi-phep.js');

    assert.doesNotMatch(source, /scheduleSearch/);
    assert.doesNotMatch(source, /keyword\?\.addEventListener\('input'/);
    assert.doesNotMatch(source, /type\?\.addEventListener\('change'/);
    assert.doesNotMatch(source, /from\?\.addEventListener\('change'/);
    assert.doesNotMatch(source, /to\?\.addEventListener\('change'/);
    assert.match(source, /event\.key === 'Enter'/);
});
