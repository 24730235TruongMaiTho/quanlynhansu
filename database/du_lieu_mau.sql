-- Deterministic local/demo seed for the fresh 15-table schema.
-- Run only after database/tao_bang.sql on an empty/disposable database.
-- All employee rows use the same valid bcrypt hash for the local/demo
-- convention documented in database/sql/employee/README.md; mat_khau is never
-- returned by the application.

USE quan_ly_nhan_su;
SET NAMES utf8mb4;

INSERT INTO phong_ban (ma_pb, ten_pb) VALUES
    (1, N'Phòng Nhân sự'), (2, N'Phòng Kế toán'),
    (3, N'Phòng Công nghệ thông tin'), (4, N'Phòng Kinh doanh'),
    (5, N'Phòng Marketing');

INSERT INTO chuc_vu (ma_cv, ten_cv, he_so_phu_cap) VALUES
    (1, N'Giám đốc', 2.00), (2, N'Trưởng phòng', 1.50),
    (3, N'Phó phòng', 1.25), (4, N'Nhân viên chính thức', 1.00),
    (5, N'Thực tập sinh', 0.50);

INSERT INTO vai_tro (ma_vt, ten_vt, mo_ta) VALUES
    (1, N'Super Admin', N'Toàn quyền quản trị hệ thống'),
    (2, N'Quản trị Nhân sự', N'Quản lý nhân viên, hợp đồng, nghỉ phép, chấm công và xem bảng lương'),
    (3, N'Quản trị CBL', N'Quản lý chấm công, nghỉ phép và tính lương'),
    (4, N'Trưởng phòng', N'Duyệt nghỉ phép và xem thông tin nhân viên phòng ban'),
    (5, N'Nhân viên', N'Xem thông tin cá nhân, chấm công và gửi đơn nghỉ phép');

-- 28 catalog permissions from the source seed, plus employee reset 105.
INSERT INTO quyen (ma_quyen, ky_hieu_quyen, ten_quyen, module) VALUES
    (101, 'NV_VIEW', N'Xem danh sách nhân viên', 'NhanVien'),
    (102, 'NV_CREATE', N'Thêm mới nhân viên', 'NhanVien'),
    (103, 'NV_EDIT', N'Cập nhật nhân viên', 'NhanVien'),
    (104, 'NV_DELETE', N'Xóa nhân viên', 'NhanVien'),
    (105, 'NV_RESET_PASSWORD', N'Đặt lại mật khẩu nhân viên', 'NhanVien'),
    (201, 'PB_VIEW', N'Xem danh sách phòng ban', 'PhongBan'),
    (202, 'PB_CREATE', N'Thêm phòng ban', 'PhongBan'),
    (203, 'PB_EDIT', N'Cập nhật phòng ban', 'PhongBan'),
    (204, 'PB_DELETE', N'Xóa phòng ban', 'PhongBan'),
    (301, 'CV_VIEW', N'Xem danh sách chức vụ', 'ChucVu'),
    (302, 'CV_CREATE', N'Thêm chức vụ', 'ChucVu'),
    (303, 'CV_EDIT', N'Cập nhật chức vụ', 'ChucVu'),
    (304, 'CV_DELETE', N'Xóa chức vụ', 'ChucVu'),
    (401, 'HD_VIEW', N'Xem danh sách hợp đồng', 'HopDong'),
    (402, 'HD_CREATE', N'Tạo hợp đồng mới', 'HopDong'),
    (403, 'HD_EDIT', N'Cập nhật hợp đồng', 'HopDong'),
    (404, 'HD_DELETE', N'Hủy hoặc xóa hợp đồng', 'HopDong'),
    (501, 'LUONG_VIEW', N'Xem bảng lương', 'Luong'),
    (502, 'LUONG_CALC', N'Tính lương hàng tháng', 'Luong'),
    (503, 'LUONG_EDIT', N'Điều chỉnh thưởng phạt', 'Luong'),
    (504, 'LUONG_LOCK', N'Chốt bảng lương', 'Luong'),
    (601, 'NP_VIEW', N'Xem danh sách nghỉ phép', 'NghiPhep'),
    (602, 'NP_CREATE', N'Tạo đơn xin nghỉ phép', 'NghiPhep'),
    (603, 'NP_APPROVE', N'Duyệt hoặc từ chối đơn nghỉ phép', 'NghiPhep'),
    (701, 'CC_VIEW', N'Xem dữ liệu chấm công', 'ChamCong'),
    (702, 'CC_IMPORT', N'Nhập hoặc ghi nhận chấm công', 'ChamCong'),
    (703, 'CC_EDIT', N'Sửa dữ liệu chấm công', 'ChamCong'),
    (801, 'PQ_ROLE_VIEW', N'Xem danh sách vai trò', 'PhanQuyen'),
    (802, 'PQ_ROLE_MANAGE', N'Quản lý vai trò và gán quyền', 'PhanQuyen');

-- Role 1 owns the complete catalog. Other roles receive only permissions
-- described by their business role; existing module permissions are retained.
INSERT INTO vai_tro_quyen (ma_vt, ma_quyen) VALUES
    (1, 101), (1, 102), (1, 103), (1, 104), (1, 105),
    (1, 201), (1, 202), (1, 203), (1, 204),
    (1, 301), (1, 302), (1, 303), (1, 304),
    (1, 401), (1, 402), (1, 403), (1, 404),
    (1, 501), (1, 502), (1, 503), (1, 504),
    (1, 601), (1, 602), (1, 603),
    (1, 701), (1, 702), (1, 703), (1, 801), (1, 802),
    (2, 101), (2, 102), (2, 103), (2, 104), (2, 105),
    (2, 201), (2, 202), (2, 203), (2, 204),
    (2, 301), (2, 302), (2, 303), (2, 304),
    (2, 401), (2, 402), (2, 403), (2, 404),
    (3, 501), (3, 502), (3, 503), (3, 504),
    (3, 601), (3, 602), (3, 603), (3, 701), (3, 702), (3, 703),
    (4, 101), (4, 401), (4, 601), (4, 603), (4, 701),
    (5, 101), (5, 601), (5, 602), (5, 701);

INSERT INTO trang_thai_lam_viec (ma_tt, ten_tt) VALUES
    (1, N'Thử việc'), (2, N'Đang làm việc'),
    (3, N'Tạm nghỉ không lương'), (4, N'Đã nghỉ việc');

INSERT INTO bo_dem_ma_nhan_vien (ten_bo_dem, so_da_cap) VALUES
    ('NHAN_VIEN', 30);

-- Original HEAD employee names, identity attributes, role/status mapping and
-- Vietnamese text are preserved. The six added columns are part of the fresh
-- 15-table contract; status 4 rows carry a termination date.
INSERT INTO nhan_vien (
    ma_nv, ho_ten, ngay_sinh, gioi_tinh, sdt, email, ngay_vao_lam,
    ma_pb, ma_cv, dan_toc, cccd, noi_cap_cccd, hoc_van, ma_tt, mat_khau, ma_vt,
    dia_chi_cu_the, phuong_xa, quan_huyen, tinh_thanh, anh_dai_dien, ngay_nghi_viec
) VALUES
    ('NV001', N'Nguyễn Văn An', '1985-03-15', 1, '0901234567', 'an.nguyen@company.com', '2015-01-10', 3, 1, N'Kinh', '001085000001', N'Cục CSQLHC về trật tự xã hội', N'Thạc sĩ', 2, '$2y$10$ZPlSMAbjhg0ljFn5atGPguMJR.dkeO6VaKCz2UnpAZVRkTeIEF/BG', 1, N'Số 01 đường Lê Lợi', N'Phường Bến Nghé', N'Quận 1', N'TP Hồ Chí Minh', NULL, NULL),
    ('NV002', N'Trần Thị Bích', '1988-07-20', 0, '0902234567', 'bich.tran@company.com', '2016-03-01', 1, 2, N'Kinh', '001088000002', N'Cục CSQLHC về trật tự xã hội', N'Đại học', 2, '$2y$10$ZPlSMAbjhg0ljFn5atGPguMJR.dkeO6VaKCz2UnpAZVRkTeIEF/BG', 2, N'Số 02 đường Lê Lợi', N'Phường Bến Nghé', N'Quận 1', N'TP Hồ Chí Minh', NULL, NULL),
    ('NV003', N'Lê Hoàng Cường', '1987-11-05', 1, '0903234567', 'cuong.le@company.com', '2017-05-15', 2, 2, N'Kinh', '001087000003', N'Cục CSQLHC về trật tự xã hội', N'Đại học', 2, '$2y$10$ZPlSMAbjhg0ljFn5atGPguMJR.dkeO6VaKCz2UnpAZVRkTeIEF/BG', 3, N'Số 03 đường Lê Lợi', N'Phường Bến Nghé', N'Quận 1', N'TP Hồ Chí Minh', NULL, NULL),
    ('NV004', N'Phạm Minh Đức', '1990-01-12', 1, '0904234567', 'duc.pham@company.com', '2018-02-01', 3, 2, N'Kinh', '001090000004', N'Cục CSQLHC về trật tự xã hội', N'Đại học', 2, '$2y$10$ZPlSMAbjhg0ljFn5atGPguMJR.dkeO6VaKCz2UnpAZVRkTeIEF/BG', 4, N'Số 04 đường Lê Lợi', N'Phường Bến Nghé', N'Quận 1', N'TP Hồ Chí Minh', NULL, NULL),
    ('NV005', N'Hoàng Thùy Giang', '1991-09-18', 0, '0905234567', 'giang.hoang@company.com', '2018-08-10', 4, 2, N'Kinh', '001091000005', N'Cục CSQLHC về trật tự xã hội', N'Đại học', 2, '$2y$10$ZPlSMAbjhg0ljFn5atGPguMJR.dkeO6VaKCz2UnpAZVRkTeIEF/BG', 4, N'Số 05 đường Lê Lợi', N'Phường Bến Nghé', N'Quận 1', N'TP Hồ Chí Minh', NULL, NULL),
    ('NV006', N'Vũ Thị Hoa', '1993-04-22', 0, '0906234567', 'hoa.vu@company.com', '2019-01-15', 1, 3, N'Kinh', '001093000006', N'Cục CSQLHC về trật tự xã hội', N'Đại học', 2, '$2y$10$ZPlSMAbjhg0ljFn5atGPguMJR.dkeO6VaKCz2UnpAZVRkTeIEF/BG', 2, N'Số 06 đường Lê Lợi', N'Phường Bến Nghé', N'Quận 1', N'TP Hồ Chí Minh', NULL, NULL),
    ('NV007', N'Đặng Văn Khoa', '1995-12-08', 1, '0907234567', 'khoa.dang@company.com', '2020-03-01', 1, 4, N'Kinh', '001095000007', N'Cục CSQLHC về trật tự xã hội', N'Đại học', 2, '$2y$10$ZPlSMAbjhg0ljFn5atGPguMJR.dkeO6VaKCz2UnpAZVRkTeIEF/BG', 5, N'Số 07 đường Lê Lợi', N'Phường Bến Nghé', N'Quận 1', N'TP Hồ Chí Minh', NULL, NULL),
    ('NV008', N'Bùi Thị Lan', '1996-06-30', 0, '0908234567', 'lan.bui@company.com', '2021-06-15', 1, 4, N'Mường', '001096000008', N'Cục CSQLHC về trật tự xã hội', N'Cao đẳng', 2, '$2y$10$ZPlSMAbjhg0ljFn5atGPguMJR.dkeO6VaKCz2UnpAZVRkTeIEF/BG', 5, N'Số 08 đường Lê Lợi', N'Phường Bến Nghé', N'Quận 1', N'TP Hồ Chí Minh', NULL, NULL),
    ('NV009', N'Đỗ Minh Mỹ', '1992-08-14', 0, '0909234567', 'my.do@company.com', '2019-04-10', 2, 3, N'Kinh', '001092000009', N'Cục CSQLHC về trật tự xã hội', N'Đại học', 2, '$2y$10$ZPlSMAbjhg0ljFn5atGPguMJR.dkeO6VaKCz2UnpAZVRkTeIEF/BG', 3, N'Số 09 đường Lê Lợi', N'Phường Bến Nghé', N'Quận 1', N'TP Hồ Chí Minh', NULL, NULL),
    ('NV010', N'Nông Văn Nam', '1994-02-28', 1, '0910234567', 'nam.nong@company.com', '2020-09-01', 2, 4, N'Tày', '001094000010', N'Cục CSQLHC về trật tự xã hội', N'Đại học', 2, '$2y$10$ZPlSMAbjhg0ljFn5atGPguMJR.dkeO6VaKCz2UnpAZVRkTeIEF/BG', 5, N'Số 10 đường Lê Lợi', N'Phường Bến Nghé', N'Quận 1', N'TP Hồ Chí Minh', NULL, NULL),
    ('NV011', N'Trịnh Quốc Oanh', '1993-10-10', 1, '0911234567', 'oanh.trinh@company.com', '2019-07-01', 3, 3, N'Kinh', '001093000011', N'Cục CSQLHC về trật tự xã hội', N'Đại học', 2, '$2y$10$ZPlSMAbjhg0ljFn5atGPguMJR.dkeO6VaKCz2UnpAZVRkTeIEF/BG', 5, N'Số 11 đường Lê Lợi', N'Phường Bến Nghé', N'Quận 1', N'TP Hồ Chí Minh', NULL, NULL),
    ('NV012', N'Ngô Tấn Phát', '1997-05-19', 1, '0912234567', 'phat.ngo@company.com', '2021-02-15', 3, 4, N'Kinh', '001097000012', N'Cục CSQLHC về trật tự xã hội', N'Đại học', 2, '$2y$10$ZPlSMAbjhg0ljFn5atGPguMJR.dkeO6VaKCz2UnpAZVRkTeIEF/BG', 5, N'Số 12 đường Lê Lợi', N'Phường Bến Nghé', N'Quận 1', N'TP Hồ Chí Minh', NULL, NULL),
    ('NV013', N'Lý Thị Quỳnh', '1998-01-25', 0, '0913234567', 'quynh.ly@company.com', '2022-01-10', 3, 4, N'Kinh', '001098000013', N'Cục CSQLHC về trật tự xã hội', N'Đại học', 2, '$2y$10$ZPlSMAbjhg0ljFn5atGPguMJR.dkeO6VaKCz2UnpAZVRkTeIEF/BG', 5, N'Số 13 đường Lê Lợi', N'Phường Bến Nghé', N'Quận 1', N'TP Hồ Chí Minh', NULL, NULL),
    ('NV014', N'Dương Sơn Sơn', '1999-11-11', 1, '0914234567', 'son.duong@company.com', '2022-08-01', 3, 4, N'Kinh', '001099000014', N'Cục CSQLHC về trật tự xã hội', N'Đại học', 2, '$2y$10$ZPlSMAbjhg0ljFn5atGPguMJR.dkeO6VaKCz2UnpAZVRkTeIEF/BG', 5, N'Số 14 đường Lê Lợi', N'Phường Bến Nghé', N'Quận 1', N'TP Hồ Chí Minh', NULL, NULL),
    ('NV015', N'Lương Phương Thảo', '2000-03-04', 0, '0915234567', 'thao.luong@company.com', '2023-03-15', 3, 4, N'Thái', '001200000015', N'Cục CSQLHC về trật tự xã hội', N'Đại học', 2, '$2y$10$ZPlSMAbjhg0ljFn5atGPguMJR.dkeO6VaKCz2UnpAZVRkTeIEF/BG', 5, N'Số 15 đường Lê Lợi', N'Phường Bến Nghé', N'Quận 1', N'TP Hồ Chí Minh', NULL, NULL),
    ('NV016', N'Mai Văn Tiến', '1990-07-07', 1, '0916234567', 'tien.mai@company.com', '2018-11-01', 4, 3, N'Kinh', '001090000016', N'Cục CSQLHC về trật tự xã hội', N'Đại học', 2, '$2y$10$ZPlSMAbjhg0ljFn5atGPguMJR.dkeO6VaKCz2UnpAZVRkTeIEF/BG', 5, N'Số 16 đường Lê Lợi', N'Phường Bến Nghé', N'Quận 1', N'TP Hồ Chí Minh', NULL, NULL),
    ('NV017', N'Nguyễn Thị Uyên', '1995-09-23', 0, '0917234567', 'uyen.nguyen@company.com', '2020-05-15', 4, 4, N'Kinh', '001095000017', N'Cục CSQLHC về trật tự xã hội', N'Đại học', 2, '$2y$10$ZPlSMAbjhg0ljFn5atGPguMJR.dkeO6VaKCz2UnpAZVRkTeIEF/BG', 5, N'Số 17 đường Lê Lợi', N'Phường Bến Nghé', N'Quận 1', N'TP Hồ Chí Minh', NULL, NULL),
    ('NV018', N'Phan Khánh Vinh', '1996-12-12', 1, '0918234567', 'vinh.phan@company.com', '2021-04-01', 4, 4, N'Kinh', '001096000018', N'Cục CSQLHC về trật tự xã hội', N'Đại học', 2, '$2y$10$ZPlSMAbjhg0ljFn5atGPguMJR.dkeO6VaKCz2UnpAZVRkTeIEF/BG', 5, N'Số 18 đường Lê Lợi', N'Phường Bến Nghé', N'Quận 1', N'TP Hồ Chí Minh', NULL, NULL),
    ('NV019', N'Hà Thị Xuân', '1997-04-05', 0, '0919234567', 'xuan.ha@company.com', '2022-02-15', 4, 4, N'Kinh', '001097000019', N'Cục CSQLHC về trật tự xã hội', N'Đại học', 2, '$2y$10$ZPlSMAbjhg0ljFn5atGPguMJR.dkeO6VaKCz2UnpAZVRkTeIEF/BG', 5, N'Số 19 đường Lê Lợi', N'Phường Bến Nghé', N'Quận 1', N'TP Hồ Chí Minh', NULL, NULL),
    ('NV020', N'Trương Công Yên', '1998-08-20', 1, '0920234567', 'yen.truong@company.com', '2022-10-01', 4, 4, N'Kinh', '001098000020', N'Cục CSQLHC về trật tự xã hội', N'Đại học', 2, '$2y$10$ZPlSMAbjhg0ljFn5atGPguMJR.dkeO6VaKCz2UnpAZVRkTeIEF/BG', 5, N'Số 20 đường Lê Lợi', N'Phường Bến Nghé', N'Quận 1', N'TP Hồ Chí Minh', NULL, NULL),
    ('NV021', N'Lê Thị Anh', '1992-06-18', 0, '0921234567', 'anh.le@company.com', '2019-09-15', 5, 3, N'Kinh', '001092000021', N'Cục CSQLHC về trật tự xã hội', N'Đại học', 2, '$2y$10$ZPlSMAbjhg0ljFn5atGPguMJR.dkeO6VaKCz2UnpAZVRkTeIEF/BG', 5, N'Số 21 đường Lê Lợi', N'Phường Bến Nghé', N'Quận 1', N'TP Hồ Chí Minh', NULL, NULL),
    ('NV022', N'Nguyễn Hoàng Bảo', '1996-03-30', 1, '0922234567', 'bao.nguyen@company.com', '2021-01-10', 5, 4, N'Kinh', '001096000022', N'Cục CSQLHC về trật tự xã hội', N'Đại học', 2, '$2y$10$ZPlSMAbjhg0ljFn5atGPguMJR.dkeO6VaKCz2UnpAZVRkTeIEF/BG', 5, N'Số 22 đường Lê Lợi', N'Phường Bến Nghé', N'Quận 1', N'TP Hồ Chí Minh', NULL, NULL),
    ('NV023', N'Đào Thị Cúc', '1997-10-15', 0, '0923234567', 'cuc.dao@company.com', '2022-05-01', 5, 4, N'Kinh', '001097000023', N'Cục CSQLHC về trật tự xã hội', N'Đại học', 2, '$2y$10$ZPlSMAbjhg0ljFn5atGPguMJR.dkeO6VaKCz2UnpAZVRkTeIEF/BG', 5, N'Số 23 đường Lê Lợi', N'Phường Bến Nghé', N'Quận 1', N'TP Hồ Chí Minh', NULL, NULL),
    ('NV024', N'Vũ Đình Dung', '2001-02-14', 1, '0924234567', 'dung.vu@company.com', '2024-01-15', 3, 5, N'Kinh', '001201000024', N'Cục CSQLHC về trật tự xã hội', N'Đại học', 1, '$2y$10$ZPlSMAbjhg0ljFn5atGPguMJR.dkeO6VaKCz2UnpAZVRkTeIEF/BG', 5, N'Số 24 đường Lê Lợi', N'Phường Bến Nghé', N'Quận 1', N'TP Hồ Chí Minh', NULL, NULL),
    ('NV025', N'Trần Ngọc Đạt', '2001-07-09', 1, '0925234567', 'dat.tran@company.com', '2024-02-01', 4, 5, N'Kinh', '001201000025', N'Cục CSQLHC về trật tự xã hội', N'Đại học', 1, '$2y$10$ZPlSMAbjhg0ljFn5atGPguMJR.dkeO6VaKCz2UnpAZVRkTeIEF/BG', 5, N'Số 25 đường Lê Lợi', N'Phường Bến Nghé', N'Quận 1', N'TP Hồ Chí Minh', NULL, NULL),
    ('NV026', N'Phạm Thu Hà', '2002-05-20', 0, '0926234567', 'ha.pham@company.com', '2024-03-01', 5, 5, N'Kinh', '001202000026', N'Cục CSQLHC về trật tự xã hội', N'Cao đẳng', 1, '$2y$10$ZPlSMAbjhg0ljFn5atGPguMJR.dkeO6VaKCz2UnpAZVRkTeIEF/BG', 5, N'Số 26 đường Lê Lợi', N'Phường Bến Nghé', N'Quận 1', N'TP Hồ Chí Minh', NULL, NULL),
    ('NV027', N'Hoàng Văn Khánh', '1994-11-30', 1, '0927234567', 'khanh.hoang@company.com', '2020-11-15', 2, 4, N'Kinh', '001094000027', N'Cục CSQLHC về trật tự xã hội', N'Đại học', 3, '$2y$10$ZPlSMAbjhg0ljFn5atGPguMJR.dkeO6VaKCz2UnpAZVRkTeIEF/BG', 5, N'Số 27 đường Lê Lợi', N'Phường Bến Nghé', N'Quận 1', N'TP Hồ Chí Minh', NULL, NULL),
    ('NV028', N'Đỗ Thị Thúy', '1995-08-12', 0, '0928234567', 'thuy.do@company.com', '2021-07-01', 1, 4, N'Kinh', '001095000028', N'Cục CSQLHC về trật tự xã hội', N'Đại học', 3, '$2y$10$ZPlSMAbjhg0ljFn5atGPguMJR.dkeO6VaKCz2UnpAZVRkTeIEF/BG', 5, N'Số 28 đường Lê Lợi', N'Phường Bến Nghé', N'Quận 1', N'TP Hồ Chí Minh', NULL, NULL),
    ('NV029', N'Nguyễn Văn Long', '1989-04-03', 1, '0929234567', 'long.nguyen@company.com', '2017-09-01', 4, 4, N'Kinh', '001089000029', N'Cục CSQLHC về trật tự xã hội', N'Đại học', 4, '$2y$10$ZPlSMAbjhg0ljFn5atGPguMJR.dkeO6VaKCz2UnpAZVRkTeIEF/BG', 5, N'Số 29 đường Lê Lợi', N'Phường Bến Nghé', N'Quận 1', N'TP Hồ Chí Minh', NULL, '2025-01-15'),
    ('NV030', N'Bùi Ánh Tuyết', '1993-09-27', 0, '0930234567', 'tuyet.bui@company.com', '2019-12-01', 5, 4, N'Kinh', '001093000030', N'Cục CSQLHC về trật tự xã hội', N'Đại học', 4, '$2y$10$ZPlSMAbjhg0ljFn5atGPguMJR.dkeO6VaKCz2UnpAZVRkTeIEF/BG', 5, N'Số 30 đường Lê Lợi', N'Phường Bến Nghé', N'Quận 1', N'TP Hồ Chí Minh', NULL, '2025-02-01');
