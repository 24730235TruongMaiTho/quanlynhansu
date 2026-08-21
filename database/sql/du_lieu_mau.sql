USE quan_ly_nhan_su;

-- 1. Dữ liệu bảng Phòng Ban
INSERT INTO phong_ban (ten_pb) VALUES
(N'Phòng Nhân sự'),
(N'Phòng Kế toán'),
(N'Phòng Công nghệ thông tin'),
(N'Phòng Kinh doanh'),
(N'Phòng Marketing');

-- 2. Dữ liệu bảng Chức Vụ
INSERT INTO chuc_vu (ten_cv, he_so_phu_cap) VALUES
(N'Giám đốc', 2.00),
(N'Trưởng phòng', 1.50),
(N'Phó phòng', 1.25),
(N'Nhân viên chính thức', 1.00),
(N'Thực tập sinh', 0.50);

-- 3. Dữ liệu bảng Trạng Thái Làm Việc
INSERT INTO trang_thai_lam_viec (ten_tt) VALUES
(N'Thử việc'),
(N'Đang làm việc'),
(N'Tạm nghỉ không lương'),
(N'Đã nghỉ việc');

-- 4. Dữ liệu bảng Vai Trò
INSERT INTO vai_tro (ten_vt, mo_ta) VALUES
(N'Super Admin', N'Toàn quyền quản trị hệ thống'),
(N'Quản trị Nhân sự', N'Quản lý nhân viên, hợp đồng, phòng ban, chức vụ'),
(N'Quản trị CBL', N'Quản lý chấm công, nghỉ phép và tính lương'),
(N'Trưởng phòng', N'Duyệt nghỉ phép và xem thông tin nhân viên phòng ban'),
(N'Nhân viên', N'Xem thông tin cá nhân, chấm công, gửi đơn nghỉ phép');

-- 5. Dữ liệu bảng Quyền (Đầy đủ các Module: NhanVien, PhongBan, ChucVu, HopDong, Luong, NghiPhep, ChamCong, PhanQuyen)
-- Quy ước MaQuyen:
-- 1xx: Module Nhân viên | 2xx: Module Phòng ban | 3xx: Module Chức vụ | 4xx: Module Hợp đồng
-- 5xx: Module Lương     | 6xx: Module Nghỉ phép | 7xx: Module Chấm công | 8xx: Module Phân quyền
INSERT INTO quyen (ma_quyen, ky_hieu_quyen, ten_quyen, module) VALUES
-- Module Nhân viên
(101, 'NV_VIEW', N'Xem danh sách nhân viên', 'NhanVien'),
(102, 'NV_CREATE', N'Thêm mới nhân viên', 'NhanVien'),
(103, 'NV_EDIT', N'Cập nhật nhân viên', 'NhanVien'),
(104, 'NV_DELETE', N'Xóa nhân viên', 'NhanVien'),

-- Module Phòng ban
(201, 'PB_VIEW', N'Xem danh sách phòng ban', 'PhongBan'),
(202, 'PB_CREATE', N'Thêm phòng ban', 'PhongBan'),
(203, 'PB_EDIT', N'Cập nhật phòng ban', 'PhongBan'),
(204, 'PB_DELETE', N'Xóa phòng ban', 'PhongBan'),

-- Module Chức vụ
(301, 'CV_VIEW', N'Xem danh sách chức vụ', 'ChucVu'),
(302, 'CV_CREATE', N'Thêm chức vụ', 'ChucVu'),
(303, 'CV_EDIT', N'Cập nhật chức vụ', 'ChucVu'),
(304, 'CV_DELETE', N'Xóa chức vụ', 'ChucVu'),

-- Module Hợp đồng
(401, 'HD_VIEW', N'Xem danh sách hợp đồng', 'HopDong'),
(402, 'HD_CREATE', N'Tạo hợp đồng mới', 'HopDong'),
(403, 'HD_EDIT', N'Cập nhật hợp đồng', 'HopDong'),
(404, 'HD_DELETE', N'Hủy/Xóa hợp đồng', 'HopDong'),

-- Module Lương
(501, 'LUONG_VIEW', N'Xem bảng lương', 'Luong'),
(502, 'LUONG_CALC', N'Tính lương hàng tháng', 'Luong'),
(503, 'LUONG_EDIT', N'Điều chỉnh thuởng/phạt', 'Luong'),
(504, 'LUONG_LOCK', N'Chốt bảng lương', 'Luong'),

-- Module Nghỉ phép
(601, 'NP_VIEW', N'Xem danh sách nghỉ phép', 'NghiPhep'),
(602, 'NP_CREATE', N'Tạo đơn xin nghỉ phép', 'NghiPhep'),
(603, 'NP_APPROVE', N'Duyệt/Từ chối đơn nghỉ phép', 'NghiPhep'),

-- Module Chấm công
(701, 'CC_VIEW', N'Xem dữ liệu chấm công', 'ChamCong'),
(702, 'CC_IMPORT', N'Nhập/Ghi nhận chấm công', 'ChamCong'),
(703, 'CC_EDIT', N'Sửa dữ liệu chấm công', 'ChamCong'),

-- Module Phân quyền
(801, 'PQ_ROLE_VIEW', N'Xem danh sách vai trò', 'PhanQuyen'),
(802, 'PQ_ROLE_MANAGE', N'Quản lý vai trò & Gán quyền', 'PhanQuyen');

-- 6. Dữ liệu bảng Nhân Viên
INSERT INTO nhan_vien (
    ma_nv, ho_ten, ngay_sinh, gioi_tinh, sdt, email, 
    ngay_vao_lam, ma_pb, ma_cv, dan_toc, cccd, noi_cap_cccd, 
    hoc_van, ma_tt, mat_khau, ma_vt
) VALUES
-- Ban Giám Đốc & Quản lý
('NV001', N'Nguyễn Văn An', '1985-03-15', 1, '0901234567', 'an.nguyen@company.com', '2015-01-10', 3, 1, N'Kinh', '001085000001', N'Cục CSQLHC về trật tự xã hội', N'Thạc sĩ', 2, '', 1),
('NV002', N'Trần Thị Bích', '1988-07-20', 0, '0902234567', 'bich.tran@company.com', '2016-03-01', 1, 2, N'Kinh', '001088000002', N'Cục CSQLHC về trật tự xã hội', N'Đại học', 2, '', 2),
('NV003', N'Lê Hoàng Cường', '1987-11-05', 1, '0903234567', 'cuong.le@company.com', '2017-05-15', 2, 2, N'Kinh', '001087000003', N'Cục CSQLHC về trật tự xã hội', N'Đại học', 2, '', 3),
('NV004', N'Phạm Minh Đức', '1990-01-12', 1, '0904234567', 'duc.pham@company.com', '2018-02-01', 3, 2, N'Kinh', '001090000004', N'Cục CSQLHC về trật tự xã hội', N'Đại học', 2, '', 4),
('NV005', N'Hoàng Thùy Giang', '1991-09-18', 0, '0905234567', 'giang.hoang@company.com', '2018-08-10', 4, 2, N'Kinh', '001091000005', N'Cục CSQLHC về trật tự xã hội', N'Đại học', 2, '', 4),

-- Phòng Nhân sự & Kế toán
('NV006', N'Vũ Thị Hoa', '1993-04-22', 0, '0906234567', 'hoa.vu@company.com', '2019-01-15', 1, 3, N'Kinh', '001093000006', N'Cục CSQLHC về trật tự xã hội', N'Đại học', 2, '', 2),
('NV007', N'Đặng Văn Khoa', '1995-12-08', 1, '0907234567', 'khoa.dang@company.com', '2020-03-01', 1, 4, N'Kinh', '001095000007', N'Cục CSQLHC về trật tự xã hội', N'Đại học', 2, '', 5),
('NV008', N'Bùi Thị Lan', '1996-06-30', 0, '0908234567', 'lan.bui@company.com', '2021-06-15', 1, 4, N'Mường', '001096000008', N'Cục CSQLHC về trật tự xã hội', N'Cao đẳng', 2, '', 5),
('NV009', N'Đỗ Minh Mỹ', '1992-08-14', 0, '0909234567', 'my.do@company.com', '2019-04-10', 2, 3, N'Kinh', '001092000009', N'Cục CSQLHC về trật tự xã hội', N'Đại học', 2, '', 3),
('NV010', N'Nông Văn Nam', '1994-02-28', 1, '0910234567', 'nam.nong@company.com', '2020-09-01', 2, 4, N'Tày', '001094000010', N'Cục CSQLHC về trật tự xã hội', N'Đại học', 2, '', 5),

-- Phòng CNTT
('NV011', N'Trịnh Quốc Oanh', '1993-10-10', 1, '0911234567', 'oanh.trinh@company.com', '2019-07-01', 3, 3, N'Kinh', '001093000011', N'Cục CSQLHC về trật tự xã hội', N'Đại học', 2, '', 5),
('NV012', N'Ngô Tấn Phát', '1997-05-19', 1, '0912234567', 'phat.ngo@company.com', '2021-02-15', 3, 4, N'Kinh', '001097000012', N'Cục CSQLHC về trật tự xã hội', N'Đại học', 2, '', 5),
('NV013', N'Lý Thị Quỳnh', '1998-01-25', 0, '0913234567', 'quynh.ly@company.com', '2022-01-10', 3, 4, N'Kinh', '001098000013', N'Cục CSQLHC về trật tự xã hội', N'Đại học', 2, '', 5),
('NV014', N'Dương Sơn Sơn', '1999-11-11', 1, '0914234567', 'son.duong@company.com', '2022-08-01', 3, 4, N'Kinh', '001099000014', N'Cục CSQLHC về trật tự xã hội', N'Đại học', 2, '', 5),
('NV015', N'Lương Phương Thảo', '2000-03-04', 0, '0915234567', 'thao.luong@company.com', '2023-03-15', 3, 4, N'Thái', '001200000015', N'Cục CSQLHC về trật tự xã hội', N'Đại học', 2, '', 5),

-- Phòng Kinh doanh
('NV016', N'Mai Văn Tiến', '1990-07-07', 1, '0916234567', 'tien.mai@company.com', '2018-11-01', 4, 3, N'Kinh', '001090000016', N'Cục CSQLHC về trật tự xã hội', N'Đại học', 2, '', 5),
('NV017', N'Nguyễn Thị Uyên', '1995-09-23', 0, '0917234567', 'uyen.nguyen@company.com', '2020-05-15', 4, 4, N'Kinh', '001095000017', N'Cục CSQLHC về trật tự xã hội', N'Đại học', 2, '', 5),
('NV018', N'Phan Khánh Vinh', '1996-12-12', 1, '0918234567', 'vinh.phan@company.com', '2021-04-01', 4, 4, N'Kinh', '001096000018', N'Cục CSQLHC về trật tự xã hội', N'Đại học', 2, '', 5),
('NV019', N'Hà Thị Xuân', '1997-04-05', 0, '0919234567', 'xuan.ha@company.com', '2022-02-15', 4, 4, N'Kinh', '001097000019', N'Cục CSQLHC về trật tự xã hội', N'Đại học', 2, '', 5),
('NV020', N'Trương Công Yên', '1998-08-20', 1, '0920234567', 'yen.truong@company.com', '2022-10-01', 4, 4, N'Kinh', '001098000020', N'Cục CSQLHC về trật tự xã hội', N'Đại học', 2, '', 5),

-- Phòng Marketing
('NV021', N'Lê Thị Anh', '1992-06-18', 0, '0921234567', 'anh.le@company.com', '2019-09-15', 5, 3, N'Kinh', '001092000021', N'Cục CSQLHC về trật tự xã hội', N'Đại học', 2, '', 5),
('NV022', N'Nguyễn Hoàng Bảo', '1996-03-30', 1, '0922234567', 'bao.nguyen@company.com', '2021-01-10', 5, 4, N'Kinh', '001096000022', N'Cục CSQLHC về trật tự xã hội', N'Đại học', 2, '', 5),
('NV023', N'Đào Thị Cúc', '1997-10-15', 0, '0923234567', 'cuc.dao@company.com', '2022-05-01', 5, 4, N'Kinh', '001097000023', N'Cục CSQLHC về trật tự xã hội', N'Đại học', 2, '', 5),

-- Nhân viên Thử việc & Trạng thái khác
('NV024', N'Vũ Đình Dung', '2001-02-14', 1, '0924234567', 'dung.vu@company.com', '2024-01-15', 3, 5, N'Kinh', '001201000024', N'Cục CSQLHC về trật tự xã hội', N'Đại học', 1, '', 5),
('NV025', N'Trần Ngọc Đạt', '2001-07-09', 1, '0925234567', 'dat.tran@company.com', '2024-02-01', 4, 5, N'Kinh', '001201000025', N'Cục CSQLHC về trật tự xã hội', N'Đại học', 1, '', 5),
('NV026', N'Phạm Thu Hà', '2002-05-20', 0, '0926234567', 'ha.pham@company.com', '2024-03-01', 5, 5, N'Kinh', '001202000026', N'Cục CSQLHC về trật tự xã hội', N'Cao đẳng', 1, '', 5),
('NV027', N'Hoàng Văn Khánh', '1994-11-30', 1, '0927234567', 'khanh.hoang@company.com', '2020-11-15', 2, 4, N'Kinh', '001094000027', N'Cục CSQLHC về trật tự xã hội', N'Đại học', 3, '', 5),
('NV028', N'Đỗ Thị Thúy', '1995-08-12', 0, '0928234567', 'thuy.do@company.com', '2021-07-01', 1, 4, N'Kinh', '001095000028', N'Cục CSQLHC về trật tự xã hội', N'Đại học', 3, '', 5),
('NV029', N'Nguyễn Văn Long', '1989-04-03', 1, '0929234567', 'long.nguyen@company.com', '2017-09-01', 4, 4, N'Kinh', '001089000029', N'Cục CSQLHC về trật tự xã hội', N'Đại học', 4, '', 5),
('NV030', N'Bùi Ánh Tuyết', '1993-09-27', 0, '0930234567', 'tuyet.bui@company.com', '2019-12-01', 5, 4, N'Kinh', '001093000030', N'Cục CSQLHC về trật tự xã hội', N'Đại học', 4, '', 5);