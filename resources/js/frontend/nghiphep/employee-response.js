export function extractData(result) {
    if (Array.isArray(result)) return result;
    if (Array.isArray(result?.data)) return result.data;
    if (Array.isArray(result?.data?.data)) return result.data.data;
    return [];
}

export function genderLabel(value) {
    return value ? (value === 1) ? 'Nam' : 'Nữ' : "-";
}

export function normalizeEmployee(employee) {
    return {
        ma_nv: employee.ma_nv ?? employee.nhan_vien?.ma_nv ?? '',
        ho_ten:
            employee.ho_ten ??
            employee.ten_nv ??
            employee.nhan_vien?.ho_ten ??
            employee.nhan_vien?.ten_nv ??
            'Chưa cập nhật',
        gioi_tinh:
            employee.gioi_tinh ??
            employee.nhan_vien?.gioi_tinh ??
            null,
        sdt: employee.sdt ?? employee.nhan_vien?.sdt ?? '—',
        email: employee.email ?? employee.nhan_vien?.email ?? '—',
        ma_pb: employee.ma_pb ?? employee.nhan_vien?.ma_pb ?? null,
        ten_pb:
            employee.ten_pb ??
            employee.phong_ban ??
            employee.nhan_vien?.ten_pb ??
            employee.nhan_vien?.phong_ban ??
            '—',
        ma_cv: employee.ma_cv ?? employee.nhan_vien?.ma_cv ?? null,
        ten_cv:
            employee.ten_cv ??
            employee.chuc_vu ??
            employee.nhan_vien?.ten_cv ??
            employee.nhan_vien?.chuc_vu ??
            '—',
        ten_tt:
            employee.ten_tt ??
            employee.trang_thai ??
            employee.nhan_vien?.ten_tt ??
            'Đang làm việc',
    };
}
