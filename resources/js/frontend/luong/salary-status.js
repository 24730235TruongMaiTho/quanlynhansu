const COMPLETED_MESSAGE = 'Hoàn tất tính lương';

function normalizeStatusMessage(value) {
    return String(value ?? '').trim().toLocaleLowerCase('vi-VN');
}

function isSalaryCalculationComplete(salary = {}) {
    const statusCode = String(salary?.trang_thai_tinh_luong ?? '').trim().toUpperCase();

    return statusCode === 'READY'
        || normalizeStatusMessage(salary?.thong_bao_tinh_luong) === normalizeStatusMessage(COMPLETED_MESSAGE);
}

function getSalaryStatusText(salary = {}) {
    if (isSalaryCalculationComplete(salary)) return COMPLETED_MESSAGE;

    return String(salary?.thong_bao_tinh_luong ?? '').trim() || 'Cần kiểm tra dữ liệu';
}

export {
    getSalaryStatusText,
    isSalaryCalculationComplete,
};
