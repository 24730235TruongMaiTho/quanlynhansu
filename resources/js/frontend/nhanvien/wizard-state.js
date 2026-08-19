const EMPLOYMENT_FIELDS = new Set(['ngay_vao_lam', 'ma_pb', 'ma_cv', 'ma_tt']);

export function firstInvalidStep(fieldNames) {
    for (const fieldName of fieldNames) {
        if (EMPLOYMENT_FIELDS.has(fieldName)) {
            return 2;
        }

        if (fieldName !== 'nhan_vien') {
            return 1;
        }
    }

    return 3;
}

export function nextStep(current) {
    return Math.min(3, Math.max(1, Number(current) || 1) + 1);
}

export function previousStep(current) {
    return Math.max(1, Math.min(3, Number(current) || 1) - 1);
}
