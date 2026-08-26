-- Dữ liệu mẫu active cho hợp đồng 15 bảng hiện hành.
-- Chạy sau database/sql/tao_bang.sql và trước database/sql/quyen_vai_tro.sql
-- trên cơ sở dữ liệu rỗng hoặc disposable đã được phê duyệt.

USE quan_ly_nhan_su;

-- Tạm tắt kiểm tra khóa ngoại
SET FOREIGN_KEY_CHECKS = 0;

-- ==================== 1. PhongBan ====================
INSERT INTO phong_ban (ma_pb, ten_pb) VALUES
(1, N'IT'),
(2, N'Ban giám đốc'),
(3, N'Nhân sự'),
(4, N'Kế toán'),
(5, N'Kinh doanh');
ALTER TABLE phong_ban AUTO_INCREMENT = 6;

-- ==================== 2. VaiTro ====================
INSERT INTO vai_tro (ma_vt, ten_vt, mo_ta) VALUES
(1, N'Quản trị', N'Quản trị toàn hệ thống'),
(2, N'Nhân sự', N'Quản lý nhân viên, quản lý hợp đồng, quản lý nghỉ phép, quản lý chấm công, xem bảng lương'),
(3, N'Kế toán', N'Quản lý tính lương'),
(4, N'Trưởng phòng', N'Duyệt nghỉ phép'),
(5, N'Nhân viên', N'Xem và cập nhật thông tin cá nhân');
ALTER TABLE vai_tro AUTO_INCREMENT = 6;

-- ==================== 3. TrangThaiLamViec ====================
INSERT INTO trang_thai_lam_viec (ma_tt, ten_tt) VALUES
(1, N'Đang làm việc'),
(2, N'Thử việc'),
(3, N'Thực tập'),
(4, N'Đã nghỉ việc'),
(5, N'Bị sa thải'),
(6, N'Nghỉ hưu');
ALTER TABLE trang_thai_lam_viec AUTO_INCREMENT = 7;

-- ==================== 4. ChucVu ====================
INSERT INTO chuc_vu (ma_cv, ten_cv, he_so_phu_cap) VALUES
(1, N'Giám đốc', 2.00),
(2, N'Phó giám đốc', 1.50),
(3, N'Trưởng phòng', 1.00),
(4, N'Phó phòng', 0.75),
(5, N'Tổ trưởng', 0.30),
(6, N'Nhân viên', 0.00);
ALTER TABLE chuc_vu AUTO_INCREMENT = 7;

-- ==================== 5. NhanVien ====================
INSERT INTO nhan_vien (ma_nv, ho_ten, ngay_sinh, gioi_tinh, sdt, email, ngay_vao_lam, ma_pb, ma_cv, dan_toc, cccd, noi_cap_cccd, hoc_van, ma_tt, mat_khau, ma_vt) VALUES
('00001', N'Nguyễn Văn An', '1979-12-11', 1, '0313645112', 'nguyenvanan@gmail.com', '2000-03-22', 1, 3, N'Kinh', '021334512155', N'Bộ Công an', N'Thạc sĩ', 1, 'A665A45920422F9D417E4867EFDC4FB8A04A1F3FFF1FA07E998E86F7F7A27AE3', 1),
('00002', N'Trần Thị Bình', '1993-07-20', 0, '0987654321', 'binh.tran@gmail.com', '2019-08-15', 2, 1, N'Kinh', '123456789012', N'Bộ Công an', N'Thạc sĩ', 1, 'A665A45920422F9D417E4867EFDC4FB8A04A1F3FFF1FA07E998E86F7F7A27AE3', 1),
('00003', N'Lê Văn Cường', '1990-01-10', 1, '0909123456', 'cuong.le@gmail.com', '2018-02-10', 2, 2, N'Kinh', '234567890123', N'Bộ Công an', N'Thạc sĩ', 1, 'A665A45920422F9D417E4867EFDC4FB8A04A1F3FFF1FA07E998E86F7F7A27AE3', 1),
('00004', N'Phạm Thị Dung', '1996-11-05', 0, '0933123456', 'dung.pham@gmail.com', '2021-09-20', 3, 6, N'Kinh', '345678901234', N'Bộ Công an', N'Đại học', 1, 'A665A45920422F9D417E4867EFDC4FB8A04A1F3FFF1FA07E998E86F7F7A27AE3', 2),
('00005', N'Hoàng Văn Em', '1992-12-25', 1, '0978123456', 'em.hoang@gmail.com', '2017-03-30', 3, 3, N'Tày', '456789012345', N'Bộ Công an', N'Đại học', 1, 'A665A45920422F9D417E4867EFDC4FB8A04A1F3FFF1FA07E998E86F7F7A27AE3', 4),
('00006', N'Đinh Văn Hùng', '1991-05-25', 1, '0945671234', 'hung.dinh@company.com', '2017-11-22', 4, 6, N'Kinh', '079208010122', N'Bộ Công an', N'Cao đẳng', 1, 'A665A45920422F9D417E4867EFDC4FB8A04A1F3FFF1FA07E998E86F7F7A27AE3', 3),
('00007', N'Vũ Thị Hạnh', '1998-02-09', 0, '0965123789', 'hanh.vu@company.com', '2020-07-12', 5, 6, N'Kinh', '079206008901', N'Bộ Công an', N'Đại học', 1, 'A665A45920422F9D417E4867EFDC4FB8A04A1F3FFF1FA07E998E86F7F7A27AE3', 5),
('00008', N'Nguyễn Thị Linh', '1996-10-14', 0, '0976543210', 'linh.nguyen@company.com', '2021-03-01', 5, 6, N'Kinh', '079208010123', N'Bộ Công an', N'Cao đẳng', 1, 'A665A45920422F9D417E4867EFDC4FB8A04A1F3FFF1FA07E998E86F7F7A27AE3', 5),
('00009', N'Đăng Văn Ngữ', '1990-11-10', 1, '0124441421', 'dangvanngu@gmail.com', '2020-10-22', 5, 6, N'Kinh', '012325484121', N'Bộ Công an', N'Đại học', 1, 'A665A45920422F9D417E4867EFDC4FB8A04A1F3FFF1FA07E998E86F7F7A27AE3', 5),
('00010', N'Phạm Quốc Bảo', '1994-06-18', 1, '0912345678', 'bao.pham@company.com', '2022-04-15', 1, 6, N'Kinh', '079301234567', N'Bộ Công an', N'Đại học', 1, 'A665A45920422F9D417E4867EFDC4FB8A04A1F3FFF1FA07E998E86F7F7A27AE3', 5),
('00011', N'Nguyễn Thị Mai', '1997-09-22', 0, '0987654321', 'mai.nguyen@company.com', '2021-08-10', 3, 6, N'Kinh', '079301234568', N'Bộ Công an', N'Đại học', 1, 'A665A45920422F9D417E4867EFDC4FB8A04A1F3FFF1FA07E998E86F7F7A27AE3', 2),
('00012', N'Trần Minh Đức', '1988-03-05', 1, '0934567890', 'duc.tran@company.com', '2018-01-12', 4, 6, N'Kinh', '079301234569', N'Bộ Công an', N'Thạc sĩ', 1, 'A665A45920422F9D417E4867EFDC4FB8A04A1F3FFF1FA07E998E86F7F7A27AE3', 5),
('00013', N'Lê Thị Thu Hà', '1995-11-14', 0, '0978123456', 'ha.le@company.com', '2020-07-20', 5, 6, N'Kinh', '079301234570', N'Bộ Công an', N'Đại học', 1, 'A665A45920422F9D417E4867EFDC4FB8A04A1F3FFF1FA07E998E86F7F7A27AE3', 5),
('00014', N'Võ Thanh Tùng', '1992-02-28', 1, '0961234567', 'tung.vo@company.com', '2019-09-05', 1, 6, N'Kinh', '079301234571', N'Bộ Công an', N'Cao đẳng', 1, 'A665A45920422F9D417E4867EFDC4FB8A04A1F3FFF1FA07E998E86F7F7A27AE3', 5),
('00015', N'Đặng Ngọc Lan', '1996-08-17', 0, '0945678901', 'lan.dang@company.com', '2021-03-18', 4, 6, N'Kinh', '079301234572', N'Bộ Công an', N'Đại học', 1, 'A665A45920422F9D417E4867EFDC4FB8A04A1F3FFF1FA07E998E86F7F7A27AE3', 3),
('00016', N'Bùi Văn Khánh', '1989-12-09', 1, '0923456789', 'khanh.bui@company.com', '2017-06-01', 5, 6, N'Kinh', '079301234573', N'Bộ Công an', N'Thạc sĩ', 1, 'A665A45920422F9D417E4867EFDC4FB8A04A1F3FFF1FA07E998E86F7F7A27AE3', 5),
('00017', N'Phan Thị Ngọc Ánh', '1998-05-25', 0, '0956789012', 'anh.phan@company.com', '2022-11-22', 3, 6, N'Kinh', '079301234574', N'Bộ Công an', N'Đại học', 1, 'A665A45920422F9D417E4867EFDC4FB8A04A1F3FFF1FA07E998E86F7F7A27AE3', 5),
('00018', N'Hoàng Đức Long', '1993-01-30', 1, '0901234567', 'long.hoang@company.com', '2019-02-14', 5, 6, N'Kinh', '079301234575', N'Bộ Công an', N'Đại học', 1, 'A665A45920422F9D417E4867EFDC4FB8A04A1F3FFF1FA07E998E86F7F7A27AE3', 5),
('00019', N'Vũ Thị Hương', '1994-07-11', 0, '0998765432', 'huong.vu@company.com', '2020-05-08', 5, 6, N'Tày', '079301234576', N'Bộ Công an', N'Cao đẳng', 1, 'A665A45920422F9D417E4867EFDC4FB8A04A1F3FFF1FA07E998E86F7F7A27AE3', 5);

-- Bộ đếm phải khớp mã lớn nhất đã cấp để lần tạo tiếp theo nhận 00020.
INSERT INTO bo_dem_ma_nhan_vien (ten_bo_dem, so_da_cap) VALUES ('NHAN_VIEN', 19);

-- ==================== 6. Quyen ====================
INSERT INTO quyen (ma_quyen, ky_hieu_quyen, ten_quyen, module) VALUES
(1, N'VaiTro.Read', N'Đọc', N'VaiTro'),
(2, N'VaiTro.Insert', N'Thêm', N'VaiTro'),
(3, N'VaiTro.Update', N'Sửa', N'VaiTro'),
(4, N'VaiTro.Delete', N'Xóa', N'VaiTro'),
(5, N'PhanQuyen.Read', N'Đọc', N'PhanQuyen'),
(6, N'PhanQuyen.Insert', N'Thêm', N'PhanQuyen'),
(7, N'PhanQuyen.Update', N'Sửa', N'PhanQuyen'),
(8, N'PhanQuyen.Delete', N'Xóa', N'PhanQuyen'),
(9, N'PhongBan.Read', N'Đọc', N'PhongBan'),
(10, N'PhongBan.Insert', N'Thêm', N'PhongBan'),
(11, N'PhongBan.Update', N'Sửa', N'PhongBan'),
(12, N'PhongBan.Delete', N'Xóa', N'PhongBan'),
(13, N'ChucVu.Read', N'Đọc', N'ChucVu'),
(14, N'ChucVu.Insert', N'Thêm', N'ChucVu'),
(15, N'ChucVu.Update', N'Sửa', N'ChucVu'),
(16, N'ChucVu.Delete', N'Xóa', N'ChucVu'),
(17, N'NhanVien.Read', N'Đọc', N'NhanVien'),
(18, N'NhanVien.Insert', N'Thêm', N'NhanVien'),
(19, N'NhanVien.Update', N'Sửa', N'NhanVien'),
(20, N'NhanVien.Delete', N'Xóa', N'NhanVien'),
(21, N'HopDong.Read', N'Đọc', N'HopDong'),
(22, N'HopDong.Insert', N'Thêm', N'HopDong'),
(23, N'HopDong.Update', N'Sửa', N'HopDong'),
(24, N'HopDong.Delete', N'Xóa', N'HopDong'),
(25, N'NghiPhep.Read', N'Đọc', N'NghiPhep'),
(26, N'NghiPhep.Insert', N'Thêm', N'NghiPhep'),
(27, N'NghiPhep.Update', N'Sửa', N'NghiPhep'),
(28, N'NghiPhep.Delete', N'Xóa', N'NghiPhep'),
(29, N'ChamCong.Read', N'Đọc', N'ChamCong'),
(30, N'ChamCong.Insert', N'Thêm', N'ChamCong'),
(31, N'ChamCong.Update', N'Sửa', N'ChamCong'),
(32, N'ChamCong.Delete', N'Xóa', N'ChamCong'),
(33, N'Luong.Read', N'Đọc', N'Luong'),
(34, N'Luong.Insert', N'Thêm', N'Luong'),
(35, N'Luong.Update', N'Sửa', N'Luong'),
(36, N'Luong.Delete', N'Xóa', N'Luong'),
(37, N'HeThong.Config', N'Cấu hình', N'HeThong');
ALTER TABLE quyen AUTO_INCREMENT = 38;

-- ==================== 7. VaiTroQuyen ====================
INSERT INTO vai_tro_quyen (ma_vt, ma_quyen) VALUES
(1, 1),(1, 2),(1, 3),(1, 4),(1, 5),(1, 6),(1, 7),(1, 8),(1, 9),(1, 10),(1, 11),(1, 12),(1, 13),(1, 14),(1, 15),
(1, 16),(1, 17),(1, 18),(1, 19),(1, 20),(1, 21),(1, 22),(1, 23),(1, 24),(1, 25),(1, 26),(1, 27),(1, 28),(1, 29),(1, 30),(1, 31),(1, 32),(1, 33),(1, 34),(1, 35),
(1, 36),(1, 37),
(2, 9),(2, 10),(2, 11),(2, 12),(2, 13),(2, 14),(2, 15),(2, 16),(2, 17),(2, 18),(2, 19),(2, 20),(2, 21),(2, 22),(2, 23),(2, 24),(2, 25),(2, 26),
(2, 28),(2, 29),(2, 30),(2, 31),(2, 32),(2, 33),(2, 34),(2, 35),(2, 36),
(3, 17),(3, 21),(3, 25),(3, 29),(3, 33),
(4, 17),(4, 21),(4, 25),(4, 27),(4, 29);

-- ==================== 8. LoaiHopDong ====================
INSERT INTO loai_hop_dong (ma_lhd, ten_lhd) VALUES
(1, N'Hợp đồng lao động không xác định thời hạn'),
(2, N'Hợp đồng lao động xác định thời hạn'),
(3, N'Hợp đồng lao động khoán'),
(4, N'Hợp đồng thời vụ'),
(5, N'Hợp đồng thử việc');
ALTER TABLE loai_hop_dong AUTO_INCREMENT = 6;

-- ==================== 9. HopDong ====================
INSERT INTO hop_dong (ma_hd, ma_nv, ma_lhd, ngay_ky, ngay_het_han, luong_co_ban) VALUES
(1, '00001', 1, '2022-01-10', NULL, 2350000),
(2, '00002', 1, '2023-12-11', NULL, 2350000),
(3, '00003', 4, '2026-01-12', '2026-12-12', 2350000),
(4, '00004', 1, '2020-05-04', NULL, 2350000),
(5, '00005', 1, '2016-09-05', NULL, 2350000),
(6, '00006', 1, '2020-11-22', NULL, 2350000),
(7, '00007', 1, '2021-08-07', NULL, 2350000),
(8, '00008', 1, '2023-07-22', NULL, 2350000),
(9, '00009', 1, '2023-10-09', NULL, 2350000),
(10, '00010', 2, '2022-06-16', '2026-06-15', 2350000),
(11, '00011', 2, '2024-06-16', '2027-06-15', 2350000),
(12, '00012', 1, '2018-01-12', NULL, 2350000),
(13, '00013', 2, '2020-07-20', '2027-07-19', 2350000),
(14, '00014', 1, '2019-09-05', NULL, 2350000),
(15, '00015', 2, '2023-03-18', '2027-03-17', 2350000),
(16, '00016', 1, '2017-06-01', NULL, 2350000),
(17, '00017', 3, '2024-11-22', '2027-01-22', 1500000),
(18, '00018', 1, '2019-02-14', NULL, 2350000),
(19, '00019', 4, '2026-04-08', '2026-07-08', 1200000);
ALTER TABLE hop_dong AUTO_INCREMENT = 20;

-- ==================== 10. LoaiPhep ====================
INSERT INTO loai_phep (ma_lp, ten_lp) VALUES
(1, N'Nghỉ phép năm'),
(2, N'Nghỉ ốm'),
(3, N'Nghỉ thai sản'),
(4, N'Nghỉ hiếu/hỉ'),
(5, N'Nghỉ không lương');
ALTER TABLE loai_phep AUTO_INCREMENT = 6;

-- ==================== 11. NghiPhep ====================
INSERT INTO nghi_phep (ma_np, ma_nv, tu_ngay, den_ngay, ma_lp, ly_do, trang_thai_duyet) VALUES
(1, '00002', '2026-05-20', '2026-05-20', 1, N'Đi du lịch', 2),
(2, '00002', '2026-05-21', '2026-05-21', 1, N'bận việc gia đình', 1),
(3, '00003', '2026-05-18', '2026-05-18', 4, N'Bận việc gia đình', 1),
(4, '00003', '2026-05-20', '2026-05-20', 5, N'Bận việc gia đình', 1),
(5, '00004', '2026-05-22', '2026-05-31', 3, N'nghỉ thai sản', 1),
(6, '00002', '2026-05-01', '2026-05-01', 1, N'Bận việc gia đình', 2),
(7, '00001', '2026-06-01', '2026-06-01', 2, N'Bị bệnh', 1);
ALTER TABLE nghi_phep AUTO_INCREMENT = 8;

-- ==================== 12. LichSuHeSoLuong ====================
INSERT INTO lich_su_he_so_luong (ma_ls, ma_nv, he_so_luong, tu_ngay, den_ngay) VALUES
(1, '00001', 2.34, '2024-01-11', '2027-01-11'),
(2, '00002', 2.34, '2025-05-01', '2027-06-01'),
(3, '00003', 3.66, '2024-10-04', '2027-10-04'),
(4, '00004', 3.00, '2019-05-28', '2027-05-27'),
(5, '00005', 2.00, '2016-09-24', '2019-09-24'),
(6, '00005', 3.33, '2019-09-25', '2022-09-25'),
(7, '00006', 2.34, '2023-09-01', '2026-09-01'),
(8, '00007', 2.66, '2023-09-01', '2026-09-01'),
(9, '00008', 3.66, '2023-09-01', '2026-09-01'),
(10, '00009', 2.34, '2023-09-01', '2026-09-01'),
(11, '00010', 3.00, '2024-04-15', '2027-04-15'),
(12, '00011', 3.00, '2024-08-10', '2027-08-10'),
(13, '00012', 3.66, '2024-01-12', '2027-01-12'),
(14, '00013', 3.00, '2024-07-20', '2027-07-20'),
(15, '00014', 2.34, '2025-09-05', '2028-09-05'),
(16, '00015', 3.00, '2025-03-18', '2028-03-18'),
(17, '00016', 3.66, '2025-06-01', '2028-06-01'),
(18, '00017', 3.00, '2024-11-22', '2026-11-22'),
(19, '00018', 3.00, '2025-02-14', '2028-02-14'),
(20, '00019', 2.34, '2025-05-08', '2028-05-08');
ALTER TABLE lich_su_he_so_luong AUTO_INCREMENT = 21;

-- ==================== 13. Luong ====================
INSERT INTO luong (ma_luong, ma_nv, ky_luong, thuong, phat, bao_hiem, thue) VALUES
(1, '00001', '2026-05-01', 50000, 0, 10000, 0),
(2, '00002', '2026-05-01', 500000, 0, 100000, 10000),
(3, '00003', '2026-05-01', 100000, 0, 100000, 10000),
(4, '00004', '2026-05-01', 50000, 0, 100000, 20000),
(5, '00005', '2026-05-01', 100000, 10000, 100000, 25000),
(6, '00006', '2026-05-01', 10000, 50000, 100000, 100000),
(7, '00007', '2026-05-01', 0, 0, 100000, 60000),
(8, '00008', '2026-05-01', 10000, 0, 100000, 80000),
(9, '00009', '2026-05-01', 10000, 0, 100000, 80000),
(10, '00010', '2026-05-01', 300000, 0, 450000, 200000),
(11, '00011', '2026-05-01', 450000, 50000, 500000, 350000),
(12, '00012', '2026-05-01', 500000, 0, 500000, 500000),
(13, '00013', '2026-05-01', 250000, 100000, 450000, 250000),
(14, '00014', '2026-05-01', 400000, 0, 500000, 300000),
(15, '00015', '2026-05-01', 350000, 50000, 450000, 250000),
(16, '00016', '2026-05-01', 500000, 0, 500000, 500000),
(17, '00017', '2026-05-01', 200000, 0, 300000, 100000),
(18, '00018', '2026-05-01', 450000, 50000, 500000, 450000),
(19, '00019', '2026-05-01', 150000, 100000, 250000, 50000);
ALTER TABLE luong AUTO_INCREMENT = 20;

-- Bật lại kiểm tra khóa ngoại
SET FOREIGN_KEY_CHECKS = 1;
