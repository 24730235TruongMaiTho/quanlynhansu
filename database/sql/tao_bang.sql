-- Fresh 15-table schema for quan_ly_nhan_su.
-- This file creates tables only: no view, trigger or stored procedure is
-- required by the employee/auth/RBAC module. Run only on a disposable or
-- explicitly approved empty database.

USE quan_ly_nhan_su;

CREATE TABLE IF NOT EXISTS phong_ban (
    ma_pb INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
    ten_pb VARCHAR(100) NOT NULL,
    CONSTRAINT uq_phong_ban_ten UNIQUE (ten_pb)
) ENGINE = InnoDB;

CREATE TABLE IF NOT EXISTS chuc_vu (
    ma_cv INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
    ten_cv VARCHAR(100) NOT NULL,
    he_so_phu_cap DECIMAL(5, 2) NOT NULL,
    CONSTRAINT uq_chuc_vu_ten UNIQUE (ten_cv)
) ENGINE = InnoDB;

CREATE TABLE IF NOT EXISTS vai_tro (
    ma_vt INT NOT NULL PRIMARY KEY,
    ten_vt VARCHAR(100) NOT NULL,
    mo_ta VARCHAR(255) NULL,
    CONSTRAINT uq_vai_tro_ten UNIQUE (ten_vt)
) ENGINE = InnoDB;

CREATE TABLE IF NOT EXISTS quyen (
    ma_quyen INT NOT NULL PRIMARY KEY,
    ky_hieu_quyen VARCHAR(100) NOT NULL,
    ten_quyen VARCHAR(100) NOT NULL,
    module VARCHAR(50) NOT NULL,
    CONSTRAINT uq_quyen_ky_hieu UNIQUE (ky_hieu_quyen)
) ENGINE = InnoDB;

CREATE TABLE IF NOT EXISTS vai_tro_quyen (
    ma_vt INT NOT NULL,
    ma_quyen INT NOT NULL,
    PRIMARY KEY (ma_vt, ma_quyen),
    CONSTRAINT fk_vai_tro_quyen_vai_tro FOREIGN KEY (ma_vt) REFERENCES vai_tro (ma_vt),
    CONSTRAINT fk_vai_tro_quyen_quyen FOREIGN KEY (ma_quyen) REFERENCES quyen (ma_quyen)
) ENGINE = InnoDB;

CREATE TABLE IF NOT EXISTS trang_thai_lam_viec (
    ma_tt TINYINT UNSIGNED NOT NULL PRIMARY KEY,
    ten_tt VARCHAR(50) NOT NULL,
    CONSTRAINT uq_trang_thai_lam_viec_ten UNIQUE (ten_tt)
) ENGINE = InnoDB;

CREATE TABLE IF NOT EXISTS nhan_vien (
    ma_nv VARCHAR(5) NOT NULL PRIMARY KEY,
    ho_ten VARCHAR(50) NOT NULL,
    ngay_sinh DATE NOT NULL,
    gioi_tinh TINYINT NOT NULL,
    sdt VARCHAR(15) NOT NULL,
    email VARCHAR(100) NOT NULL,
    ngay_vao_lam DATE NOT NULL,
    ma_pb INT NOT NULL,
    ma_cv INT NOT NULL,
    dan_toc VARCHAR(50) NOT NULL,
    cccd VARCHAR(12) NOT NULL,
    noi_cap_cccd VARCHAR(50) NOT NULL,
    hoc_van VARCHAR(50) NOT NULL,
    ma_tt TINYINT UNSIGNED NOT NULL,
    mat_khau VARCHAR(255) NOT NULL,
    ma_vt INT NOT NULL,
    dia_chi_cu_the VARCHAR(255) NULL,
    phuong_xa VARCHAR(100) NULL,
    quan_huyen VARCHAR(100) NULL,
    tinh_thanh VARCHAR(100) NULL,
    anh_dai_dien VARCHAR(255) NULL,
    ngay_nghi_viec DATE NULL,
    CONSTRAINT uq_nhan_vien_email UNIQUE (email),
    CONSTRAINT uq_nhan_vien_cccd UNIQUE (cccd),
    CONSTRAINT fk_nhan_vien_phong_ban FOREIGN KEY (ma_pb) REFERENCES phong_ban (ma_pb),
    CONSTRAINT fk_nhan_vien_chuc_vu FOREIGN KEY (ma_cv) REFERENCES chuc_vu (ma_cv),
    CONSTRAINT fk_nhan_vien_trang_thai FOREIGN KEY (ma_tt) REFERENCES trang_thai_lam_viec (ma_tt),
    CONSTRAINT fk_nhan_vien_vai_tro FOREIGN KEY (ma_vt) REFERENCES vai_tro (ma_vt)
) ENGINE = InnoDB;

CREATE TABLE IF NOT EXISTS bo_dem_ma_nhan_vien (
    ten_bo_dem VARCHAR(32) NOT NULL PRIMARY KEY,
    so_da_cap SMALLINT UNSIGNED NOT NULL
) ENGINE = InnoDB;

CREATE TABLE IF NOT EXISTS loai_hop_dong (
    ma_lhd INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
    ten_lhd VARCHAR(255) NOT NULL,
    CONSTRAINT uq_loai_hop_dong_ten UNIQUE (ten_lhd)
) ENGINE = InnoDB;

CREATE TABLE IF NOT EXISTS hop_dong (
    ma_hd INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
    ma_nv VARCHAR(5) NOT NULL,
    ma_lhd INT NOT NULL,
    ngay_ky DATE NOT NULL,
    ngay_het_han DATE NULL,
    luong_co_ban DECIMAL(18, 0) NOT NULL,
    CONSTRAINT fk_hop_dong_nhan_vien FOREIGN KEY (ma_nv) REFERENCES nhan_vien (ma_nv),
    CONSTRAINT fk_hop_dong_loai FOREIGN KEY (ma_lhd) REFERENCES loai_hop_dong (ma_lhd)
) ENGINE = InnoDB;

CREATE TABLE IF NOT EXISTS loai_phep (
    ma_lp INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
    ten_lp VARCHAR(255) NOT NULL,
    CONSTRAINT uq_loai_phep_ten UNIQUE (ten_lp)
) ENGINE = InnoDB;

CREATE TABLE IF NOT EXISTS nghi_phep (
    ma_np INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
    ma_nv VARCHAR(5) NOT NULL,
    tu_ngay DATE NOT NULL,
    den_ngay DATE NOT NULL,
    ma_lp INT NOT NULL,
    ly_do VARCHAR(255) NOT NULL,
    trang_thai_duyet TINYINT NOT NULL,
    CONSTRAINT fk_nghi_phep_nhan_vien FOREIGN KEY (ma_nv) REFERENCES nhan_vien (ma_nv),
    CONSTRAINT fk_nghi_phep_loai FOREIGN KEY (ma_lp) REFERENCES loai_phep (ma_lp),
    CONSTRAINT ck_nghi_phep_ngay CHECK (den_ngay >= tu_ngay)
) ENGINE = InnoDB;

CREATE TABLE IF NOT EXISTS cham_cong (
    ma_cc INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
    ma_nv VARCHAR(5) NOT NULL,
    ngay_lam DATE NOT NULL,
    so_gio_lam SMALLINT NOT NULL,
    vao_muon BIT NOT NULL,
    ve_som BIT NOT NULL,
    CONSTRAINT uq_cham_cong_nhan_vien_ngay UNIQUE (ma_nv, ngay_lam),
    CONSTRAINT fk_cham_cong_nhan_vien FOREIGN KEY (ma_nv) REFERENCES nhan_vien (ma_nv)
) ENGINE = InnoDB;

CREATE TABLE IF NOT EXISTS lich_su_he_so_luong (
    ma_ls INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
    ma_nv VARCHAR(5) NOT NULL,
    he_so_luong DECIMAL(5, 2) NOT NULL,
    tu_ngay DATE NOT NULL,
    den_ngay DATE NOT NULL,
    CONSTRAINT fk_lich_su_he_so_luong_nhan_vien FOREIGN KEY (ma_nv) REFERENCES nhan_vien (ma_nv),
    CONSTRAINT ck_lich_su_he_so_luong_ngay CHECK (tu_ngay <= den_ngay)
) ENGINE = InnoDB;

CREATE TABLE IF NOT EXISTS luong (
    ma_luong INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
    ma_nv VARCHAR(5) NOT NULL,
    ky_luong DATE NOT NULL,
    thuong DECIMAL(18, 0) NULL,
    phat DECIMAL(18, 0) NULL,
    bao_hiem DECIMAL(18, 0) NULL,
    thue DECIMAL(18, 0) NULL,
    CONSTRAINT uq_luong_nhan_vien_ky UNIQUE (ma_nv, ky_luong),
    CONSTRAINT fk_luong_nhan_vien FOREIGN KEY (ma_nv) REFERENCES nhan_vien (ma_nv)
) ENGINE = InnoDB;
