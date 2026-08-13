CREATE TABLE phong_ban (
    ma_pb INT AUTO_INCREMENT PRIMARY KEY,
    ten_pb NVARCHAR(100) NOT NULL
);
CREATE TABLE chuc_vu (
    ma_cv INT AUTO_INCREMENT PRIMARY KEY,
    ten_cv NVARCHAR(100) NOT NULL,
    he_so_phu_cap DECIMAL(5, 2) NOT NULL
);
CREATE TABLE vai_tro (
    ma_vt INT AUTO_INCREMENT PRIMARY KEY,
    ten_vt NVARCHAR(100) NOT NULL,
    mo_ta NVARCHAR(255) NULL
);
CREATE TABLE quyen (
    ma_quyen INT PRIMARY KEY,
    ky_hieu_quyen NVARCHAR(100) NOT NULL,
    ten_quyen NVARCHAR(50) NOT NULL,
    module NVARCHAR(50) NOT NULL
);
CREATE TABLE vai_tro_quyen (
    ma_vt INT NOT NULL,
    ma_quyen INT NOT NULL,
    PRIMARY KEY (ma_vt, ma_quyen),
    CONSTRAINT fk_vai_tro_quyen_quyen FOREIGN KEY (ma_quyen) REFERENCES quyen(ma_quyen),
    CONSTRAINT fk_vai_tro_quyen_vai_tro FOREIGN KEY (ma_vt) REFERENCES vai_tro(ma_vt)
);
CREATE TABLE trang_thai_lam_viec (
    ma_tt TINYINT AUTO_INCREMENT PRIMARY KEY,
    ten_tt NVARCHAR(50) NOT NULL
);
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
CREATE TABLE loai_hop_dong (
    ma_lhd INT AUTO_INCREMENT PRIMARY KEY,
    ten_lhd NVARCHAR(255) NOT NULL
);
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
CREATE TABLE loai_phep (
    ma_lp INT AUTO_INCREMENT PRIMARY KEY,
    ten_lp NVARCHAR(255) NOT NULL
);
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
CREATE TABLE lich_su_he_so_luong (
    ma_ls INT AUTO_INCREMENT PRIMARY KEY,
    ma_nv VARCHAR(5) NOT NULL,
    he_so_luong DECIMAL(5, 2) NOT NULL,
    tu_ngay DATE NOT NULL,
    den_ngay DATE NOT NULL,
    CONSTRAINT fk_lich_su_he_so_luong_nhan_vien FOREIGN KEY (ma_nv) REFERENCES nhan_vien(ma_nv),
    CONSTRAINT ck_lshsl_tu_ngay_den_ngay CHECK (tu_ngay <= den_ngay)
);
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
DELIMITER //
CREATE FUNCTION fn_dem_nhan_vien_theo_phong_ban(p_ma_pb INT)
RETURNS INT
DETERMINISTIC
BEGIN
    DECLARE v_so_luong INT;
    SELECT COUNT(*) INTO v_so_luong FROM nhan_vien WHERE ma_pb = p_ma_pb;
    RETURN IFNULL(v_so_luong, 0);
END//
CREATE FUNCTION fn_dem_nhan_vien_theo_chuc_vu(p_ma_cv INT)
RETURNS INT
DETERMINISTIC
BEGIN
    DECLARE v_so_luong INT;
    SELECT COUNT(*) INTO v_so_luong FROM nhan_vien WHERE ma_cv = p_ma_cv;
    RETURN IFNULL(v_so_luong, 0);
END//
CREATE PROCEDURE sp_nhan_vien_tim_kiem(
    IN p_tu_khoa NVARCHAR(100), IN p_ma_pb INT, IN p_ma_cv INT, IN p_ma_tt TINYINT
)
BEGIN
    SET p_tu_khoa = LTRIM(RTRIM(IFNULL(p_tu_khoa, N'')));
    SELECT * FROM vw_danh_sach_nhan_vien_chi_tiet WHERE
        (p_tu_khoa = N'' OR ma_nv LIKE CONCAT('%', p_tu_khoa, '%') OR ho_ten LIKE CONCAT('%', p_tu_khoa, '%')
            OR sdt LIKE CONCAT('%', p_tu_khoa, '%') OR email LIKE CONCAT('%', p_tu_khoa, '%')
            OR cccd LIKE CONCAT('%', p_tu_khoa, '%') OR ten_pb LIKE CONCAT('%', p_tu_khoa, '%')
            OR ten_cv LIKE CONCAT('%', p_tu_khoa, '%'))
        AND (p_ma_pb IS NULL OR ma_pb = p_ma_pb)
        AND (p_ma_cv IS NULL OR ma_cv = p_ma_cv)
        AND (p_ma_tt IS NULL OR ma_tt = p_ma_tt)
    ORDER BY ma_nv;
END//
CREATE PROCEDURE sp_nhan_vien_danh_sach()
BEGIN
    SELECT * FROM vw_danh_sach_nhan_vien_chi_tiet ORDER BY ma_nv;
END//
CREATE PROCEDURE sp_nhan_vien_chi_tiet(IN p_ma_nv VARCHAR(5))
BEGIN
    SELECT * FROM vw_danh_sach_nhan_vien_chi_tiet WHERE ma_nv = p_ma_nv;
END//
CREATE PROCEDURE sp_nhan_vien_them(
    IN p_ma_nv VARCHAR(5), IN p_ho_ten NVARCHAR(50), IN p_ngay_sinh DATE, IN p_gioi_tinh TINYINT,
    IN p_sdt VARCHAR(15), IN p_email NVARCHAR(50), IN p_ngay_vao_lam DATE, IN p_ma_pb INT, IN p_ma_cv INT,
    IN p_dan_toc NVARCHAR(50), IN p_cccd VARCHAR(12), IN p_noi_cap_cccd NVARCHAR(50), IN p_hoc_van NVARCHAR(50),
    IN p_ma_tt TINYINT, IN p_mat_khau VARCHAR(255), IN p_ma_vt INT
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
    IF p_ma_nv = '' OR LENGTH(p_ma_nv) > 5 THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = N'Mã nhân viên không hợp lệ. MaNV tối đa 5 ký tự.'; END IF;
    IF EXISTS (SELECT 1 FROM nhan_vien WHERE ma_nv = p_ma_nv) THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = N'Mã nhân viên đã tồn tại.'; END IF;
    IF p_ho_ten = N'' THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = N'Họ tên không được rỗng.'; END IF;
    IF p_gioi_tinh NOT IN (0, 1) THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = N'Giới tính không hợp lệ. Dùng 1 = Nam, 0 = Nữ.'; END IF;
    IF p_ngay_sinh >= p_ngay_vao_lam OR TIMESTAMPDIFF(YEAR, p_ngay_sinh, p_ngay_vao_lam) < 18 THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = N'Ngày sinh không hợp lệ.'; END IF;
    IF NOT EXISTS (SELECT 1 FROM phong_ban WHERE ma_pb = p_ma_pb) THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = N'Mã phòng ban không tồn tại.'; END IF;
    IF NOT EXISTS (SELECT 1 FROM chuc_vu WHERE ma_cv = p_ma_cv) THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = N'Mã chức vụ không tồn tại.'; END IF;
    IF NOT EXISTS (SELECT 1 FROM trang_thai_lam_viec WHERE ma_tt = p_ma_tt) THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = N'Mã trạng thái làm việc không tồn tại.'; END IF;
    IF p_cccd NOT REGEXP '^[0-9]{12}$' OR EXISTS (SELECT 1 FROM nhan_vien WHERE cccd = p_cccd) THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = N'CCCD không hợp lệ.'; END IF;
    IF p_sdt = '' OR p_sdt NOT REGEXP '^[0-9]' THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = N'Số điện thoại không hợp lệ.'; END IF;
    IF p_email = N'' OR p_email NOT LIKE '%_@_%_.%' THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = N'Email không hợp lệ.'; END IF;
    IF p_mat_khau IS NULL OR LTRIM(RTRIM(p_mat_khau)) = '' THEN SET v_mat_khau = SHA2('123456', 256); ELSE SET v_mat_khau = SHA2(p_mat_khau, 256); END IF;
    INSERT INTO nhan_vien(ma_nv, ho_ten, ngay_sinh, gioi_tinh, sdt, email, ngay_vao_lam, ma_pb, ma_cv, dan_toc, cccd, noi_cap_cccd, hoc_van, ma_tt, mat_khau, ma_vt)
    VALUES(p_ma_nv, p_ho_ten, p_ngay_sinh, p_gioi_tinh, p_sdt, p_email, p_ngay_vao_lam, p_ma_pb, p_ma_cv, p_dan_toc, p_cccd, p_noi_cap_cccd, p_hoc_van, p_ma_tt, v_mat_khau, p_ma_vt);
END//
CREATE PROCEDURE sp_nhan_vien_sua(
    IN p_ma_nv VARCHAR(5), IN p_ho_ten NVARCHAR(50), IN p_ngay_sinh DATE, IN p_gioi_tinh TINYINT,
    IN p_sdt VARCHAR(15), IN p_email NVARCHAR(50), IN p_ngay_vao_lam DATE, IN p_ma_pb INT, IN p_ma_cv INT,
    IN p_dan_toc NVARCHAR(50), IN p_cccd VARCHAR(12), IN p_noi_cap_cccd NVARCHAR(50), IN p_hoc_van NVARCHAR(50),
    IN p_ma_tt TINYINT, IN p_mat_khau VARCHAR(255), IN p_ma_vt INT
)
BEGIN
    DECLARE v_mat_khau VARCHAR(255);
    SET p_ma_nv = UPPER(LTRIM(RTRIM(IFNULL(p_ma_nv, ''))));
    IF NOT EXISTS (SELECT 1 FROM nhan_vien WHERE ma_nv = p_ma_nv) THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = N'Không tìm thấy nhân viên cần sửa.'; END IF;
    IF p_ho_ten IS NULL OR LTRIM(RTRIM(p_ho_ten)) = N'' THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = N'Họ tên không được rỗng.'; END IF;
    IF p_gioi_tinh NOT IN (0, 1) THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = N'Giới tính không hợp lệ.'; END IF;
    IF p_ngay_sinh >= p_ngay_vao_lam OR TIMESTAMPDIFF(YEAR, p_ngay_sinh, p_ngay_vao_lam) < 18 THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = N'Ngày sinh không hợp lệ.'; END IF;
    IF NOT EXISTS (SELECT 1 FROM phong_ban WHERE ma_pb = p_ma_pb) OR NOT EXISTS (SELECT 1 FROM chuc_vu WHERE ma_cv = p_ma_cv) OR NOT EXISTS (SELECT 1 FROM trang_thai_lam_viec WHERE ma_tt = p_ma_tt) THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = N'Dữ liệu tham chiếu không tồn tại.'; END IF;
    IF p_cccd NOT REGEXP '^[0-9]{12}$' OR EXISTS (SELECT 1 FROM nhan_vien WHERE cccd = p_cccd AND ma_nv <> p_ma_nv) THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = N'CCCD không hợp lệ.'; END IF;
    IF p_sdt IS NULL OR LTRIM(RTRIM(p_sdt)) = '' OR p_sdt NOT REGEXP '^[0-9]' THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = N'Số điện thoại không hợp lệ.'; END IF;
    IF p_email IS NULL OR LTRIM(RTRIM(p_email)) = N'' OR p_email NOT LIKE '%_@_%_.%' THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = N'Email không hợp lệ.'; END IF;
    IF p_mat_khau IS NULL OR LTRIM(RTRIM(p_mat_khau)) = '' THEN SET v_mat_khau = SHA2('123456', 256); ELSE SET v_mat_khau = SHA2(p_mat_khau, 256); END IF;
    UPDATE nhan_vien SET ho_ten = LTRIM(RTRIM(p_ho_ten)), ngay_sinh = p_ngay_sinh, gioi_tinh = p_gioi_tinh, sdt = LTRIM(RTRIM(p_sdt)), email = LTRIM(RTRIM(p_email)), ngay_vao_lam = p_ngay_vao_lam, ma_pb = p_ma_pb, ma_cv = p_ma_cv, dan_toc = LTRIM(RTRIM(p_dan_toc)), cccd = LTRIM(RTRIM(p_cccd)), noi_cap_cccd = LTRIM(RTRIM(p_noi_cap_cccd)), hoc_van = LTRIM(RTRIM(p_hoc_van)), ma_tt = p_ma_tt, mat_khau = v_mat_khau, ma_vt = p_ma_vt WHERE ma_nv = p_ma_nv;
END//
CREATE PROCEDURE sp_nhan_vien_xoa(IN p_ma_nv VARCHAR(5))
BEGIN
    SET p_ma_nv = UPPER(LTRIM(RTRIM(IFNULL(p_ma_nv, ''))));
    IF p_ma_nv = '' OR NOT EXISTS (SELECT 1 FROM nhan_vien WHERE ma_nv = p_ma_nv) THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = N'Không tìm thấy nhân viên cần xóa.'; END IF;
    IF EXISTS (SELECT 1 FROM hop_dong WHERE ma_nv = p_ma_nv) OR EXISTS (SELECT 1 FROM cham_cong WHERE ma_nv = p_ma_nv) OR EXISTS (SELECT 1 FROM nghi_phep WHERE ma_nv = p_ma_nv) OR EXISTS (SELECT 1 FROM luong WHERE ma_nv = p_ma_nv) OR EXISTS (SELECT 1 FROM lich_su_he_so_luong WHERE ma_nv = p_ma_nv) THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = N'Không thể xóa nhân viên còn dữ liệu liên quan.'; END IF;
    DELETE FROM nhan_vien WHERE ma_nv = p_ma_nv;
END//
CREATE PROCEDURE sp_nhan_vien_dang_nhap(IN p_ten_dang_nhap NVARCHAR(50), IN p_mat_khau VARCHAR(255))
BEGIN
    DECLARE v_mat_khau_hash VARCHAR(255);
    SET v_mat_khau_hash = SHA2(p_mat_khau, 256);
    IF NOT EXISTS (SELECT 1 FROM nhan_vien WHERE ma_nv = p_ten_dang_nhap) THEN SELECT -1 AS ket_qua, N'Tài khoản không tồn tại' AS thong_bao;
    ELSEIF NOT EXISTS (SELECT 1 FROM nhan_vien WHERE ma_nv = p_ten_dang_nhap AND mat_khau = v_mat_khau_hash) THEN SELECT -2 AS ket_qua, N'Sai mật khẩu' AS thong_bao;
    ELSE SELECT 1 AS ket_qua;
    END IF;
END//
DELIMITER ;
