-- Disposable legacy fixture transform. The test first loads the deterministic
-- fresh seed, then this script restores the former 16-table shape: target
-- tables plus dia_chi_nhan_vien and the two legacy symbol columns.

ALTER TABLE nhan_vien
    DROP COLUMN dia_chi_cu_the,
    DROP COLUMN phuong_xa,
    DROP COLUMN quan_huyen,
    DROP COLUMN tinh_thanh,
    DROP COLUMN anh_dai_dien,
    DROP COLUMN ngay_nghi_viec;

ALTER TABLE vai_tro ADD COLUMN ky_hieu VARCHAR(50) NULL;
UPDATE vai_tro SET ky_hieu = CASE ma_vt
    WHEN 1 THEN 'SUPER_ADMIN'
    WHEN 2 THEN 'NHAN_SU'
    WHEN 3 THEN 'CBL'
    WHEN 4 THEN 'TRUONG_PHONG'
    WHEN 5 THEN 'NHAN_VIEN'
END;

ALTER TABLE trang_thai_lam_viec ADD COLUMN ky_hieu VARCHAR(20) NULL;
UPDATE trang_thai_lam_viec SET ky_hieu = CASE ma_tt
    WHEN 1 THEN 'THU_VIEC'
    WHEN 2 THEN 'DANG_LAM'
    WHEN 3 THEN 'TAM_NGHI_KHONG_LUONG'
    WHEN 4 THEN 'DA_NGHI'
END;

CREATE TABLE dia_chi_nhan_vien (
    ma_nv VARCHAR(5) NOT NULL PRIMARY KEY,
    dia_chi_cu_the VARCHAR(255) NULL,
    phuong_xa VARCHAR(100) NULL,
    quan_huyen VARCHAR(100) NULL,
    tinh_thanh VARCHAR(100) NULL,
    CONSTRAINT fk_dia_chi_nhan_vien_nhan_vien
        FOREIGN KEY (ma_nv) REFERENCES nhan_vien (ma_nv)
) ENGINE = InnoDB;

INSERT INTO dia_chi_nhan_vien
VALUES ('NV001', 'Số 01 đường Lê Lợi', 'Phường Bến Nghé', 'Quận 1', 'TP Hồ Chí Minh');

-- Force the migration to provision 105 and its required mappings.
DELETE FROM vai_tro_quyen WHERE ma_quyen = 105;
-- Existing unrelated module data must survive the employee/RBAC migration.
INSERT INTO vai_tro_quyen (ma_vt, ma_quyen) VALUES (2, 501);
DELETE FROM quyen WHERE ma_quyen = 105;
