import assert from 'node:assert/strict';
import test from 'node:test';

import {
    OWN_LEAVE_API_URL,
    buildOwnLeavePayload,
} from '../../../resources/js/frontend/nghiphep/own-leave-contract.js';

test('own leave payload cannot carry employee or approval identity', () => {
    assert.equal(OWN_LEAVE_API_URL, '/api/v1/nghi-phep/cua-toi');
    assert.deepEqual(buildOwnLeavePayload({
        fromDate: '2026-09-20',
        toDate: '2026-09-21',
        leaveType: '1',
        reason: ' Việc gia đình ',
        ma_nv: '00002',
        trang_thai_duyet: 1,
    }), {
        tu_ngay: '2026-09-20',
        den_ngay: '2026-09-21',
        ma_lp: '1',
        ly_do: 'Việc gia đình',
    });
});
