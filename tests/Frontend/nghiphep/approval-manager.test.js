import assert from 'node:assert/strict';
import fs from 'node:fs';
import path from 'node:path';
import test from 'node:test';
import { fileURLToPath } from 'node:url';

const root = fileURLToPath(new URL('../../../', import.meta.url));
const read = (relativePath) => fs.readFileSync(path.join(root, relativePath), 'utf8');

test('approval mutation sends only route id and validated status', () => {
    const source = read('resources/js/frontend/nghiphep/duyet-nghi-phep.js');
    const actionBlock = source.slice(source.indexOf('async function processLeave'));

    assert.match(source, /\/duyet/);
    assert.match(source, /method:\s*'PATCH'/);
    assert.match(source, /trang_thai_duyet:\s*status/);
    assert.doesNotMatch(actionBlock, /ma_nv:\s*item\.ma_nv/);
    assert.doesNotMatch(actionBlock, /ma_pb:\s*item\.ma_pb/);
});

test('approval client gives safe accessible feedback for authorization and conflict responses', () => {
    const source = read('resources/js/frontend/nghiphep/duyet-nghi-phep.js');

    assert.match(source, /403/);
    assert.match(source, /404/);
    assert.match(source, /409/);
    assert.match(source, /actionError\.textContent/);
});
