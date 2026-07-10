-- ============================================================
-- TẠO DATABASE
-- ============================================================
DROP DATABASE IF EXISTS quan_ly_nhan_su;
CREATE DATABASE quan_ly_nhan_su CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

USE quan_ly_nhan_su;

-- ============================================================
-- TẠO BẢNG
-- ============================================================

/* --------------------------------------
   Bảng phòng ban
   -------------------------------------- */
CREATE TABLE phong_ban (
    ma_pb INT AUTO_INCREMENT PRIMARY KEY,
    ten_pb NVARCHAR(100) NOT NULL
);

/* --------------------------------------
   Bảng chức vụ
   -------------------------------------- */
CREATE TABLE chuc_vu (
    ma_cv INT AUTO_INCREMENT PRIMARY KEY,
    ten_cv NVARCHAR(100) NOT NULL,
    he_so_phu_cap DECIMAL(5, 2) NOT NULL
);

/* --------------------------------------
   Bảng vai trò
   -------------------------------------- */
CREATE TABLE vai_tro (
    ma_vt INT AUTO_INCREMENT PRIMARY KEY,
    ten_vt NVARCHAR(100) NOT NULL,
    mo_ta NVARCHAR(255) NULL
);

/* --------------------------------------
   Bảng quyền
   -------------------------------------- */
CREATE TABLE quyen (
    ma_quyen INT PRIMARY KEY,
    ky_hieu_quyen NVARCHAR(100) NOT NULL,
    ten_quyen NVARCHAR(50) NOT NULL,
    module NVARCHAR(50) NOT NULL
);

/* --------------------------------------
   Bảng vai trò quyền
   -------------------------------------- */
CREATE TABLE vai_tro_quyen (
    ma_vt INT NOT NULL,
    ma_quyen INT NOT NULL,
    PRIMARY KEY (ma_vt, ma_quyen),
    CONSTRAINT fk_vai_tro_quyen_quyen FOREIGN KEY (ma_quyen) REFERENCES quyen(ma_quyen),
    CONSTRAINT fk_vai_tro_quyen_vai_tro FOREIGN KEY (ma_vt) REFERENCES vai_tro(ma_vt)
);

/* --------------------------------------
   Bảng trạng thái làm việc
   -------------------------------------- */
CREATE TABLE trang_thai_lam_viec (
    ma_tt TINYINT AUTO_INCREMENT PRIMARY KEY,
    ten_tt NVARCHAR(50) NOT NULL
);

/* --------------------------------------
   Bảng nhân viên
   -------------------------------------- */
CREATE TABLE nhan_vien (
    ma_nv VARCHAR(5) PRIMARY KEY,
    ho_ten NVARCHAR(50) NOT NULL,
    ngay_sinh DATE NOT NULL,
    gioi_tinh TINYINT NOT NULL,
    sdt VARCHAR(15) NOT NULL,
    email NVARCHAR(100) NOT NULL,
    ngay_vao_lam DATE NOT NULL,
    ma_pb INT NOT NULL,
    ma_cv INT NOT NULL,
    dan_toc NVARCHAR(50) NOT NULL,
    cccd VARCHAR(12) NOT NULL,
    noi_cap_cccd NVARCHAR(50) NOT NULL,
    hoc_van NVARCHAR(50) NOT NULL,
    ma_tt TINYINT NOT NULL,
    mat_khau VARCHAR(255) NOT NULL,
    ma_vt INT NOT NULL,
    CONSTRAINT fk_nhan_vien_chuc_vu FOREIGN KEY (ma_cv) REFERENCES chuc_vu(ma_cv),
    CONSTRAINT fk_nhan_vien_phong_ban FOREIGN KEY (ma_pb) REFERENCES phong_ban(ma_pb),
    CONSTRAINT fk_nhan_vien_trang_thai_lam_viec FOREIGN KEY (ma_tt) REFERENCES trang_thai_lam_viec(ma_tt),
    CONSTRAINT fk_nhan_vien_vai_tro FOREIGN KEY (ma_vt) REFERENCES vai_tro(ma_vt)
);

/* --------------------------------------
   Bảng loại hợp đồng
   -------------------------------------- */
CREATE TABLE loai_hop_dong (
    ma_lhd INT AUTO_INCREMENT PRIMARY KEY,
    ten_lhd NVARCHAR(255) NOT NULL
);

/* --------------------------------------
   Bảng hợp đồng
   -------------------------------------- */
CREATE TABLE hop_dong (
    ma_hd INT AUTO_INCREMENT PRIMARY KEY,
    ma_nv VARCHAR(5) NOT NULL,
    ma_lhd INT NOT NULL,
    ngay_ky DATE NOT NULL,
    ngay_het_han DATE NULL,
    luong_co_ban DECIMAL(18, 0) NOT NULL,
    CONSTRAINT fk_hop_dong_loai_hop_dong FOREIGN KEY (ma_lhd) REFERENCES loai_hop_dong(ma_lhd),
    CONSTRAINT fk_hop_dong_nhan_vien FOREIGN KEY (ma_nv) REFERENCES nhan_vien(ma_nv)
);

/* --------------------------------------
   Bảng loại phép
   -------------------------------------- */
CREATE TABLE loai_phep (
    ma_lp INT AUTO_INCREMENT PRIMARY KEY,
    ten_lp NVARCHAR(255) NOT NULL
);

/* --------------------------------------
   Bảng nghỉ phép
   -------------------------------------- */
CREATE TABLE nghi_phep (
    ma_np INT AUTO_INCREMENT PRIMARY KEY,
    ma_nv VARCHAR(5) NOT NULL,
    tu_ngay DATE NOT NULL,
    den_ngay DATE NOT NULL,
    ma_lp INT NOT NULL,
    ly_do NVARCHAR(255) NOT NULL,
    trang_thai_duyet TINYINT NOT NULL,
    CONSTRAINT fk_nghi_phep_loai_phep FOREIGN KEY (ma_lp) REFERENCES loai_phep(ma_lp),
    CONSTRAINT fk_nghi_phep_nhan_vien FOREIGN KEY (ma_nv) REFERENCES nhan_vien(ma_nv),
    CONSTRAINT ck_nghi_phep_ngay CHECK (den_ngay >= tu_ngay)
);

/* --------------------------------------
   Bảng chấm công
   -------------------------------------- */
CREATE TABLE cham_cong (
    ma_cc INT AUTO_INCREMENT PRIMARY KEY,
    ma_nv VARCHAR(5) NOT NULL,
    ngay_lam DATE NOT NULL,
    so_gio_lam SMALLINT NOT NULL,
    vao_muon BIT NOT NULL,
    ve_som BIT NOT NULL,
    CONSTRAINT fk_cham_cong_nhan_vien FOREIGN KEY (ma_nv) REFERENCES nhan_vien(ma_nv),
    CONSTRAINT uq_cham_cong_ma_nv_ngay_lam UNIQUE (ma_nv, ngay_lam),
    CONSTRAINT ck_cham_cong_vao_muon CHECK (vao_muon IN (0, 1)),
    CONSTRAINT ck_cham_cong_ve_som CHECK (ve_som IN (0, 1))
);

/* --------------------------------------
   Bảng lịch sử hệ số lương
   -------------------------------------- */
CREATE TABLE lich_su_he_so_luong (
    ma_ls INT AUTO_INCREMENT PRIMARY KEY,
    ma_nv VARCHAR(5) NOT NULL,
    he_so_luong DECIMAL(5, 2) NOT NULL,
    tu_ngay DATE NOT NULL,
    den_ngay DATE NOT NULL,
    CONSTRAINT fk_lich_su_he_so_luong_nhan_vien FOREIGN KEY (ma_nv) REFERENCES nhan_vien(ma_nv),
    CONSTRAINT ck_lshsl_tu_ngay_den_ngay CHECK (tu_ngay <= den_ngay)
);

/* --------------------------------------
   Bảng lương
   -------------------------------------- */
CREATE TABLE luong (
    ma_luong INT AUTO_INCREMENT PRIMARY KEY,
    ma_nv VARCHAR(5) NOT NULL,
    ky_luong DATE NOT NULL,
    thuong DECIMAL(18, 0) NULL,
    phat DECIMAL(18, 0) NULL,
    bao_hiem DECIMAL(18, 0) NULL,
    thue DECIMAL(18, 0) NULL,
    CONSTRAINT fk_luong_nhan_vien FOREIGN KEY (ma_nv) REFERENCES nhan_vien(ma_nv)
);

-- ============================================================
-- VIEW
-- ============================================================

/* --------------------------------------
   Danh sách nhân viên chi tiết
   -------------------------------------- */
DROP VIEW IF EXISTS vw_danh_sach_nhan_vien_chi_tiet;

CREATE VIEW vw_danh_sach_nhan_vien_chi_tiet AS
SELECT nv.ma_nv, nv.ho_ten, nv.ngay_sinh, nv.gioi_tinh, 
    CASE nv.gioi_tinh WHEN 1 THEN N'Nam' WHEN 0 THEN N'Nữ' ELSE N'Khác' END AS gioi_tinh_hien_thi,
    nv.sdt, nv.email, nv.ngay_vao_lam, nv.ma_pb, pb.ten_pb, nv.ma_cv, cv.ten_cv, cv.he_so_phu_cap, 
    nv.dan_toc, nv.cccd, nv.noi_cap_cccd, nv.hoc_van,
    nv.ma_tt, ttlv.ten_tt, nv.mat_khau, nv.ma_vt, vt.ten_vt 
FROM nhan_vien nv
LEFT JOIN phong_ban pb ON pb.ma_pb = nv.ma_pb
LEFT JOIN chuc_vu cv ON cv.ma_cv = nv.ma_cv
LEFT JOIN trang_thai_lam_viec ttlv ON ttlv.ma_tt = nv.ma_tt
LEFT JOIN vai_tro vt ON vt.ma_vt = nv.ma_vt;

-- ============================================================
-- FUNCTION
-- ============================================================

DELIMITER $$

/* ---------------------------------------------------------
   Đếm nhân viên theo phòng ban
   --------------------------------------------------------- */
DROP FUNCTION IF EXISTS fn_dem_nhan_vien_theo_phong_ban;

CREATE FUNCTION fn_dem_nhan_vien_theo_phong_ban(p_ma_pb INT)
RETURNS INT
DETERMINISTIC
BEGIN
    DECLARE v_so_luong INT;
    SELECT COUNT(*) INTO v_so_luong FROM nhan_vien WHERE ma_pb = p_ma_pb;
    RETURN IFNULL(v_so_luong, 0);
END$$

DELIMITER ;

/* ---------------------------------------------------------
   Đếm nhân viên theo chức vụ
   --------------------------------------------------------- */
DROP FUNCTION IF EXISTS fn_dem_nhan_vien_theo_chuc_vu//

CREATE FUNCTION fn_dem_nhan_vien_theo_chuc_vu(p_ma_cv INT)
RETURNS INT
DETERMINISTIC
BEGIN
    DECLARE v_so_luong INT;
    SELECT COUNT(*) INTO v_so_luong FROM nhan_vien WHERE ma_cv = p_ma_cv;
    RETURN IFNULL(v_so_luong, 0);
END//

/* --------------------------------------
   Tính số ngày công chuẩn
   -------------------------------------- */
DROP FUNCTION IF EXISTS fn_so_ngay_cong_chuan//

CREATE FUNCTION fn_so_ngay_cong_chuan(p_ma_nv VARCHAR(5), p_ky_luong DATE)
RETURNS INT
DETERMINISTIC
BEGIN
    DECLARE v_ky DATE;
    DECLARE v_ngay_dau_thang DATE;
    DECLARE v_ngay_cuoi_thang DATE;
    DECLARE v_so_ngay_cong_chuan INT;
    SET v_ky = DATE_FORMAT(p_ky_luong, '%Y-%m-01');
    SET v_ngay_dau_thang = v_ky;
    SET v_ngay_cuoi_thang = LAST_DAY(v_ky);
    SELECT COUNT(*) INTO v_so_ngay_cong_chuan 
    FROM cham_cong 
    WHERE ma_nv = p_ma_nv 
        AND ngay_lam BETWEEN v_ngay_dau_thang AND v_ngay_cuoi_thang
        AND IFNULL(so_gio_lam, 0) > 0;
    RETURN IFNULL(v_so_ngay_cong_chuan, 0);
END//

/* --------------------------------------
   Tính số ngày công thực tế
   -------------------------------------- */
DROP FUNCTION IF EXISTS fn_so_ngay_cong_thuc_te//

CREATE FUNCTION fn_so_ngay_cong_thuc_te(p_ma_nv VARCHAR(5), p_ky_luong DATE)
RETURNS DECIMAL(10,2)
DETERMINISTIC
BEGIN
    DECLARE v_ky DATE;
    DECLARE v_ngay_dau_thang DATE;
    DECLARE v_ngay_cuoi_thang DATE;
    DECLARE v_so_ngay_cong_thuc_te DECIMAL(10,2);
    SET v_ky = DATE_FORMAT(p_ky_luong, '%Y-%m-01');
    SET v_ngay_dau_thang = v_ky;
    SET v_ngay_cuoi_thang = LAST_DAY(v_ky);
    SELECT SUM(
        CASE 
            WHEN IFNULL(so_gio_lam, 0) >= 8 THEN 1.0 
            WHEN IFNULL(so_gio_lam, 0) >= 4 THEN 0.5 
            ELSE 0 
        END
    ) INTO v_so_ngay_cong_thuc_te
    FROM cham_cong 
    WHERE ma_nv = p_ma_nv AND ngay_lam BETWEEN v_ngay_dau_thang AND v_ngay_cuoi_thang;
    RETURN IFNULL(v_so_ngay_cong_thuc_te, 0);
END//

/* --------------------------------------
   Tính lương thực nhận
   -------------------------------------- */
DROP FUNCTION IF EXISTS fn_tinh_luong_thuc_nhan//

CREATE FUNCTION fn_tinh_luong_thuc_nhan(p_ma_nv VARCHAR(5), p_ky_luong DATE)
RETURNS DECIMAL(18,0)
DETERMINISTIC
BEGIN
    DECLARE v_luong_co_ban DECIMAL(18,2);
    DECLARE v_ky DATE;
    DECLARE v_he_so_luong DECIMAL(18,2);
    DECLARE v_so_ngay_cong_chuan INT;
    DECLARE v_so_ngay_cong_thuc_te DECIMAL(10,2);
    DECLARE v_phu_cap_chuc_vu DECIMAL(18,2);
    DECLARE v_thuong DECIMAL(18,2);
    DECLARE v_phat DECIMAL(18,2);
    DECLARE v_bao_hiem DECIMAL(18,2);
    DECLARE v_thue DECIMAL(18,2);
    DECLARE v_luong_theo_ngay_cong DECIMAL(18,2);
    DECLARE v_thuc_nhan DECIMAL(18,0);
    
    SET v_ky = DATE_FORMAT(p_ky_luong, '%Y-%m-01');
    
    -- Lấy lương cơ bản từ hợp đồng còn hiệu lực
    SELECT luong_co_ban INTO v_luong_co_ban 
    FROM hop_dong 
    WHERE ma_nv = p_ma_nv AND p_ky_luong BETWEEN ngay_ky AND IFNULL(ngay_het_han, p_ky_luong)
    ORDER BY ngay_ky DESC 
    LIMIT 1;
    
    -- Lấy hệ số lương
    SELECT he_so_luong INTO v_he_so_luong 
    FROM lich_su_he_so_luong 
    WHERE ma_nv = p_ma_nv AND p_ky_luong BETWEEN tu_ngay AND IFNULL(den_ngay, p_ky_luong)
    ORDER BY tu_ngay DESC 
    LIMIT 1;
    
    IF v_luong_co_ban IS NULL THEN RETURN 0; END IF;
    IF v_he_so_luong IS NULL THEN RETURN 0; END IF;
    
    -- Tính số ngày công chuẩn và số ngày công thực tế
    SET v_so_ngay_cong_chuan = fn_so_ngay_cong_chuan(p_ma_nv, v_ky);
    SET v_so_ngay_cong_thuc_te = fn_so_ngay_cong_thuc_te(p_ma_nv, v_ky);
    
    IF v_so_ngay_cong_chuan IS NULL OR v_so_ngay_cong_chuan = 0 THEN RETURN 0; END IF;
    
    -- Lấy phụ cấp chức vụ
    SELECT IFNULL(cv.he_so_phu_cap, 0) INTO v_phu_cap_chuc_vu 
    FROM nhan_vien nv
    JOIN chuc_vu cv ON nv.ma_cv = cv.ma_cv
    WHERE nv.ma_nv = p_ma_nv;
    SET v_phu_cap_chuc_vu = IFNULL(v_phu_cap_chuc_vu, 0);
    
    -- Lấy thưởng, phạt, bảo hiểm, thuế từ bảng lương
    SELECT IFNULL(thuong, 0), IFNULL(phat, 0), IFNULL(bao_hiem, 0), IFNULL(thue, 0) 
    INTO v_thuong, v_phat, v_bao_hiem, v_thue
    FROM luong
    WHERE ma_nv = p_ma_nv AND ky_luong = v_ky;
    
    SET v_thuong = IFNULL(v_thuong, 0);
    SET v_phat = IFNULL(v_phat, 0);
    SET v_bao_hiem = IFNULL(v_bao_hiem, 0);
    SET v_thue = IFNULL(v_thue, 0);
    
    -- Tính lương theo ngày công
    SET v_luong_theo_ngay_cong = (v_luong_co_ban / v_so_ngay_cong_chuan) * v_so_ngay_cong_thuc_te * v_he_so_luong;
    
    -- Tính lương thực nhận
    SET v_thuc_nhan = v_luong_theo_ngay_cong + v_phu_cap_chuc_vu * v_luong_co_ban + v_thuong - v_phat - v_thue - v_bao_hiem;
    
    RETURN v_thuc_nhan;
END//

/* ---------------------------------------------------------
   Tính số lần vào muộn
   --------------------------------------------------------- */
DROP FUNCTION IF EXISTS fn_tinh_so_lan_vao_muon//

CREATE FUNCTION fn_tinh_so_lan_vao_muon(p_ma_nv VARCHAR(5), p_ky_luong DATE)
RETURNS INT
DETERMINISTIC
BEGIN
    DECLARE v_ky DATE;
    DECLARE v_so_lan_vao_muon INT;
    SET v_ky = DATE_FORMAT(p_ky_luong, '%Y-%m-01');
    
    SELECT COUNT(*) INTO v_so_lan_vao_muon 
    FROM nhan_vien nv 
    JOIN cham_cong cc ON nv.ma_nv = cc.ma_nv
    WHERE nv.ma_nv = p_ma_nv 
        AND vao_muon = 1 
        AND v_ky = DATE_FORMAT(cc.ngay_lam, '%Y-%m-01');
    
    RETURN IFNULL(v_so_lan_vao_muon, 0);
END//

/* ---------------------------------------------------------
   Tính số lần về sớm
   --------------------------------------------------------- */
DROP FUNCTION IF EXISTS fn_tinh_so_lan_ve_som//

CREATE FUNCTION fn_tinh_so_lan_ve_som(p_ma_nv VARCHAR(5), p_ky_luong DATE)
RETURNS INT
DETERMINISTIC
BEGIN
    DECLARE v_ky DATE;
    DECLARE v_so_lan_ve_som INT;
    SET v_ky = DATE_FORMAT(p_ky_luong, '%Y-%m-01');
    
    SELECT COUNT(*) INTO v_so_lan_ve_som 
    FROM nhan_vien nv 
    JOIN cham_cong cc ON nv.ma_nv = cc.ma_nv
    WHERE nv.ma_nv = p_ma_nv 
        AND ve_som = 1 
        AND v_ky = DATE_FORMAT(cc.ngay_lam, '%Y-%m-01');
    
    RETURN IFNULL(v_so_lan_ve_som, 0);
END//

/* ---------------------------------------------------------
   Tính số ngày công
   --------------------------------------------------------- */
DROP FUNCTION IF EXISTS fn_tinh_so_ngay_cong//

CREATE FUNCTION fn_tinh_so_ngay_cong(p_ma_nv VARCHAR(5), p_ky_luong DATE)
RETURNS DECIMAL(10, 2)
DETERMINISTIC
BEGIN
    DECLARE v_ky DATE;
    DECLARE v_so_ngay_cong DECIMAL(10, 2);
    SET v_ky = DATE_FORMAT(p_ky_luong, '%Y-%m-01');
    
    SELECT SUM(
        CASE 
            WHEN cc.so_gio_lam = 8 THEN 1.0 
            WHEN cc.so_gio_lam = 4 THEN 0.5 
            WHEN cc.so_gio_lam = 0 THEN 0 
            ELSE 0 
        END
    ) INTO v_so_ngay_cong
    FROM nhan_vien nv 
    JOIN cham_cong cc ON nv.ma_nv = cc.ma_nv
    WHERE nv.ma_nv = p_ma_nv 
        AND v_ky = DATE_FORMAT(cc.ngay_lam, '%Y-%m-01');
    
    RETURN IFNULL(v_so_ngay_cong, 0);
END//

DELIMITER ;

-- ============================================================
-- TRIGGER
-- ============================================================

DELIMITER //

/* --------------------------------------
   Kiểm tra trùng tên loại hợp đồng
   -------------------------------------- */
DROP TRIGGER IF EXISTS trg_kiem_tra_trung_ten_loai_hop_dong//

CREATE TRIGGER trg_kiem_tra_trung_ten_loai_hop_dong
BEFORE INSERT ON loai_hop_dong
FOR EACH ROW
BEGIN
    IF EXISTS (SELECT 1 FROM loai_hop_dong WHERE ten_lhd = NEW.ten_lhd) THEN
        SIGNAL SQLSTATE '45000' 
        SET MESSAGE_TEXT = N'Tên loại hợp đồng đã tồn tại. Vui lòng chọn tên khác.';
    END IF;
END//

DROP TRIGGER IF EXISTS trg_kiem_tra_trung_ten_loai_hop_dong_update//

CREATE TRIGGER trg_kiem_tra_trung_ten_loai_hop_dong_update
BEFORE UPDATE ON loai_hop_dong
FOR EACH ROW
BEGIN
    IF EXISTS (SELECT 1 FROM loai_hop_dong WHERE ten_lhd = NEW.ten_lhd AND ma_lhd <> NEW.ma_lhd) THEN
        SIGNAL SQLSTATE '45000' 
        SET MESSAGE_TEXT = N'Tên loại hợp đồng đã tồn tại. Vui lòng chọn tên khác.';
    END IF;
END//

/* --------------------------------------
   Trùng lặp hệ số lương
   -------------------------------------- */
DROP TRIGGER IF EXISTS trg_trung_lap_he_so_luong//

CREATE TRIGGER trg_trung_lap_he_so_luong
BEFORE INSERT ON lich_su_he_so_luong
FOR EACH ROW
BEGIN
    -- Cặp thời gian phải hợp lệ
    IF NEW.tu_ngay >= NEW.den_ngay THEN
        SIGNAL SQLSTATE '45000' 
        SET MESSAGE_TEXT = N'Từ ngày phải nhỏ hơn đến ngày.';
    END IF;
    
    -- Không trùng/chồng lấn thời gian với giai đoạn khác
    IF EXISTS (
        SELECT 1 FROM lich_su_he_so_luong 
        WHERE ma_nv = NEW.ma_nv 
            AND ma_ls <> NEW.ma_ls 
            AND NEW.tu_ngay <= den_ngay 
            AND NEW.den_ngay >= tu_ngay
    ) THEN
        SIGNAL SQLSTATE '45000' 
        SET MESSAGE_TEXT = N'Thời gian bị trùng hoặc chồng lấn với giai đoạn khác.';
    END IF;
    
    -- Hệ số lương phải tăng dần
    IF EXISTS (
        SELECT 1 FROM lich_su_he_so_luong 
        WHERE ma_nv = NEW.ma_nv 
            AND ma_ls <> NEW.ma_ls 
            AND tu_ngay < NEW.tu_ngay
            AND he_so_luong >= NEW.he_so_luong
        LIMIT 1
    ) THEN
        SIGNAL SQLSTATE '45000' 
        SET MESSAGE_TEXT = N'Hệ số lương mới phải lớn hơn hệ số lương của giai đoạn trước đó.';
    END IF;
END//

DROP TRIGGER IF EXISTS trg_trung_lap_he_so_luong_update//

CREATE TRIGGER trg_trung_lap_he_so_luong_update
BEFORE UPDATE ON lich_su_he_so_luong
FOR EACH ROW
BEGIN
    -- Cặp thời gian phải hợp lệ
    IF NEW.tu_ngay >= NEW.den_ngay THEN
        SIGNAL SQLSTATE '45000' 
        SET MESSAGE_TEXT = N'Từ ngày phải nhỏ hơn đến ngày.';
    END IF;
    
    -- Không trùng/chồng lấn thời gian với giai đoạn khác
    IF EXISTS (
        SELECT 1 FROM lich_su_he_so_luong 
        WHERE ma_nv = NEW.ma_nv 
            AND ma_ls <> NEW.ma_ls 
            AND NEW.tu_ngay <= den_ngay 
            AND NEW.den_ngay >= tu_ngay
    ) THEN
        SIGNAL SQLSTATE '45000' 
        SET MESSAGE_TEXT = N'Thời gian bị trùng hoặc chồng lấn với giai đoạn khác.';
    END IF;
    
    -- Hệ số lương phải tăng dần
    IF EXISTS (
        SELECT 1 FROM lich_su_he_so_luong 
        WHERE ma_nv = NEW.ma_nv 
            AND ma_ls <> NEW.ma_ls 
            AND tu_ngay < NEW.tu_ngay
            AND he_so_luong >= NEW.he_so_luong
        LIMIT 1
    ) THEN
        SIGNAL SQLSTATE '45000' 
        SET MESSAGE_TEXT = N'Hệ số lương mới phải lớn hơn hệ số lương của giai đoạn trước đó.';
    END IF;
END//

/* --------------------------------------
   Không xóa hệ số lương nếu có lương
   -------------------------------------- */
DROP TRIGGER IF EXISTS trg_khong_xoa_he_so_luong_neu_co_luong//

CREATE TRIGGER trg_khong_xoa_he_so_luong_neu_co_luong
BEFORE DELETE ON lich_su_he_so_luong
FOR EACH ROW
BEGIN
    IF EXISTS (
        SELECT 1 FROM luong 
        WHERE ma_nv = OLD.ma_nv 
            AND ky_luong BETWEEN OLD.tu_ngay AND OLD.den_ngay
    ) THEN
        SIGNAL SQLSTATE '45000' 
        SET MESSAGE_TEXT = N'Không thể xóa lịch sử hệ số lương vì đã tồn tại dữ liệu lương trong khoảng thời gian này.';
    END IF;
END//

/* --------------------------------------
   Trùng lặp ngày nghỉ phép
   -------------------------------------- */
DROP TRIGGER IF EXISTS trg_trung_lap_ngay_nghi_phep//

CREATE TRIGGER trg_trung_lap_ngay_nghi_phep
BEFORE INSERT ON nghi_phep
FOR EACH ROW
BEGIN
    IF EXISTS (
        SELECT 1 FROM nghi_phep 
        WHERE ma_nv = NEW.ma_nv 
            AND ma_np <> NEW.ma_np 
            AND NEW.tu_ngay <= den_ngay 
            AND NEW.den_ngay >= tu_ngay
    ) THEN
        SIGNAL SQLSTATE '45000' 
        SET MESSAGE_TEXT = N'Thời gian nghỉ phép của nhân viên bị trùng lặp.';
    END IF;
END//

DROP TRIGGER IF EXISTS trg_trung_lap_ngay_nghi_phep_update//

CREATE TRIGGER trg_trung_lap_ngay_nghi_phep_update
BEFORE UPDATE ON nghi_phep
FOR EACH ROW
BEGIN
    IF EXISTS (
        SELECT 1 FROM nghi_phep 
        WHERE ma_nv = NEW.ma_nv 
            AND ma_np <> NEW.ma_np 
            AND NEW.tu_ngay <= den_ngay 
            AND NEW.den_ngay >= tu_ngay
    ) THEN
        SIGNAL SQLSTATE '45000' 
        SET MESSAGE_TEXT = N'Thời gian nghỉ phép của nhân viên bị trùng lặp.';
    END IF;
END//

/* --------------------------------------
   Không thêm/sửa nghỉ phép khi đã chấm công
   -------------------------------------- */
DROP TRIGGER IF EXISTS trg_khong_duoc_them_sua_khi_da_cham_cong//

CREATE TRIGGER trg_khong_duoc_them_sua_khi_da_cham_cong
BEFORE INSERT ON nghi_phep
FOR EACH ROW
BEGIN
    IF EXISTS (
        SELECT 1 FROM cham_cong 
        WHERE ma_nv = NEW.ma_nv 
            AND YEAR(ngay_lam) = YEAR(NEW.tu_ngay) 
            AND MONTH(ngay_lam) = MONTH(NEW.tu_ngay)
    ) THEN
        SIGNAL SQLSTATE '45000' 
        SET MESSAGE_TEXT = N'Không thể thêm/sửa nghỉ phép vì tháng này đã có dữ liệu chấm công!';
    END IF;
END//

DROP TRIGGER IF EXISTS trg_khong_duoc_them_sua_khi_da_cham_cong_update//

CREATE TRIGGER trg_khong_duoc_them_sua_khi_da_cham_cong_update
BEFORE UPDATE ON nghi_phep
FOR EACH ROW
BEGIN
    IF EXISTS (
        SELECT 1 FROM cham_cong 
        WHERE ma_nv = NEW.ma_nv 
            AND YEAR(ngay_lam) = YEAR(NEW.tu_ngay) 
            AND MONTH(ngay_lam) = MONTH(NEW.tu_ngay)
    ) THEN
        SIGNAL SQLSTATE '45000' 
        SET MESSAGE_TEXT = N'Không thể thêm/sửa nghỉ phép vì tháng này đã có dữ liệu chấm công!';
    END IF;
END//

/* --------------------------------------
   Không xóa nghỉ phép khi đã chấm công
   -------------------------------------- */
DROP TRIGGER IF EXISTS trg_khong_xoa_nghi_phep_neu_co_cham_cong//

CREATE TRIGGER trg_khong_xoa_nghi_phep_neu_co_cham_cong
BEFORE DELETE ON nghi_phep
FOR EACH ROW
BEGIN
    IF EXISTS (
        SELECT 1 FROM cham_cong 
        WHERE ma_nv = OLD.ma_nv 
            AND ngay_lam BETWEEN OLD.tu_ngay AND OLD.den_ngay
    ) THEN
        SIGNAL SQLSTATE '45000' 
        SET MESSAGE_TEXT = N'Không thể xóa nghỉ phép vì nhân viên đã có chấm công trong khoảng thời gian này!';
    END IF;
END//

DELIMITER ;

-- ============================================================
-- STORED PROCEDURE
-- ============================================================

DELIMITER //

/* ============================
   PHÒNG BAN
   ============================ */

/* --------------------------------------
   Thêm phòng ban
   -------------------------------------- */
DROP PROCEDURE IF EXISTS sp_phong_ban_them;

CREATE PROCEDURE sp_phong_ban_them(
    IN p_ten_pb NVARCHAR(100)
)
BEGIN
    SET p_ten_pb = LTRIM(RTRIM(IFNULL(p_ten_pb, N'')));
    IF p_ten_pb = N'' THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = N'Tên phòng ban không được rỗng.';
    END IF;
    IF EXISTS (SELECT 1 FROM phong_ban WHERE ten_pb = p_ten_pb) THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = N'Tên phòng ban đã tồn tại.';
    END IF;
    INSERT INTO phong_ban(ten_pb) VALUES (p_ten_pb);
END

/* --------------------------------------
   Sửa phòng ban
   -------------------------------------- */
DROP PROCEDURE IF EXISTS sp_phong_ban_sua;

CREATE PROCEDURE sp_phong_ban_sua(
    IN p_ma_pb INT,
    IN p_ten_pb NVARCHAR(100)
)
BEGIN
    SET p_ten_pb = LTRIM(RTRIM(IFNULL(p_ten_pb, N'')));
    IF NOT EXISTS (SELECT 1 FROM phong_ban WHERE ma_pb = p_ma_pb) THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = N'Không tìm thấy phòng ban cần sửa.';
    END IF;
    IF p_ten_pb = N'' THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = N'Tên phòng ban không được rỗng.';
    END IF;
    IF EXISTS (SELECT 1 FROM phong_ban WHERE ten_pb = p_ten_pb AND ma_pb <> p_ma_pb) THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = N'Tên phòng ban đã tồn tại.';
    END IF;
    UPDATE phong_ban SET ten_pb = p_ten_pb WHERE ma_pb = p_ma_pb;
END

/* --------------------------------------
   Xóa phòng ban
   -------------------------------------- */
DROP PROCEDURE IF EXISTS sp_phong_ban_xoa;

CREATE PROCEDURE sp_phong_ban_xoa(
    IN p_ma_pb INT
)
BEGIN
    IF NOT EXISTS (SELECT 1 FROM phong_ban WHERE ma_pb = p_ma_pb) THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = N'Không tìm thấy phòng ban cần xóa.';
    END IF;
    IF EXISTS (SELECT 1 FROM nhan_vien WHERE ma_pb = p_ma_pb) THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = N'Không thể xóa phòng ban vì đang có nhân viên thuộc phòng ban này.';
    END IF;
    DELETE FROM phong_ban WHERE ma_pb = p_ma_pb;
END

/* --------------------------------------
   Danh sách phòng ban
   -------------------------------------- */
DROP PROCEDURE IF EXISTS sp_phong_ban_danh_sach$$

CREATE PROCEDURE sp_phong_ban_danh_sach()
BEGIN
    SELECT ma_pb, ten_pb, fn_dem_nhan_vien_theo_phong_ban(ma_pb) AS so_nhan_vien 
    FROM phong_ban 
    ORDER BY ma_pb;
END$$

/* ============================
   CHỨC VỤ
   ============================ */

/* --------------------------------------
   Thêm chức vụ
   -------------------------------------- */
DROP PROCEDURE IF EXISTS sp_chuc_vu_them//

CREATE PROCEDURE sp_chuc_vu_them(
    IN p_ten_cv NVARCHAR(100),
    IN p_he_so_phu_cap DECIMAL(18,2)
)
BEGIN
    SET p_ten_cv = LTRIM(RTRIM(IFNULL(p_ten_cv, N'')));
    IF p_ten_cv = N'' THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = N'Tên chức vụ không được rỗng.';
    END IF;
    IF p_he_so_phu_cap IS NULL OR p_he_so_phu_cap < 0 THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = N'Hệ số phụ cấp không hợp lệ.';
    END IF;
    IF EXISTS (SELECT 1 FROM chuc_vu WHERE ten_cv = p_ten_cv) THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = N'Tên chức vụ đã tồn tại.';
    END IF;
    INSERT INTO chuc_vu(ten_cv, he_so_phu_cap) VALUES (p_ten_cv, p_he_so_phu_cap);
END//

/* --------------------------------------
   Sửa chức vụ
   -------------------------------------- */
DROP PROCEDURE IF EXISTS sp_chuc_vu_sua//

CREATE PROCEDURE sp_chuc_vu_sua(
    IN p_ma_cv INT,
    IN p_ten_cv NVARCHAR(100),
    IN p_he_so_phu_cap DECIMAL(18,2)
)
BEGIN
    SET p_ten_cv = LTRIM(RTRIM(IFNULL(p_ten_cv, N'')));
    IF NOT EXISTS (SELECT 1 FROM chuc_vu WHERE ma_cv = p_ma_cv) THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = N'Không tìm thấy chức vụ cần sửa.';
    END IF;
    IF p_ten_cv = N'' THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = N'Tên chức vụ không được rỗng.';
    END IF;
    IF p_he_so_phu_cap IS NULL OR p_he_so_phu_cap < 0 THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = N'Hệ số phụ cấp không hợp lệ.';
    END IF;
    IF EXISTS (SELECT 1 FROM chuc_vu WHERE ten_cv = p_ten_cv AND ma_cv <> p_ma_cv) THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = N'Tên chức vụ đã tồn tại.';
    END IF;
    UPDATE chuc_vu SET ten_cv = p_ten_cv, he_so_phu_cap = p_he_so_phu_cap WHERE ma_cv = p_ma_cv;
END//

/* --------------------------------------
   Xóa chức vụ
   -------------------------------------- */
DROP PROCEDURE IF EXISTS sp_chuc_vu_xoa//

CREATE PROCEDURE sp_chuc_vu_xoa(
    IN p_ma_cv INT
)
BEGIN
    IF NOT EXISTS (SELECT 1 FROM chuc_vu WHERE ma_cv = p_ma_cv) THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = N'Không tìm thấy chức vụ cần xóa.';
    END IF;
    IF EXISTS (SELECT 1 FROM nhan_vien WHERE ma_cv = p_ma_cv) THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = N'Không thể xóa chức vụ vì đang có nhân viên sử dụng chức vụ này.';
    END IF;
    DELETE FROM chuc_vu WHERE ma_cv = p_ma_cv;
END//

/* --------------------------------------
   Danh sách chức vụ
   -------------------------------------- */
DROP PROCEDURE IF EXISTS sp_chuc_vu_danh_sach//

CREATE PROCEDURE sp_chuc_vu_danh_sach()
BEGIN
    SELECT ma_cv, ten_cv, he_so_phu_cap FROM chuc_vu ORDER BY ma_cv;
END//

/* ============================
   VAI TRÒ
   ============================ */

/* --------------------------------------
   Thêm vai trò
   -------------------------------------- */
DROP PROCEDURE IF EXISTS sp_vai_tro_them//

CREATE PROCEDURE sp_vai_tro_them(
    IN p_ten_vt NVARCHAR(100),
    IN p_mo_ta NVARCHAR(255)
)
BEGIN
    IF EXISTS (SELECT 1 FROM vai_tro WHERE ten_vt = p_ten_vt) THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = N'Tên vai trò đã tồn tại.';
    END IF;
    INSERT INTO vai_tro(ten_vt, mo_ta) VALUES(p_ten_vt, p_mo_ta);
END//

/* --------------------------------------
   Sửa vai trò
   -------------------------------------- */
DROP PROCEDURE IF EXISTS sp_vai_tro_sua//

CREATE PROCEDURE sp_vai_tro_sua(
    IN p_ma_vt INT,
    IN p_ten_vt NVARCHAR(100),
    IN p_mo_ta NVARCHAR(255)
)
BEGIN
    IF EXISTS (SELECT 1 FROM vai_tro WHERE ten_vt = p_ten_vt AND ma_vt <> p_ma_vt) THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = N'Tên vai trò đã tồn tại.';
    END IF;
    UPDATE vai_tro SET ten_vt = p_ten_vt, mo_ta = p_mo_ta WHERE ma_vt = p_ma_vt;
END//

/* --------------------------------------
   Xóa vai trò
   -------------------------------------- */
DROP PROCEDURE IF EXISTS sp_vai_tro_xoa//

CREATE PROCEDURE sp_vai_tro_xoa(
    IN p_ma_vt INT
)
BEGIN
    DECLARE EXIT HANDLER FOR SQLEXCEPTION
    BEGIN
        ROLLBACK;
        RESIGNAL;
    END;
    
    START TRANSACTION;
    IF NOT EXISTS (SELECT 1 FROM vai_tro WHERE ma_vt = p_ma_vt) THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = N'Vai trò không tồn tại.';
    END IF;
    DELETE FROM nhan_vien WHERE ma_vt = p_ma_vt;
    DELETE FROM vai_tro_quyen WHERE ma_vt = p_ma_vt;
    DELETE FROM vai_tro WHERE ma_vt = p_ma_vt;
    COMMIT;
END//

/* --------------------------------------
   Danh sách vai trò
   -------------------------------------- */
DROP PROCEDURE IF EXISTS sp_vai_tro_danh_sach//

CREATE PROCEDURE sp_vai_tro_danh_sach()
BEGIN
    SELECT ma_vt, ten_vt, mo_ta FROM vai_tro ORDER BY ten_vt;
END//

/* ============================
   QUYỀN
   ============================ */

/* --------------------------------------
   Thêm quyền
   -------------------------------------- */
DROP PROCEDURE IF EXISTS sp_quyen_them//

CREATE PROCEDURE sp_quyen_them(
    IN p_ky_hieu_quyen NVARCHAR(100),
    IN p_ten_quyen NVARCHAR(50),
    IN p_module NVARCHAR(50)
)
BEGIN
    IF EXISTS (SELECT 1 FROM quyen WHERE ky_hieu_quyen = p_ky_hieu_quyen) THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = N'Tên quyền đã tồn tại.';
    END IF;
    INSERT INTO quyen(ky_hieu_quyen, ten_quyen, module) VALUES(p_ky_hieu_quyen, p_ten_quyen, p_module);
END//

/* --------------------------------------
   Xóa quyền
   -------------------------------------- */
DROP PROCEDURE IF EXISTS sp_quyen_xoa//

CREATE PROCEDURE sp_quyen_xoa(
    IN p_ma_quyen INT
)
BEGIN
    DECLARE EXIT HANDLER FOR SQLEXCEPTION
    BEGIN
        ROLLBACK;
        RESIGNAL;
    END;
    
    START TRANSACTION;
    IF NOT EXISTS (SELECT 1 FROM quyen WHERE ma_quyen = p_ma_quyen) THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = N'Quyền không tồn tại.';
    END IF;
    DELETE FROM vai_tro_quyen WHERE ma_quyen = p_ma_quyen;
    DELETE FROM quyen WHERE ma_quyen = p_ma_quyen;
    COMMIT;
END//

/* --------------------------------------
   Lấy quyền theo mã nhân viên
   -------------------------------------- */
DROP PROCEDURE IF EXISTS sp_quyen_lay_theo_ma_nhan_vien//

CREATE PROCEDURE sp_quyen_lay_theo_ma_nhan_vien(
    IN p_ma_nv VARCHAR(5)
)
BEGIN
    SELECT q.ky_hieu_quyen FROM nhan_vien nv
    JOIN vai_tro_quyen vq ON nv.ma_vt = vq.ma_vt
    JOIN quyen q ON vq.ma_quyen = q.ma_quyen
    WHERE nv.ma_nv = p_ma_nv;
END//

/* ============================
   VAI TRÒ - QUYỀN
   ============================ */

/* --------------------------------------
   Gán quyền cho vai trò
   -------------------------------------- */
DROP PROCEDURE IF EXISTS sp_vai_tro_quyen_them//

CREATE PROCEDURE sp_vai_tro_quyen_them(
    IN p_ma_vt INT,
    IN p_ma_quyen INT
)
BEGIN
    IF NOT EXISTS (SELECT 1 FROM vai_tro WHERE ma_vt = p_ma_vt) THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = N'Vai trò không tồn tại.';
    END IF;
    IF NOT EXISTS (SELECT 1 FROM quyen WHERE ma_quyen = p_ma_quyen) THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = N'Quyền không tồn tại.';
    END IF;
    IF EXISTS (SELECT 1 FROM vai_tro_quyen WHERE ma_vt = p_ma_vt AND ma_quyen = p_ma_quyen) THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = N'Vai trò đã được gán quyền này.';
    END IF;
    INSERT INTO vai_tro_quyen(ma_vt, ma_quyen) VALUES(p_ma_vt, p_ma_quyen);
END//

/* --------------------------------------
   Xóa quyền khỏi vai trò
   -------------------------------------- */
DROP PROCEDURE IF EXISTS sp_vai_tro_quyen_xoa//

CREATE PROCEDURE sp_vai_tro_quyen_xoa(
    IN p_ma_vt INT
)
BEGIN
    DELETE FROM vai_tro_quyen WHERE ma_vt = p_ma_vt;
END//

/* --------------------------------------
   Lấy quyền theo vai trò
   -------------------------------------- */
DROP PROCEDURE IF EXISTS sp_vai_tro_quyen_lay_quyen_theo_vai_tro//

CREATE PROCEDURE sp_vai_tro_quyen_lay_quyen_theo_vai_tro(
    IN p_ma_vt INT
)
BEGIN
    SELECT ma_quyen FROM vai_tro_quyen WHERE ma_vt = p_ma_vt;
END//

/* ============================
   TRẠNG THÁI LÀM VIỆC
   ============================ */

/* --------------------------------------
   Danh sách trạng thái làm việc
   -------------------------------------- */
DROP PROCEDURE IF EXISTS sp_trang_thai_lam_viec_danh_sach//

CREATE PROCEDURE sp_trang_thai_lam_viec_danh_sach()
BEGIN
    SELECT ma_tt, ten_tt FROM trang_thai_lam_viec;
END//

/* ============================
   NHÂN VIÊN
   ============================ */

/* --------------------------------------
   Tìm kiếm nhân viên
   -------------------------------------- */
DROP PROCEDURE IF EXISTS sp_nhan_vien_tim_kiem//

CREATE PROCEDURE sp_nhan_vien_tim_kiem(
    IN p_tu_khoa NVARCHAR(100),
    IN p_ma_pb INT,
    IN p_ma_cv INT,
    IN p_ma_tt TINYINT
)
BEGIN
    SET p_tu_khoa = LTRIM(RTRIM(IFNULL(p_tu_khoa, N'')));
    SELECT * FROM vw_danh_sach_nhan_vien_chi_tiet WHERE
        (
            p_tu_khoa = N''
            OR ma_nv LIKE CONCAT('%', p_tu_khoa, '%')
            OR ho_ten LIKE CONCAT('%', p_tu_khoa, '%')
            OR sdt LIKE CONCAT('%', p_tu_khoa, '%')
            OR email LIKE CONCAT('%', p_tu_khoa, '%')
            OR cccd LIKE CONCAT('%', p_tu_khoa, '%')
            OR ten_pb LIKE CONCAT('%', p_tu_khoa, '%')
            OR ten_cv LIKE CONCAT('%', p_tu_khoa, '%')
        )
        AND (p_ma_pb IS NULL OR ma_pb = p_ma_pb)
        AND (p_ma_cv IS NULL OR ma_cv = p_ma_cv)
        AND (p_ma_tt IS NULL OR ma_tt = p_ma_tt)
    ORDER BY ma_nv;
END//

/* --------------------------------------
   Danh sách nhân viên
   -------------------------------------- */
DROP PROCEDURE IF EXISTS sp_nhan_vien_danh_sach//

CREATE PROCEDURE sp_nhan_vien_danh_sach()
BEGIN
    SELECT * FROM vw_danh_sach_nhan_vien_chi_tiet ORDER BY ma_nv;
END//

/* --------------------------------------
   Chi tiết nhân viên
   -------------------------------------- */
DROP PROCEDURE IF EXISTS sp_nhan_vien_chi_tiet//

CREATE PROCEDURE sp_nhan_vien_chi_tiet(
    IN p_ma_nv VARCHAR(5)
)
BEGIN
    SELECT * FROM vw_danh_sach_nhan_vien_chi_tiet WHERE ma_nv = p_ma_nv;
END//

/* --------------------------------------
   Thêm nhân viên
   -------------------------------------- */
DROP PROCEDURE IF EXISTS sp_nhan_vien_them//

CREATE PROCEDURE sp_nhan_vien_them(
    IN p_ma_nv VARCHAR(5),
    IN p_ho_ten NVARCHAR(50),
    IN p_ngay_sinh DATE,
    IN p_gioi_tinh TINYINT,
    IN p_sdt VARCHAR(15),
    IN p_email NVARCHAR(50),
    IN p_ngay_vao_lam DATE,
    IN p_ma_pb INT,
    IN p_ma_cv INT,
    IN p_dan_toc NVARCHAR(50),
    IN p_cccd VARCHAR(12),
    IN p_noi_cap_cccd NVARCHAR(50),
    IN p_hoc_van NVARCHAR(50),
    IN p_ma_tt TINYINT,
    IN p_mat_khau VARCHAR(255),
    IN p_ma_vt INT
)
BEGIN
    DECLARE v_mat_khau VARCHAR(255);
    
    SET p_ma_nv = UPPER(LTRIM(RTRIM(IFNULL(p_ma_nv, ''))));
    SET p_ho_ten = LTRIM(RTRIM(IFNULL(p_ho_ten, N'')));
    SET p_sdt = LTRIM(RTRIM(IFNULL(p_sdt, '')));
    SET p_email = LTRIM(RTRIM(IFNULL(p_email, N'')));
    SET p_dan_toc = LTRIM(RTRIM(IFNULL(p_dan_toc, N'')));
    SET p_cccd = LTRIM(RTRIM(IFNULL(p_cccd, '')));
    SET p_noi_cap_cccd = LTRIM(RTRIM(IFNULL(p_noi_cap_cccd, N'')));
    SET p_hoc_van = LTRIM(RTRIM(IFNULL(p_hoc_van, N'')));
    
    IF p_ma_nv = '' OR LENGTH(p_ma_nv) > 5 THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = N'Mã nhân viên không hợp lệ. MaNV tối đa 5 ký tự.';
    END IF;
    IF EXISTS (SELECT 1 FROM nhan_vien WHERE ma_nv = p_ma_nv) THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = N'Mã nhân viên đã tồn tại.';
    END IF;
    IF p_ho_ten = N'' THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = N'Họ tên không được rỗng.';
    END IF;
    IF p_gioi_tinh NOT IN (0, 1) THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = N'Giới tính không hợp lệ. Dùng 1 = Nam, 0 = Nữ.';
    END IF;
    IF p_ngay_sinh >= p_ngay_vao_lam THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = N'Ngày sinh phải nhỏ hơn ngày vào làm.';
    END IF;
    IF TIMESTAMPDIFF(YEAR, p_ngay_sinh, p_ngay_vao_lam) < 18 THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = N'Nhân viên phải đủ 18 tuổi tại thời điểm vào làm.';
    END IF;
    IF NOT EXISTS (SELECT 1 FROM phong_ban WHERE ma_pb = p_ma_pb) THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = N'Mã phòng ban không tồn tại.';
    END IF;
    IF NOT EXISTS (SELECT 1 FROM chuc_vu WHERE ma_cv = p_ma_cv) THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = N'Mã chức vụ không tồn tại.';
    END IF;
    IF NOT EXISTS (SELECT 1 FROM trang_thai_lam_viec WHERE ma_tt = p_ma_tt) THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = N'Mã trạng thái làm việc không tồn tại.';
    END IF;
    IF p_cccd NOT REGEXP '^[0-9]{12}$' THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = N'CCCD phải gồm đúng 12 chữ số.';
    END IF;
    IF EXISTS (SELECT 1 FROM nhan_vien WHERE cccd = p_cccd) THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = N'CCCD đã tồn tại.';
    END IF;
    IF p_sdt = '' OR p_sdt NOT REGEXP '^[0-9]' THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = N'Số điện thoại không hợp lệ.';
    END IF;
    IF p_email = N'' OR p_email NOT LIKE '%_@_%_.%' THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = N'Email không hợp lệ.';
    END IF;
    
    IF p_mat_khau IS NULL OR LTRIM(RTRIM(p_mat_khau)) = '' THEN
        SET v_mat_khau = SHA2('123456', 256);
    ELSE
        SET v_mat_khau = SHA2(p_mat_khau, 256);
    END IF;
    
    INSERT INTO nhan_vien(ma_nv, ho_ten, ngay_sinh, gioi_tinh, sdt, email, ngay_vao_lam,
        ma_pb, ma_cv, dan_toc, cccd, noi_cap_cccd, hoc_van, ma_tt, mat_khau, ma_vt)
    VALUES(p_ma_nv, p_ho_ten, p_ngay_sinh, p_gioi_tinh, p_sdt, p_email, p_ngay_vao_lam,
        p_ma_pb, p_ma_cv, p_dan_toc, p_cccd, p_noi_cap_cccd, p_hoc_van, p_ma_tt, v_mat_khau, p_ma_vt);
END//

/* --------------------------------------
   Sửa nhân viên
   -------------------------------------- */
DROP PROCEDURE IF EXISTS sp_nhan_vien_sua//

CREATE PROCEDURE sp_nhan_vien_sua(
    IN p_ma_nv VARCHAR(5),
    IN p_ho_ten NVARCHAR(50),
    IN p_ngay_sinh DATE,
    IN p_gioi_tinh TINYINT,
    IN p_sdt VARCHAR(15),
    IN p_email NVARCHAR(50),
    IN p_ngay_vao_lam DATE,
    IN p_ma_pb INT,
    IN p_ma_cv INT,
    IN p_dan_toc NVARCHAR(50),
    IN p_cccd VARCHAR(12),
    IN p_noi_cap_cccd NVARCHAR(50),
    IN p_hoc_van NVARCHAR(50),
    IN p_ma_tt TINYINT,
    IN p_mat_khau VARCHAR(255),
    IN p_ma_vt INT
)
BEGIN
    DECLARE v_mat_khau VARCHAR(255);
    
    SET p_ma_nv = UPPER(LTRIM(RTRIM(IFNULL(p_ma_nv, ''))));
    SET p_ho_ten = LTRIM(RTRIM(IFNULL(p_ho_ten, N'')));
    SET p_sdt = LTRIM(RTRIM(IFNULL(p_sdt, '')));
    SET p_email = LTRIM(RTRIM(IFNULL(p_email, N'')));
    SET p_dan_toc = LTRIM(RTRIM(IFNULL(p_dan_toc, N'')));
    SET p_cccd = LTRIM(RTRIM(IFNULL(p_cccd, '')));
    SET p_noi_cap_cccd = LTRIM(RTRIM(IFNULL(p_noi_cap_cccd, N'')));
    SET p_hoc_van = LTRIM(RTRIM(IFNULL(p_hoc_van, N'')));
    
    IF NOT EXISTS (SELECT 1 FROM nhan_vien WHERE ma_nv = p_ma_nv) THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = N'Không tìm thấy nhân viên cần sửa.';
    END IF;
    IF p_ho_ten = N'' THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = N'Họ tên không được rỗng.';
    END IF;
    IF p_gioi_tinh NOT IN (0, 1) THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = N'Giới tính không hợp lệ. Dùng 1 = Nam, 0 = Nữ.';
    END IF;
    IF p_ngay_sinh >= p_ngay_vao_lam THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = N'Ngày sinh phải nhỏ hơn ngày vào làm.';
    END IF;
    IF TIMESTAMPDIFF(YEAR, p_ngay_sinh, p_ngay_vao_lam) < 18 THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = N'Nhân viên phải đủ 18 tuổi tại thời điểm vào làm.';
    END IF;
    IF NOT EXISTS (SELECT 1 FROM phong_ban WHERE ma_pb = p_ma_pb) THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = N'Mã phòng ban không tồn tại.';
    END IF;
    IF NOT EXISTS (SELECT 1 FROM chuc_vu WHERE ma_cv = p_ma_cv) THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = N'Mã chức vụ không tồn tại.';
    END IF;
    IF NOT EXISTS (SELECT 1 FROM trang_thai_lam_viec WHERE ma_tt = p_ma_tt) THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = N'Mã trạng thái làm việc không tồn tại.';
    END IF;
    IF p_cccd NOT REGEXP '^[0-9]{12}$' THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = N'CCCD phải gồm đúng 12 chữ số.';
    END IF;
    IF EXISTS (SELECT 1 FROM nhan_vien WHERE cccd = p_cccd AND ma_nv <> p_ma_nv) THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = N'CCCD đã tồn tại ở nhân viên khác.';
    END IF;
    IF p_sdt = '' OR p_sdt NOT REGEXP '^[0-9]' THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = N'Số điện thoại không hợp lệ.';
    END IF;
    IF p_email = N'' OR p_email NOT LIKE '%_@_%_.%' THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = N'Email không hợp lệ.';
    END IF;
    
    IF p_mat_khau IS NULL OR LTRIM(RTRIM(p_mat_khau)) = '' THEN
        SET v_mat_khau = SHA2('123456', 256);
    ELSE
        SET v_mat_khau = SHA2(p_mat_khau, 256);
    END IF;
    
    UPDATE nhan_vien SET 
        ho_ten = p_ho_ten, 
        ngay_sinh = p_ngay_sinh, 
        gioi_tinh = p_gioi_tinh, 
        sdt = p_sdt, 
        email = p_email, 
        ngay_vao_lam = p_ngay_vao_lam,
        ma_pb = p_ma_pb, 
        ma_cv = p_ma_cv, 
        dan_toc = p_dan_toc, 
        cccd = p_cccd, 
        noi_cap_cccd = p_noi_cap_cccd, 
        hoc_van = p_hoc_van, 
        ma_tt = p_ma_tt,
        mat_khau = v_mat_khau, 
        ma_vt = p_ma_vt 
    WHERE ma_nv = p_ma_nv;
END//

/* --------------------------------------
   Xóa nhân viên
   -------------------------------------- */
DROP PROCEDURE IF EXISTS sp_nhan_vien_xoa//

CREATE PROCEDURE sp_nhan_vien_xoa(
    IN p_ma_nv VARCHAR(5)
)
BEGIN
    DECLARE EXIT HANDLER FOR SQLEXCEPTION
    BEGIN
        ROLLBACK;
        RESIGNAL;
    END;
    
    SET p_ma_nv = UPPER(LTRIM(RTRIM(IFNULL(p_ma_nv, ''))));
    IF p_ma_nv = '' THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = N'Mã nhân viên không được rỗng.';
    END IF;
    IF NOT EXISTS (SELECT 1 FROM nhan_vien WHERE ma_nv = p_ma_nv) THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = N'Không tìm thấy nhân viên cần xóa.';
    END IF;
    
    IF EXISTS (SELECT 1 FROM hop_dong WHERE ma_nv = p_ma_nv) THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = N'Không thể xóa nhân viên vì còn dữ liệu hợp đồng.';
    END IF;
    IF EXISTS (SELECT 1 FROM cham_cong WHERE ma_nv = p_ma_nv) THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = N'Không thể xóa nhân viên vì còn dữ liệu chấm công.';
    END IF;
    IF EXISTS (SELECT 1 FROM nghi_phep WHERE ma_nv = p_ma_nv) THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = N'Không thể xóa nhân viên vì còn dữ liệu nghỉ phép.';
    END IF;
    IF EXISTS (SELECT 1 FROM luong WHERE ma_nv = p_ma_nv) THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = N'Không thể xóa nhân viên vì còn dữ liệu lương.';
    END IF;
    IF EXISTS (SELECT 1 FROM lich_su_he_so_luong WHERE ma_nv = p_ma_nv) THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = N'Không thể xóa nhân viên vì còn lịch sử hệ số lương.';
    END IF;
    
    START TRANSACTION;
    DELETE FROM nhan_vien WHERE ma_nv = p_ma_nv;
    COMMIT;
END//

/* --------------------------------------
   Đăng nhập
   -------------------------------------- */
DROP PROCEDURE IF EXISTS sp_nhan_vien_dang_nhap//

CREATE PROCEDURE sp_nhan_vien_dang_nhap(
    IN p_ten_dang_nhap NVARCHAR(50),
    IN p_mat_khau VARCHAR(255)
)
BEGIN
    DECLARE v_mat_khau_hash VARCHAR(255);
    SET v_mat_khau_hash = SHA2(p_mat_khau, 256);
    
    IF NOT EXISTS (SELECT 1 FROM nhan_vien WHERE ma_nv = p_ten_dang_nhap) THEN
        SELECT -1 AS ket_qua, N'Tài khoản không tồn tại' AS thong_bao;
    ELSEIF NOT EXISTS (SELECT 1 FROM nhan_vien WHERE ma_nv = p_ten_dang_nhap AND mat_khau = v_mat_khau_hash) THEN
        SELECT -2 AS ket_qua, N'Sai mật khẩu' AS thong_bao;
    ELSE
        SELECT 1 AS ket_qua;
    END IF;
END//

/* ============================
   LOẠI HỢP ĐỒNG
   ============================ */

/* --------------------------------------
   Thêm loại hợp đồng
   -------------------------------------- */
DROP PROCEDURE IF EXISTS sp_loai_hop_dong_them//

CREATE PROCEDURE sp_loai_hop_dong_them(
    IN p_ten_lhd NVARCHAR(255)
)
BEGIN
    INSERT INTO loai_hop_dong(ten_lhd) VALUES(p_ten_lhd);
END//

/* --------------------------------------
   Sửa loại hợp đồng
   -------------------------------------- */
DROP PROCEDURE IF EXISTS sp_loai_hop_dong_sua//

CREATE PROCEDURE sp_loai_hop_dong_sua(
    IN p_ma_lhd INT,
    IN p_ten_lhd NVARCHAR(255)
)
BEGIN
    UPDATE loai_hop_dong SET ten_lhd = p_ten_lhd WHERE ma_lhd = p_ma_lhd;
END//

/* --------------------------------------
   Xóa loại hợp đồng
   -------------------------------------- */
DROP PROCEDURE IF EXISTS sp_loai_hop_dong_xoa//

CREATE PROCEDURE sp_loai_hop_dong_xoa(
    IN p_ma_lhd INT
)
BEGIN
    IF NOT EXISTS (SELECT 1 FROM loai_hop_dong WHERE ma_lhd = p_ma_lhd) THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = N'Loại hợp đồng không tồn tại.';
    END IF;
    IF EXISTS (SELECT 1 FROM hop_dong WHERE ma_lhd = p_ma_lhd) THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = N'Không thể xóa loại hợp đồng vì đã có hợp đồng thuộc loại này.';
    END IF;
    DELETE FROM loai_hop_dong WHERE ma_lhd = p_ma_lhd;
END//

/* --------------------------------------
   Danh sách loại hợp đồng
   -------------------------------------- */
DROP PROCEDURE IF EXISTS sp_loai_hop_dong_danh_sach//

CREATE PROCEDURE sp_loai_hop_dong_danh_sach()
BEGIN
    SELECT ma_lhd, ten_lhd FROM loai_hop_dong;
END//

/* ============================
   HỢP ĐỒNG
   ============================ */

/* --------------------------------------
   Thêm hợp đồng
   -------------------------------------- */
DROP PROCEDURE IF EXISTS sp_hop_dong_them//

CREATE PROCEDURE sp_hop_dong_them(
    IN p_ma_nv VARCHAR(5),
    IN p_ma_lhd INT,
    IN p_ngay_ky DATE,
    IN p_ngay_het_han DATE,
    IN p_luong_co_ban DECIMAL(18,0)
)
BEGIN
    IF NOT EXISTS (SELECT 1 FROM nhan_vien WHERE ma_nv = p_ma_nv) THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = N'Mã nhân viên không tồn tại.';
    END IF;
    IF NOT EXISTS (SELECT 1 FROM loai_hop_dong WHERE ma_lhd = p_ma_lhd) THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = N'Mã loại hợp đồng không tồn tại.';
    END IF;
    IF p_ngay_het_han IS NOT NULL AND p_ngay_het_han < p_ngay_ky THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = N'Ngày hết hạn không được nhỏ hơn ngày ký.';
    END IF;
    INSERT INTO hop_dong(ma_nv, ma_lhd, ngay_ky, ngay_het_han, luong_co_ban) 
    VALUES(p_ma_nv, p_ma_lhd, p_ngay_ky, p_ngay_het_han, p_luong_co_ban);
END//

/* --------------------------------------
   Sửa hợp đồng
   -------------------------------------- */
DROP PROCEDURE IF EXISTS sp_hop_dong_sua//

CREATE PROCEDURE sp_hop_dong_sua(
    IN p_ma_hd INT,
    IN p_ma_nv VARCHAR(5),
    IN p_ma_lhd INT,
    IN p_ngay_ky DATE,
    IN p_ngay_het_han DATE,
    IN p_luong_co_ban DECIMAL(18,0)
)
BEGIN
    IF NOT EXISTS (SELECT 1 FROM hop_dong WHERE ma_hd = p_ma_hd) THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = N'Mã hợp đồng không tồn tại.';
    END IF;
    IF NOT EXISTS (SELECT 1 FROM nhan_vien WHERE ma_nv = p_ma_nv) THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = N'Mã nhân viên không tồn tại.';
    END IF;
    IF NOT EXISTS (SELECT 1 FROM loai_hop_dong WHERE ma_lhd = p_ma_lhd) THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = N'Mã loại hợp đồng không tồn tại.';
    END IF;
    IF p_ngay_het_han IS NOT NULL AND p_ngay_het_han < p_ngay_ky THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = N'Ngày hết hạn không được nhỏ hơn ngày ký.';
    END IF;
    UPDATE hop_dong SET 
        ma_nv = p_ma_nv, 
        ma_lhd = p_ma_lhd, 
        ngay_ky = p_ngay_ky, 
        ngay_het_han = p_ngay_het_han, 
        luong_co_ban = p_luong_co_ban
    WHERE ma_hd = p_ma_hd;
END//

/* --------------------------------------
   Xóa hợp đồng
   -------------------------------------- */
DROP PROCEDURE IF EXISTS sp_hop_dong_xoa//

CREATE PROCEDURE sp_hop_dong_xoa(
    IN p_ma_hd INT
)
BEGIN
    IF NOT EXISTS (SELECT 1 FROM hop_dong WHERE ma_hd = p_ma_hd) THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = N'Mã hợp đồng không tồn tại.';
    END IF;
    DELETE FROM hop_dong WHERE ma_hd = p_ma_hd;
END//

/* --------------------------------------
   Tìm kiếm hợp đồng
   -------------------------------------- */
DROP PROCEDURE IF EXISTS sp_hop_dong_tim_kiem//

CREATE PROCEDURE sp_hop_dong_tim_kiem(
    IN p_ma_nv VARCHAR(5),
    IN p_ma_lhd INT
)
BEGIN
    SELECT hd.ma_hd, hd.ma_lhd, lhd.ten_lhd, hd.ngay_ky, hd.ngay_het_han, hd.luong_co_ban 
    FROM hop_dong hd
    LEFT JOIN loai_hop_dong lhd ON lhd.ma_lhd = hd.ma_lhd
    WHERE hd.ma_nv = p_ma_nv AND (p_ma_lhd IS NULL OR hd.ma_lhd = p_ma_lhd)
    ORDER BY hd.ngay_ky DESC, hd.ma_hd DESC;
END//

/* --------------------------------------
   Chi tiết hợp đồng
   -------------------------------------- */
DROP PROCEDURE IF EXISTS sp_hop_dong_chi_tiet//

CREATE PROCEDURE sp_hop_dong_chi_tiet(
    IN p_ma_hd INT,
    IN p_ma_nv VARCHAR(5)
)
BEGIN
    SELECT hd.ma_hd, hd.ma_lhd, hd.ngay_ky, hd.ngay_het_han, hd.luong_co_ban 
    FROM hop_dong hd 
    WHERE hd.ma_hd = p_ma_hd AND hd.ma_nv = p_ma_nv;
END//

/* --------------------------------------
   Cập nhật trạng thái cảnh báo hợp đồng sắp hết hạn
   -------------------------------------- */
DROP PROCEDURE IF EXISTS sp_cap_nhat_canh_bao_hop_dong//

CREATE PROCEDURE sp_cap_nhat_canh_bao_hop_dong(
    IN p_so_ngay_canh_bao INT
)
BEGIN
    DECLARE v_ma_nv VARCHAR(5);
    DECLARE v_ho_ten NVARCHAR(50);
    DECLARE v_ten_pb NVARCHAR(100);
    DECLARE v_ten_cv NVARCHAR(100);
    DECLARE v_ngay_het_han DATE;
    DECLARE v_so_ngay_con_lai INT;
    DECLARE v_trang_thai NVARCHAR(50);
    DECLARE done INT DEFAULT FALSE;
    
    DECLARE cur_hop_dong CURSOR FOR
    SELECT hd.ma_nv, nv.ho_ten, pb.ten_pb, cv.ten_cv, hd.ngay_het_han 
    FROM hop_dong hd
    JOIN nhan_vien nv ON hd.ma_nv = nv.ma_nv
    JOIN phong_ban pb ON nv.ma_pb = pb.ma_pb
    JOIN chuc_vu cv ON nv.ma_cv = cv.ma_cv
    WHERE hd.ngay_het_han IS NOT NULL 
        AND hd.ngay_het_han >= CURDATE() 
        AND hd.ngay_het_han <= DATE_ADD(CURDATE(), INTERVAL p_so_ngay_canh_bao DAY)
    ORDER BY hd.ngay_het_han ASC;
    
    DECLARE CONTINUE HANDLER FOR NOT FOUND SET done = TRUE;
    
    DROP TEMPORARY TABLE IF EXISTS tmp_canh_bao_hop_dong;
    CREATE TEMPORARY TABLE tmp_canh_bao_hop_dong (
        ma_nv VARCHAR(5),
        ho_ten NVARCHAR(50),
        ten_pb NVARCHAR(100),
        ten_cv NVARCHAR(100),
        ngay_het_han DATE,
        so_ngay_con_lai INT,
        trang_thai NVARCHAR(50)
    );
    
    OPEN cur_hop_dong;
    read_loop: LOOP
        FETCH cur_hop_dong INTO v_ma_nv, v_ho_ten, v_ten_pb, v_ten_cv, v_ngay_het_han;
        IF done THEN
            LEAVE read_loop;
        END IF;
        
        SET v_so_ngay_con_lai = DATEDIFF(v_ngay_het_han, CURDATE());
        SET v_trang_thai = CASE
            WHEN v_so_ngay_con_lai <= 0 THEN N'Đã hết hạn'
            WHEN v_so_ngay_con_lai <= 7 THEN N'Sắp hết hạn (Khẩn cấp)'
            WHEN v_so_ngay_con_lai <= 15 THEN N'Sắp hết hạn (Quan trọng)'
            WHEN v_so_ngay_con_lai <= 30 THEN N'Sắp hết hạn'
            ELSE N'Còn hạn'
        END;
        
        INSERT INTO tmp_canh_bao_hop_dong (ma_nv, ho_ten, ten_pb, ten_cv, ngay_het_han, so_ngay_con_lai, trang_thai)
        VALUES (v_ma_nv, v_ho_ten, v_ten_pb, v_ten_cv, v_ngay_het_han, v_so_ngay_con_lai, v_trang_thai);
    END LOOP;
    CLOSE cur_hop_dong;
    
    SELECT * FROM tmp_canh_bao_hop_dong ORDER BY so_ngay_con_lai ASC;
    DROP TEMPORARY TABLE tmp_canh_bao_hop_dong;
END//

/* ============================
   LOẠI NGHỈ PHÉP
   ============================ */

/* --------------------------------------
   Danh sách loại phép
   -------------------------------------- */
DROP PROCEDURE IF EXISTS sp_loai_phep_danh_sach//

CREATE PROCEDURE sp_loai_phep_danh_sach()
BEGIN
    SELECT ma_lp, ten_lp FROM loai_phep;
END//

/* ============================
   NGHỈ PHÉP
   ============================ */

/* --------------------------------------
   Thêm nghỉ phép
   -------------------------------------- */
DROP PROCEDURE IF EXISTS sp_nghi_phep_them//

CREATE PROCEDURE sp_nghi_phep_them(
    IN p_ma_nv VARCHAR(5),
    IN p_tu_ngay DATE,
    IN p_den_ngay DATE,
    IN p_ma_lp INT,
    IN p_ly_do NVARCHAR(255),
    IN p_trang_thai_duyet INT
)
BEGIN
    IF p_tu_ngay > p_den_ngay THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = N'Từ ngày phải nhỏ hơn hoặc bằng đến ngày.';
    END IF;
    IF NOT EXISTS (SELECT 1 FROM nhan_vien WHERE ma_nv = p_ma_nv) THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = N'Mã nhân viên không tồn tại.';
    END IF;
    INSERT INTO nghi_phep (ma_nv, tu_ngay, den_ngay, ma_lp, ly_do, trang_thai_duyet)
    VALUES (p_ma_nv, p_tu_ngay, p_den_ngay, p_ma_lp, p_ly_do, p_trang_thai_duyet);
END//

/* --------------------------------------
   Sửa nghỉ phép
   -------------------------------------- */
DROP PROCEDURE IF EXISTS sp_nghi_phep_sua//

CREATE PROCEDURE sp_nghi_phep_sua(
    IN p_ma_nv VARCHAR(5),
    IN p_ma_np INT,
    IN p_tu_ngay DATE,
    IN p_den_ngay DATE,
    IN p_ly_do NVARCHAR(255),
    IN p_ma_lp INT,
    IN p_trang_thai_duyet TINYINT
)
BEGIN
    IF p_tu_ngay > p_den_ngay THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = N'Từ ngày phải nhỏ hơn hoặc bằng đến ngày.';
    END IF;
    IF NOT EXISTS (SELECT 1 FROM nhan_vien WHERE ma_nv = p_ma_nv) THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = N'Mã nhân viên không tồn tại.';
    END IF;
    IF NOT EXISTS (SELECT 1 FROM nghi_phep WHERE ma_nv = p_ma_nv AND ma_np = p_ma_np) THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = N'Đơn nghỉ phép của nhân viên không tồn tại.';
    END IF;
    UPDATE nghi_phep SET 
        tu_ngay = p_tu_ngay, 
        den_ngay = p_den_ngay, 
        ly_do = p_ly_do, 
        ma_lp = p_ma_lp, 
        trang_thai_duyet = p_trang_thai_duyet 
    WHERE ma_nv = p_ma_nv AND ma_np = p_ma_np;
END//

/* --------------------------------------
   Xóa nghỉ phép
   -------------------------------------- */
DROP PROCEDURE IF EXISTS sp_nghi_phep_xoa//

CREATE PROCEDURE sp_nghi_phep_xoa(
    IN p_ma_np INT
)
BEGIN
    IF EXISTS (
        SELECT 1 FROM nghi_phep np 
        INNER JOIN cham_cong cc ON np.ma_nv = cc.ma_nv
        WHERE np.ma_np = p_ma_np AND cc.ngay_lam BETWEEN np.tu_ngay AND np.den_ngay
    ) THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = N'Có chấm công trong thời gian nghỉ phép, không thể xóa!';
    END IF;
    DELETE FROM nghi_phep WHERE ma_np = p_ma_np;
END//

/* --------------------------------------
   Duyệt nghỉ phép
   -------------------------------------- */
DROP PROCEDURE IF EXISTS sp_nghi_phep_duyet_phep//

CREATE PROCEDURE sp_nghi_phep_duyet_phep(
    IN p_ma_np INT,
    IN p_ma_nv VARCHAR(5),
    IN p_trang_thai_duyet TINYINT
)
BEGIN
    IF NOT EXISTS (SELECT 1 FROM nghi_phep WHERE ma_np = p_ma_np AND ma_nv = p_ma_nv AND trang_thai_duyet = 0) THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = N'Đơn nghỉ phép không ở trạng thái chờ duyệt';
    END IF;
    UPDATE nghi_phep SET trang_thai_duyet = p_trang_thai_duyet 
    WHERE ma_np = p_ma_np AND ma_nv = p_ma_nv AND trang_thai_duyet = 0;
END//

/* --------------------------------------
   Danh sách phép chờ duyệt
   -------------------------------------- */
DROP PROCEDURE IF EXISTS sp_nghi_phep_danh_sach_phep_cho_duyet//

CREATE PROCEDURE sp_nghi_phep_danh_sach_phep_cho_duyet()
BEGIN
    SELECT np.ma_np, np.ma_nv, np.tu_ngay, np.den_ngay, np.ma_lp, lp.ten_lp, np.ly_do, np.trang_thai_duyet
    FROM nghi_phep np LEFT JOIN loai_phep lp ON lp.ma_lp = np.ma_lp
    WHERE trang_thai_duyet = 0;
END//

/* --------------------------------------
   Danh sách phép đã duyệt
   -------------------------------------- */
DROP PROCEDURE IF EXISTS sp_nghi_phep_danh_sach_phep_da_duyet//

CREATE PROCEDURE sp_nghi_phep_danh_sach_phep_da_duyet(
    IN p_ma_nv VARCHAR(5)
)
BEGIN
    SELECT np.ma_np, np.ma_nv, np.tu_ngay, np.den_ngay, np.ma_lp, lp.ten_lp, np.ly_do, np.trang_thai_duyet
    FROM nghi_phep np LEFT JOIN loai_phep lp ON lp.ma_lp = np.ma_lp
    WHERE trang_thai_duyet != 0 AND ma_nv = p_ma_nv;
END//

/* --------------------------------------
   Chi tiết nghỉ phép
   -------------------------------------- */
DROP PROCEDURE IF EXISTS sp_nghi_phep_chi_tiet//

CREATE PROCEDURE sp_nghi_phep_chi_tiet(
    IN p_ma_np INT,
    IN p_ma_nv VARCHAR(5)
)
BEGIN
    SELECT np.ma_np, np.ma_nv, np.tu_ngay, np.den_ngay, np.ma_lp, np.ly_do, np.trang_thai_duyet
    FROM nghi_phep np WHERE np.ma_np = p_ma_np AND np.ma_nv = p_ma_nv;
END//

/* ============================
   CHẤM CÔNG
   ============================ */

/* --------------------------------------
   Tìm kiếm chấm công nhân viên
   -------------------------------------- */
DROP PROCEDURE IF EXISTS sp_cham_cong_nhan_vien_tim_kiem//

CREATE PROCEDURE sp_cham_cong_nhan_vien_tim_kiem(
    IN p_ma_nv VARCHAR(5),
    IN p_ngay_lam DATE
)
BEGIN
    SELECT dsnvct.ma_nv, dsnvct.ho_ten, dsnvct.ngay_sinh, dsnvct.gioi_tinh_hien_thi,
        dsnvct.sdt, dsnvct.email, dsnvct.ten_pb, dsnvct.ten_cv, 
        IFNULL(ccnv.so_ngay_cham_cong, 0) AS so_ngay_cham_cong,
        IFNULL(ccnv.so_lan_vao_muon, 0) AS so_lan_vao_muon, 
        IFNULL(ccnv.so_lan_ve_som, 0) AS so_lan_ve_som
    FROM vw_danh_sach_nhan_vien_chi_tiet dsnvct
    LEFT JOIN (
        SELECT nv.ma_nv, 
            fn_tinh_so_ngay_cong(nv.ma_nv, p_ngay_lam) AS so_ngay_cham_cong,
            SUM(CASE WHEN vao_muon = 1 THEN 1 ELSE 0 END) AS so_lan_vao_muon, 
            SUM(CASE WHEN ve_som = 1 THEN 1 ELSE 0 END) AS so_lan_ve_som
        FROM cham_cong cc LEFT JOIN nhan_vien nv ON nv.ma_nv = cc.ma_nv
        WHERE p_ngay_lam IS NULL 
            OR (ngay_lam >= DATE_FORMAT(p_ngay_lam, '%Y-%m-01')
                AND ngay_lam < DATE_ADD(DATE_FORMAT(p_ngay_lam, '%Y-%m-01'), INTERVAL 1 MONTH))
        GROUP BY nv.ma_nv
    ) ccnv ON dsnvct.ma_nv = ccnv.ma_nv
    WHERE (p_ma_nv IS NULL OR dsnvct.ma_nv = p_ma_nv)
    ORDER BY dsnvct.ma_nv ASC;
END//

/* --------------------------------------
   Chi tiết chấm công
   -------------------------------------- */
DROP PROCEDURE IF EXISTS sp_cham_cong_chi_tiet//

CREATE PROCEDURE sp_cham_cong_chi_tiet(
    IN p_ma_nv VARCHAR(5),
    IN p_nam INT,
    IN p_thang INT
)
BEGIN
    SELECT * FROM cham_cong 
    WHERE ma_nv = p_ma_nv AND YEAR(ngay_lam) = p_nam AND MONTH(ngay_lam) = p_thang;
END//

/* --------------------------------------
   Cập nhật chấm công
   -------------------------------------- */
DROP PROCEDURE IF EXISTS sp_cham_cong_cap_nhat//

CREATE PROCEDURE sp_cham_cong_cap_nhat(
    IN p_ma_cc INT,
    IN p_ma_nv VARCHAR(5),
    IN p_ngay_lam DATE,
    IN p_so_gio_lam SMALLINT,
    IN p_vao_muon BIT,
    IN p_ve_som BIT
)
BEGIN
    DECLARE v_error_message NVARCHAR(2048);
    
    IF EXISTS (
        SELECT 1 FROM nghi_phep np 
        WHERE np.ma_nv = p_ma_nv AND np.trang_thai_duyet = 1 
            AND p_ngay_lam BETWEEN np.tu_ngay AND np.den_ngay
    ) THEN
        IF p_so_gio_lam <> 0 THEN
            SET v_error_message = CONCAT(N'Nhân viên ', p_ma_nv, N' có nghỉ phép ngày ', 
                DATE_FORMAT(p_ngay_lam, '%d/%m/%Y'), N' đã được duyệt, chỉ được chấm công với SoGioLam = 0!');
            SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = v_error_message;
        END IF;
    END IF;
    
    IF p_so_gio_lam = -1 THEN
        DELETE FROM cham_cong WHERE ma_nv = p_ma_nv AND ngay_lam = p_ngay_lam;
    ELSEIF EXISTS (SELECT 1 FROM cham_cong WHERE ma_nv = p_ma_nv AND ngay_lam = p_ngay_lam) THEN
        UPDATE cham_cong SET 
            so_gio_lam = p_so_gio_lam, 
            vao_muon = p_vao_muon, 
            ve_som = p_ve_som 
        WHERE ma_nv = p_ma_nv AND ngay_lam = p_ngay_lam;
    ELSE
        INSERT INTO cham_cong(ma_nv, ngay_lam, so_gio_lam, vao_muon, ve_som) 
        VALUES(p_ma_nv, p_ngay_lam, p_so_gio_lam, p_vao_muon, p_ve_som);
    END IF;
END//

/* --------------------------------------
   Import chấm công
   -------------------------------------- */
DROP PROCEDURE IF EXISTS sp_cham_cong_import//

CREATE PROCEDURE sp_cham_cong_import(
    IN p_duong_dan_tap_tin VARCHAR(4000)
)
BEGIN
    -- MySQL không hỗ trợ BULK INSERT trực tiếp
    -- Cần sử dụng LOAD DATA INFILE hoặc công cụ bên ngoài
    SIGNAL SQLSTATE '45000' 
    SET MESSAGE_TEXT = N'Chức năng Import cần sử dụng LOAD DATA INFILE hoặc công cụ bên ngoài. Vui lòng tham khảo tài liệu MySQL.';
END//

/* --------------------------------------
   Export chấm công
   -------------------------------------- */
DROP PROCEDURE IF EXISTS sp_cham_cong_export//

CREATE PROCEDURE sp_cham_cong_export(
    IN p_thang INT,
    IN p_nam INT,
    IN p_duong_dan_tap_tin NVARCHAR(4000)
)
BEGIN
    -- MySQL sử dụng SELECT ... INTO OUTFILE
    SET @sql = CONCAT(
        'SELECT ma_nv, ngay_lam, so_gio_lam, vao_muon, ve_som ',
        'INTO OUTFILE ''', p_duong_dan_tap_tin, ''' ',
        'FIELDS TERMINATED BY '','' ',
        'ENCLOSED BY ''"'' ',
        'LINES TERMINATED BY ''\\n'' ',
        'FROM cham_cong ',
        'WHERE MONTH(ngay_lam) = ', p_thang, ' AND YEAR(ngay_lam) = ', p_nam
    );
    PREPARE stmt FROM @sql;
    EXECUTE stmt;
    DEALLOCATE PREPARE stmt;
END//

/* ============================
   HỆ SỐ LƯƠNG
   ============================ */

/* --------------------------------------
   Thêm lịch sử hệ số lương
   -------------------------------------- */
DROP PROCEDURE IF EXISTS sp_lich_su_he_so_luong_them//

CREATE PROCEDURE sp_lich_su_he_so_luong_them(
    IN p_ma_nv VARCHAR(5),
    IN p_he_so_luong DECIMAL(5,2),
    IN p_tu_ngay DATE,
    IN p_den_ngay DATE
)
BEGIN
    IF p_tu_ngay > p_den_ngay THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = N'Từ ngày phải nhỏ hơn hoặc bằng đến ngày.';
    END IF;
    IF NOT EXISTS (SELECT 1 FROM nhan_vien WHERE ma_nv = p_ma_nv) THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = N'Mã nhân viên không tồn tại.';
    END IF;
    INSERT INTO lich_su_he_so_luong (ma_nv, he_so_luong, tu_ngay, den_ngay) 
    VALUES (p_ma_nv, p_he_so_luong, p_tu_ngay, p_den_ngay);
END//

/* --------------------------------------
   Sửa lịch sử hệ số lương
   -------------------------------------- */
DROP PROCEDURE IF EXISTS sp_lich_su_he_so_luong_sua//

CREATE PROCEDURE sp_lich_su_he_so_luong_sua(
    IN p_ma_ls INT,
    IN p_he_so_luong DECIMAL(5,2),
    IN p_tu_ngay DATE,
    IN p_den_ngay DATE
)
BEGIN
    IF p_tu_ngay >= p_den_ngay THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = N'Từ ngày phải nhỏ hơn đến ngày.';
    END IF;
    IF NOT EXISTS (SELECT 1 FROM lich_su_he_so_luong WHERE ma_ls = p_ma_ls) THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = N'Mã lịch sử hệ số lương của nhân viên không tồn tại.';
    END IF;
    UPDATE lich_su_he_so_luong SET 
        he_so_luong = p_he_so_luong, 
        tu_ngay = p_tu_ngay, 
        den_ngay = p_den_ngay 
    WHERE ma_ls = p_ma_ls;
END//

/* --------------------------------------
   Xóa lịch sử hệ số lương
   -------------------------------------- */
DROP PROCEDURE IF EXISTS sp_lich_su_he_so_luong_xoa//

CREATE PROCEDURE sp_lich_su_he_so_luong_xoa(
    IN p_ma_ls INT
)
BEGIN
    IF NOT EXISTS (SELECT 1 FROM lich_su_he_so_luong WHERE ma_ls = p_ma_ls) THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = N'Mã lịch sử hệ số lương của nhân viên không tồn tại.';
    END IF;
    DELETE FROM lich_su_he_so_luong WHERE ma_ls = p_ma_ls;
END//

/* --------------------------------------
   Tìm kiếm lịch sử hệ số lương
   -------------------------------------- */
DROP PROCEDURE IF EXISTS sp_lich_su_he_so_luong_tim_kiem//

CREATE PROCEDURE sp_lich_su_he_so_luong_tim_kiem(
    IN p_ma_nv VARCHAR(5),
    IN p_tu_ngay DATE,
    IN p_den_ngay DATE
)
BEGIN
    SELECT ls.ma_ls, ls.ma_nv, ls.he_so_luong, ls.tu_ngay, ls.den_ngay 
    FROM lich_su_he_so_luong ls
    WHERE ls.ma_nv = p_ma_nv 
        AND (ls.tu_ngay <= p_den_ngay OR p_den_ngay IS NULL OR ls.tu_ngay IS NULL)
        AND (ls.den_ngay >= p_tu_ngay OR p_tu_ngay IS NULL OR ls.den_ngay IS NULL)
    ORDER BY den_ngay DESC;
END//

/* --------------------------------------
   Chi tiết lịch sử hệ số lương
   -------------------------------------- */
DROP PROCEDURE IF EXISTS sp_lich_su_he_so_luong_chi_tiet//

CREATE PROCEDURE sp_lich_su_he_so_luong_chi_tiet(
    IN p_ma_ls INT
)
BEGIN
    SELECT ls.ma_ls, ls.ma_nv, ls.he_so_luong, ls.tu_ngay, ls.den_ngay 
    FROM lich_su_he_so_luong ls 
    WHERE ls.ma_ls = p_ma_ls;
END//

/* ============================
   LƯƠNG
   ============================ */

/* --------------------------------------
   Chi tiết lương
   -------------------------------------- */
DROP PROCEDURE IF EXISTS sp_luong_chi_tiet//

CREATE PROCEDURE sp_luong_chi_tiet(
    IN p_ma_luong INT
)
BEGIN
    SELECT ma_nv, ky_luong, thuong, phat, thue, bao_hiem 
    FROM luong WHERE ma_luong = p_ma_luong;
END//

/* --------------------------------------
   Thêm lương
   -------------------------------------- */
DROP PROCEDURE IF EXISTS sp_luong_them//

CREATE PROCEDURE sp_luong_them(
    IN p_ma_nv VARCHAR(5),
    IN p_ky_luong DATE,
    IN p_thuong DECIMAL,
    IN p_phat DECIMAL,
    IN p_bao_hiem DECIMAL,
    IN p_thue DECIMAL
)
BEGIN
    IF NOT EXISTS (SELECT 1 FROM nhan_vien WHERE ma_nv = p_ma_nv) THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = N'Mã nhân viên không tồn tại.';
    END IF;
    IF NOT EXISTS (
        SELECT 1 FROM lich_su_he_so_luong 
        WHERE ma_nv = p_ma_nv AND p_ky_luong BETWEEN tu_ngay AND den_ngay
    ) THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = N'Nhân viên đã được trả lương trong kỳ này.';
    END IF;
    INSERT INTO luong (ma_nv, ky_luong, thuong, thue, phat, bao_hiem) 
    VALUES (p_ma_nv, p_ky_luong, p_thuong, p_thue, p_phat, p_bao_hiem);
END//

/* --------------------------------------
   Sửa lương
   -------------------------------------- */
DROP PROCEDURE IF EXISTS sp_luong_sua//

CREATE PROCEDURE sp_luong_sua(
    IN p_ma_luong INT,
    IN p_thuong DECIMAL,
    IN p_phat DECIMAL,
    IN p_bao_hiem DECIMAL,
    IN p_thue DECIMAL
)
BEGIN
    IF NOT EXISTS (SELECT 1 FROM luong WHERE ma_luong = p_ma_luong) THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = N'Mã lương không tồn tại.';
    END IF;
    UPDATE luong SET 
        thuong = p_thuong, 
        phat = p_phat, 
        bao_hiem = p_bao_hiem, 
        thue = p_thue 
    WHERE ma_luong = p_ma_luong;
END//

/* --------------------------------------
   Xóa lương
   -------------------------------------- */
DROP PROCEDURE IF EXISTS sp_luong_xoa//

CREATE PROCEDURE sp_luong_xoa(
    IN p_ma_luong INT
)
BEGIN
    IF NOT EXISTS (SELECT 1 FROM luong WHERE ma_luong = p_ma_luong) THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = N'Thông tin lương không tồn tại.';
    END IF;
    DELETE FROM luong WHERE ma_luong = p_ma_luong;
END//

/* --------------------------------------
   Tìm kiếm lương
   -------------------------------------- */
DROP PROCEDURE IF EXISTS sp_luong_tim_kiem//

CREATE PROCEDURE sp_luong_tim_kiem(
    IN p_tu_khoa NVARCHAR(MAX),
    IN p_ky_luong DATE,
    IN p_ma_pb INT,
    IN p_ma_cv INT
)
BEGIN
    SELECT nv.ma_nv, nv.ho_ten, nv.ngay_sinh, nv.gioi_tinh, nv.sdt, nv.email, nv.ngay_vao_lam, 
        nv.ma_pb, nv.ten_pb, nv.ma_cv,
        nv.ten_cv, nv.hoc_van, l.ma_luong, cv.he_so_phu_cap AS phu_cap, l.thuong, l.phat, l.bao_hiem, l.thue,
        fn_tinh_luong_thuc_nhan(nv.ma_nv, l.ky_luong) AS thuc_nhan,
        IFNULL(cc.so_ngay_cham_cong, 0) AS so_ngay_cham_cong,
        IFNULL(cc.so_lan_vao_muon, 0) AS so_lan_vao_muon,
        IFNULL(cc.so_lan_ve_som, 0) AS so_lan_ve_som
    FROM vw_danh_sach_nhan_vien_chi_tiet nv
    JOIN chuc_vu cv ON nv.ma_cv = cv.ma_cv
    LEFT JOIN (
        SELECT ma_nv, ma_luong, thuong, phat, bao_hiem, thue, ky_luong 
        FROM luong
        WHERE p_ky_luong IS NULL OR ky_luong = p_ky_luong
    ) l ON nv.ma_nv = l.ma_nv
    LEFT JOIN (
        SELECT ma_nv, 
            SUM(CASE WHEN so_gio_lam = 8 THEN 1 WHEN so_gio_lam = 4 THEN 0.5 ELSE 0 END) AS so_ngay_cham_cong,
            SUM(CASE WHEN vao_muon = 1 THEN 1 ELSE 0 END) AS so_lan_vao_muon,
            SUM(CASE WHEN ve_som = 1 THEN 1 ELSE 0 END) AS so_lan_ve_som
        FROM cham_cong 
        WHERE p_ky_luong IS NULL
            OR (ngay_lam >= DATE_FORMAT(p_ky_luong, '%Y-%m-01')
                AND ngay_lam < DATE_ADD(DATE_FORMAT(p_ky_luong, '%Y-%m-01'), INTERVAL 1 MONTH))
        GROUP BY ma_nv
    ) cc ON nv.ma_nv = cc.ma_nv
    WHERE (p_ma_pb IS NULL OR nv.ma_pb = p_ma_pb) 
        AND (p_ma_cv IS NULL OR nv.ma_cv = p_ma_cv) 
        AND (p_tu_khoa IS NULL
            OR nv.ma_nv LIKE CONCAT('%', p_tu_khoa, '%') 
            OR nv.ho_ten LIKE CONCAT('%', p_tu_khoa, '%')
            OR nv.ten_pb LIKE CONCAT('%', p_tu_khoa, '%') 
            OR nv.ten_cv LIKE CONCAT('%', p_tu_khoa, '%'))
    ORDER BY l.ky_luong DESC, nv.ma_nv ASC;
END//

/* --------------------------------------
   Xem lương
   -------------------------------------- */
DROP PROCEDURE IF EXISTS sp_luong_xem//

CREATE PROCEDURE sp_luong_xem(
    IN p_ma_nv VARCHAR(5),
    IN p_ky_luong DATE
)
BEGIN
    SELECT nv.ma_nv, nv.ho_ten, nv.ngay_sinh, nv.gioi_tinh, nv.sdt, nv.email, nv.ngay_vao_lam, 
        nv.ma_pb, nv.ten_pb, nv.ma_cv,
        nv.ten_cv, nv.hoc_van, l.ma_luong, cv.he_so_phu_cap AS phu_cap, l.thuong, l.phat, l.bao_hiem, l.thue,
        fn_tinh_luong_thuc_nhan(nv.ma_nv, l.ky_luong) AS thuc_nhan,
        IFNULL(cc.so_ngay_cham_cong, 0) AS so_ngay_cham_cong,
        IFNULL(cc.so_lan_vao_muon, 0) AS so_lan_vao_muon,
        IFNULL(cc.so_lan_ve_som, 0) AS so_lan_ve_som
    FROM vw_danh_sach_nhan_vien_chi_tiet nv
    JOIN chuc_vu cv ON nv.ma_cv = cv.ma_cv
    LEFT JOIN (
        SELECT ma_nv, ma_luong, thuong, phat, bao_hiem, thue, ky_luong 
        FROM luong
        WHERE p_ky_luong IS NULL OR ky_luong = p_ky_luong
    ) l ON nv.ma_nv = l.ma_nv
    LEFT JOIN (
        SELECT ma_nv, COUNT(*) AS so_ngay_cham_cong,
            SUM(CASE WHEN vao_muon = 1 THEN 1 ELSE 0 END) AS so_lan_vao_muon,
            SUM(CASE WHEN ve_som = 1 THEN 1 ELSE 0 END) AS so_lan_ve_som
        FROM cham_cong 
        WHERE p_ky_luong IS NULL
            OR (ngay_lam >= DATE_FORMAT(p_ky_luong, '%Y-%m-01')
                AND ngay_lam < DATE_ADD(DATE_FORMAT(p_ky_luong, '%Y-%m-01'), INTERVAL 1 MONTH))
        GROUP BY ma_nv
    ) cc ON nv.ma_nv = cc.ma_nv
    WHERE l.ma_nv = p_ma_nv
    LIMIT 1;
END//

/* --------------------------------------
   Báo cáo lương
   -------------------------------------- */
DROP PROCEDURE IF EXISTS sp_luong_bao_cao//

CREATE PROCEDURE sp_luong_bao_cao(
    IN p_ky_luong DATE
)
BEGIN
    DROP TEMPORARY TABLE IF EXISTS tmp_bao_cao_luong;
    CREATE TEMPORARY TABLE tmp_bao_cao_luong (
        stt INT,
        ma_nv VARCHAR(5),
        ho_ten NVARCHAR(50),
        phong_ban NVARCHAR(100),
        chuc_vu NVARCHAR(100),
        so_ngay_cong DECIMAL(10,2),
        so_lan_vao_muon INT,
        so_lan_ve_som INT,
        thuong DECIMAL(18,0),
        phat DECIMAL(18,0),
        bao_hiem DECIMAL(18,0),
        thue DECIMAL(18,0),
        luong_thuc_nhan BIGINT
    );
    
    INSERT INTO tmp_bao_cao_luong (stt, ma_nv, ho_ten, phong_ban, chuc_vu, so_ngay_cong, so_lan_vao_muon, so_lan_ve_som, thuong, phat, bao_hiem, thue, luong_thuc_nhan)
    SELECT 
        ROW_NUMBER() OVER (ORDER BY nv.ma_nv),
        nv.ma_nv,
        nv.ho_ten,
        pb.ten_pb,
        cv.ten_cv,
        fn_tinh_so_ngay_cong(nv.ma_nv, p_ky_luong),
        fn_tinh_so_lan_vao_muon(nv.ma_nv, p_ky_luong),
        fn_tinh_so_lan_ve_som(nv.ma_nv, p_ky_luong),
        IFNULL(l.thuong, 0),
        IFNULL(l.phat, 0),
        IFNULL(l.bao_hiem, 0),
        IFNULL(l.thue, 0),
        fn_tinh_luong_thuc_nhan(nv.ma_nv, p_ky_luong)
    FROM luong l 
    INNER JOIN nhan_vien nv ON nv.ma_nv = l.ma_nv
    INNER JOIN phong_ban pb ON pb.ma_pb = nv.ma_pb
    INNER JOIN chuc_vu cv ON cv.ma_cv = nv.ma_cv
    WHERE l.ky_luong = p_ky_luong
    ORDER BY nv.ma_nv;
    
    SELECT * FROM tmp_bao_cao_luong ORDER BY stt;
    DROP TEMPORARY TABLE tmp_bao_cao_luong;
END//

/* ============================
   SAO LƯU VÀ KHÔI PHỤC
   ============================ */

/* --------------------------------------
   Sao lưu database
   -------------------------------------- */
DROP PROCEDURE IF EXISTS sp_database_sao_luu//

CREATE PROCEDURE sp_database_sao_luu(
    IN p_ten_database NVARCHAR(100),
    IN p_duong_dan_tap_tin NVARCHAR(500)
)
BEGIN
    SET @sql = CONCAT('BACKUP DATABASE ', p_ten_database, ' TO DISK = ''', p_duong_dan_tap_tin, '''');
    PREPARE stmt FROM @sql;
    EXECUTE stmt;
    DEALLOCATE PREPARE stmt;
END//

/* --------------------------------------
   Khôi phục database
   -------------------------------------- */
DROP PROCEDURE IF EXISTS sp_database_khoi_phuc//

CREATE PROCEDURE sp_database_khoi_phuc(
    IN p_ten_database NVARCHAR(100),
    IN p_duong_dan_tap_tin NVARCHAR(500)
)
BEGIN
    SET @sql = CONCAT('RESTORE DATABASE ', p_ten_database, ' FROM DISK = ''', p_duong_dan_tap_tin, '''');
    PREPARE stmt FROM @sql;
    EXECUTE stmt;
    DEALLOCATE PREPARE stmt;
END//

DELIMITER ;