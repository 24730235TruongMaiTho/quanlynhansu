import { toIsoDate } from '../shared/date-field.js';

export const OWN_LEAVE_API_URL = '/api/v1/nghi-phep/cua-toi';

export function buildOwnLeavePayload({
    fromDate,
    toDate,
    leaveType,
    reason,
}) {
    return {
        tu_ngay: toIsoDate(fromDate || '') || null,
        den_ngay: toIsoDate(toDate || '') || null,
        ma_lp: leaveType || null,
        ly_do: String(reason || '').trim(),
    };
}
