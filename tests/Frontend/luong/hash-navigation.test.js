import test from 'node:test';
import assert from 'node:assert/strict';
import fs from 'node:fs';

const js = fs.readFileSync(
    new URL('../../../resources/js/frontend/luong/luong.js', import.meta.url),
    'utf8',
);
const view = fs.readFileSync(
    new URL('../../../resources/views/backend/luong/index.blade.php', import.meta.url),
    'utf8',
);

test('salary coefficient hash reveals and focuses the authorized coefficient card after initialization', () => {
    assert.match(view, /id="salary-coefficient-card"/);
    assert.match(view, /id="salary-coefficient-card"[\s\S]{0,120}tabindex="-1"/);
    assert.match(js, /location\.hash/);
    assert.match(js, /salary-coefficient-card/);
    assert.match(js, /scrollIntoView/);
    assert.match(js, /\.focus\(/);
});
