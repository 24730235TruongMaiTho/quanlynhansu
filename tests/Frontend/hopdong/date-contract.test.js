import test from 'node:test';
import assert from 'node:assert/strict';
import fs from 'node:fs';

const root = new URL('../../../', import.meta.url);
const read = (path) => fs.readFileSync(new URL(path, root), 'utf8');

test('contract date inputs use strict Vietnamese display-date controls', () => {
    const form = read('resources/views/backend/hopdong/form.blade.php');

    assert.doesNotMatch(form, /type="date"/);
    assert.equal((form.match(/placeholder="dd\/mm\/yyyy"/g) || []).length, 2);
    assert.equal((form.match(/inputmode="numeric"/g) || []).length, 3);
    assert.equal((form.match(/maxlength="10"/g) || []).length, 2);
    assert.match(form, /id="luong_co_ban" name="luong_co_ban"/);
    assert.match(form, /type="text" inputmode="numeric"/);
});

test('contract index formats dates through the shared safe formatter', () => {
    const index = read('resources/views/backend/hopdong/index.blade.php');

    assert.match(index, /DisplayDateFormatter::format\(/);
    assert.doesNotMatch(index, /\$contract->ngay_(?:ky|het_han)\s*\?\?>?/);
});

test('contract requests normalize display dates and validate strict ISO after normalization', () => {
    const store = read('app/Http/Requests/StoreHopDongRequest.php');

    assert.match(store, /use NormalizesDisplayDates/);
    assert.match(store, /normalizeDisplayDateFields\(\['ngay_ky', 'ngay_het_han'\]\)/);
    assert.match(store, /date_format:Y-m-d/);
    assert.match(store, /'ngay_ky'\s*=>\s*'Ngày ký'/);
});
