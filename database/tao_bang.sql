USE quan_ly_nhan_su;

-- ============================================================
-- TẠO BẢNG
-- ============================================================

/* --------------------------------------
   Bảng phòng ban
   -------------------------------------- */
CREATE TABLE IF NOT EXISTS phong_ban (
    ma_pb INT AUTO_INCREMENT PRIMARY KEY,
    ten_pb NVARCHAR(100) NOT NULL
);

/* --------------------------------------
   Bảng chức vụ
   -------------------------------------- */
CREATE TABLE IF NOT EXISTS chuc_vu (
    ma_cv INT AUTO_INCREMENT PRIMARY KEY,
    ten_cv NVARCHAR(100) NOT NULL,
    he_so_phu_cap DECIMAL(5, 2) NOT NULL
);

/* --------------------------------------
   Bảng vai trò
   -------------------------------------- */
CREATE TABLE IF NOT EXISTS vai_tro (
    ma_vt INT AUTO_INCREMENT PRIMARY KEY,
    ten_vt NVARCHAR(100) NOT NULL,
    mo_ta NVARCHAR(255) NULL
);

/* --------------------------------------
   Bảng quyền
   -------------------------------------- */
CREATE TABLE IF NOT EXISTS quyen (
    ma_quyen INT PRIMARY KEY,
    ky_hieu_quyen NVARCHAR(100) NOT NULL,
    ten_quyen NVARCHAR(50) NOT NULL,
    module NVARCHAR(50) NOT NULL
);

/* --------------------------------------
   Bảng vai trò quyền
   -------------------------------------- */
CREATE TABLE IF NOT EXISTS vai_tro_quyen (
    ma_vt INT NOT NULL,
    ma_quyen INT NOT NULL,
    PRIMARY KEY (ma_vt, ma_quyen),
    CONSTRAINT fk_vai_tro_quyen_quyen FOREIGN KEY (ma_quyen) REFERENCES quyen(ma_quyen),
    CONSTRAINT fk_vai_tro_quyen_vai_tro FOREIGN KEY (ma_vt) REFERENCES vai_tro(ma_vt)
);

/* --------------------------------------
   Bảng trạng thái làm việc
   -------------------------------------- */
CREATE TABLE IF NOT EXISTS trang_thai_lam_viec (
    ma_tt TINYINT AUTO_INCREMENT PRIMARY KEY,
    ten_tt NVARCHAR(50) NOT NULL
);

/* --------------------------------------
   Bảng nhân viên
   -------------------------------------- */
CREATE TABLE IF NOT EXISTS nhan_vien (
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
CREATE TABLE IF NOT EXISTS loai_hop_dong (
    ma_lhd INT AUTO_INCREMENT PRIMARY KEY,
    ten_lhd NVARCHAR(255) NOT NULL
);

/* --------------------------------------
   Bảng hợp đồng
   -------------------------------------- */
CREATE TABLE IF NOT EXISTS hop_dong (
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
CREATE TABLE IF NOT EXISTS loai_phep (
    ma_lp INT AUTO_INCREMENT PRIMARY KEY,
    ten_lp NVARCHAR(255) NOT NULL
);

/* --------------------------------------
   Bảng nghỉ phép
   -------------------------------------- */
CREATE TABLE IF NOT EXISTS nghi_phep (
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
CREATE TABLE IF NOT EXISTS cham_cong (
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
CREATE TABLE IF NOT EXISTS lich_su_he_so_luong (
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
CREATE TABLE IF NOT EXISTS luong (
    ma_luong INT AUTO_INCREMENT PRIMARY KEY,
    ma_nv VARCHAR(5) NOT NULL,
    ky_luong DATE NOT NULL,
    thuong DECIMAL(18, 0) NULL,
    phat DECIMAL(18, 0) NULL,
    bao_hiem DECIMAL(18, 0) NULL,
    thue DECIMAL(18, 0) NULL,
    CONSTRAINT fk_luong_nhan_vien FOREIGN KEY (ma_nv) REFERENCES nhan_vien(ma_nv)
);
