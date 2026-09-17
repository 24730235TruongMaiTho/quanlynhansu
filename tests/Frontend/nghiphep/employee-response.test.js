import assert from 'node:assert/strict';
import test from 'node:test';

import {
    extractData,
    genderLabel,
    normalizeEmployee,
} from '../../../resources/js/frontend/nghiphep/employee-response.js';

test('canonical employee paginator is normalized without browser globals or undefined gender', () => {
    const result = {
        data: {
            data: [{
                ma_nv: 'NV001',
                ho_ten: 'Nguyễn An',
                gioi_tinh: 0,
                sdt: '0900000001',
                email: 'an@example.test',
                ma_pb: 2,
                ten_pb: 'Kỹ thuật',
                ma_cv: 3,
                ten_cv: 'Lập trình viên',
                ten_tt: 'Đang làm',
            }],
        },
    };

    const rows = extractData(result).map(normalizeEmployee);

    assert.deepEqual(rows, [{
        ma_nv: 'NV001',
        ho_ten: 'Nguyễn An',
        gioi_tinh: 0,
        sdt: '0900000001',
        email: 'an@example.test',
        ma_pb: 2,
        ten_pb: 'Kỹ thuật',
        ma_cv: 3,
        ten_cv: 'Lập trình viên',
        ten_tt: 'Đang làm',
    }]);
    assert.equal(genderLabel(rows[0].gioi_tinh), 'Nữ');
    assert.equal(JSON.stringify(rows).includes('undefined'), false);
});

test('gender labels explicitly preserve canonical boolean and string values', () => {
    for (const value of [1, '1', true, 'Nam']) {
        assert.equal(genderLabel(value), 'Nam');
    }

    for (const value of [0, '0', false, 'Nữ']) {
        assert.equal(genderLabel(value), 'Nữ');
    }

    for (const value of [null, undefined, 2, 'unknown']) {
        assert.equal(genderLabel(value), '—');
    }
});
