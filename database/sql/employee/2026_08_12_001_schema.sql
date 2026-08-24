-- LEGACY EMPLOYEE MIGRATION (superseded 2026-08-24). Historical only; do not
-- run as active setup. Use the exact 15-table fresh pair or the reviewed
-- 2026_08_24_001 migration for an existing disposable database.

DELIMITER //

BEGIN NOT ATOMIC
    IF EXISTS (
        SELECT 1
        FROM nhan_vien
        WHERE email IS NULL
           OR TRIM(email) = ''
           OR TRIM(email) NOT REGEXP '^[^@[:space:]]+@[^@[:space:]]+[.][^@[:space:]]+$'
    ) OR EXISTS (
        SELECT 1
        FROM nhan_vien
        GROUP BY LOWER(TRIM(email))
        HAVING COUNT(*) > 1
    ) THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'NV_MIGRATION_EMAIL_INVALID';
    END IF;

    IF EXISTS (
        SELECT 1
        FROM nhan_vien
        WHERE cccd IS NULL
           OR TRIM(cccd) NOT REGEXP '^[0-9]{12}$'
           OR BINARY ma_nv NOT REGEXP '^NV(00[1-9]|0[1-9][0-9]|[1-9][0-9]{2})$'
    ) OR EXISTS (
        SELECT 1
        FROM nhan_vien
        GROUP BY TRIM(cccd)
        HAVING COUNT(*) > 1
    ) THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'NV_MIGRATION_CCCD_INVALID';
    END IF;

    IF (SELECT COUNT(*) FROM trang_thai_lam_viec) > 0 AND (
        (SELECT COUNT(*) FROM trang_thai_lam_viec) <> 3
        OR (SELECT COUNT(*) FROM trang_thai_lam_viec WHERE BINARY LOWER(TRIM(ten_tt)) = BINARY 'đang làm việc') <> 1
        OR (SELECT COUNT(*) FROM trang_thai_lam_viec WHERE BINARY LOWER(TRIM(ten_tt)) = BINARY 'thử việc') <> 1
        OR (SELECT COUNT(*) FROM trang_thai_lam_viec WHERE BINARY LOWER(TRIM(ten_tt)) = BINARY 'đã nghỉ') <> 1
    ) THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'NV_MIGRATION_STATUS_AMBIGUOUS';
    END IF;

    IF EXISTS (
        SELECT 1
        FROM nhan_vien nv
        JOIN trang_thai_lam_viec ttlv ON ttlv.ma_tt = nv.ma_tt
        WHERE BINARY LOWER(TRIM(ttlv.ten_tt)) = BINARY 'đã nghỉ'
    ) THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'NV_MIGRATION_EXISTING_TERMINATION_DATE_REQUIRED';
    END IF;

    IF (SELECT COUNT(*) FROM vai_tro WHERE BINARY LOWER(TRIM(ten_vt)) = BINARY 'nhân viên mặc định') > 1
       OR EXISTS (
            SELECT 1
            FROM vai_tro vt
            JOIN vai_tro_quyen vtq ON vtq.ma_vt = vt.ma_vt
            WHERE BINARY LOWER(TRIM(vt.ten_vt)) = BINARY 'nhân viên mặc định'
       ) THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'NV_MIGRATION_ROLE_AMBIGUOUS';
    END IF;

    IF EXISTS (
        SELECT 1
        FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME = 'vai_tro'
          AND COLUMN_NAME = 'ky_hieu'
    ) THEN
        IF EXISTS (
            SELECT 1
            FROM vai_tro
            WHERE ky_hieu = 'NHAN_VIEN_MAC_DINH'
              AND BINARY LOWER(TRIM(ten_vt)) <> BINARY 'nhân viên mặc định'
        ) OR EXISTS (
            SELECT 1
            FROM vai_tro
            WHERE BINARY LOWER(TRIM(ten_vt)) = BINARY 'nhân viên mặc định'
              AND ky_hieu IS NOT NULL
              AND BINARY ky_hieu <> BINARY 'NHAN_VIEN_MAC_DINH'
        ) OR EXISTS (
            SELECT 1
            FROM vai_tro
            WHERE ky_hieu IS NOT NULL
            GROUP BY ky_hieu
            HAVING COUNT(*) > 1
        ) THEN
            SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'NV_MIGRATION_ROLE_AMBIGUOUS';
        END IF;
    END IF;
END//

DELIMITER ;

UPDATE nhan_vien
SET email = LOWER(TRIM(email)),
    cccd = TRIM(cccd);

ALTER TABLE trang_thai_lam_viec
    ADD COLUMN ky_hieu VARCHAR(20) NULL;

UPDATE trang_thai_lam_viec
SET ky_hieu = CASE
    WHEN BINARY LOWER(TRIM(ten_tt)) = BINARY 'đang làm việc' THEN 'DANG_LAM'
    WHEN BINARY LOWER(TRIM(ten_tt)) = BINARY 'thử việc' THEN 'THU_VIEC'
    WHEN BINARY LOWER(TRIM(ten_tt)) = BINARY 'đã nghỉ' THEN 'DA_NGHI'
END;

INSERT INTO trang_thai_lam_viec (ten_tt, ky_hieu)
SELECT seeded.ten_tt, seeded.ky_hieu
FROM (
    SELECT 'DANG_LAM' AS ky_hieu, 'Đang làm việc' AS ten_tt
    UNION ALL SELECT 'THU_VIEC', 'Thử việc'
    UNION ALL SELECT 'DA_NGHI', 'Đã nghỉ'
) seeded
WHERE NOT EXISTS (SELECT 1 FROM trang_thai_lam_viec);

ALTER TABLE trang_thai_lam_viec
    MODIFY ky_hieu VARCHAR(20) NOT NULL,
    ADD CONSTRAINT uq_trang_thai_lam_viec_ky_hieu UNIQUE (ky_hieu);

ALTER TABLE vai_tro
    ADD COLUMN IF NOT EXISTS ky_hieu VARCHAR(50) NULL;

INSERT INTO vai_tro (ten_vt, mo_ta, ky_hieu)
SELECT 'Nhân viên mặc định', 'Vai trò hệ thống mặc định không có quyền', 'NHAN_VIEN_MAC_DINH'
WHERE NOT EXISTS (
    SELECT 1 FROM vai_tro
    WHERE BINARY LOWER(TRIM(ten_vt)) = BINARY 'nhân viên mặc định'
);

UPDATE vai_tro
SET ky_hieu = 'NHAN_VIEN_MAC_DINH'
WHERE BINARY LOWER(TRIM(ten_vt)) = BINARY 'nhân viên mặc định';

DELIMITER //

BEGIN NOT ATOMIC
    IF (SELECT COUNT(*) FROM vai_tro WHERE BINARY ky_hieu = BINARY 'NHAN_VIEN_MAC_DINH') <> 1
       OR EXISTS (
            SELECT 1
            FROM vai_tro vt
            JOIN vai_tro_quyen vtq ON vtq.ma_vt = vt.ma_vt
            WHERE BINARY vt.ky_hieu = BINARY 'NHAN_VIEN_MAC_DINH'
       ) THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'NV_MIGRATION_ROLE_AMBIGUOUS';
    END IF;
END//

DELIMITER ;

ALTER TABLE vai_tro
    ADD CONSTRAINT uq_vai_tro_ky_hieu UNIQUE (ky_hieu);

ALTER TABLE nhan_vien
    ADD COLUMN anh_dai_dien VARCHAR(255) NULL,
    ADD COLUMN ngay_nghi_viec DATE NULL,
    ADD CONSTRAINT uq_nhan_vien_email UNIQUE (email),
    ADD CONSTRAINT uq_nhan_vien_cccd UNIQUE (cccd),
    ADD CONSTRAINT ck_nhan_vien_ma_nv CHECK (
        BINARY ma_nv REGEXP '^NV(00[1-9]|0[1-9][0-9]|[1-9][0-9]{2})$'
    );

CREATE TABLE dia_chi_nhan_vien (
    ma_nv VARCHAR(5) PRIMARY KEY,
    dia_chi_cu_the NVARCHAR(255) NOT NULL,
    phuong_xa NVARCHAR(100) NOT NULL,
    quan_huyen NVARCHAR(100) NOT NULL,
    tinh_thanh NVARCHAR(100) NOT NULL,
    CONSTRAINT fk_dia_chi_nhan_vien_nhan_vien
        FOREIGN KEY (ma_nv) REFERENCES nhan_vien(ma_nv) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS bo_dem_ma_nhan_vien (
    ten_bo_dem VARCHAR(30) PRIMARY KEY,
    so_da_cap SMALLINT UNSIGNED NOT NULL
);

INSERT INTO bo_dem_ma_nhan_vien (ten_bo_dem, so_da_cap)
SELECT 'NHAN_VIEN', COALESCE(MAX(CAST(SUBSTRING(ma_nv, 3, 3) AS UNSIGNED)), 0)
FROM nhan_vien
ON DUPLICATE KEY UPDATE so_da_cap = GREATEST(so_da_cap, VALUES(so_da_cap));

DROP VIEW IF EXISTS vw_danh_sach_nhan_vien_chi_tiet;

CREATE VIEW vw_danh_sach_nhan_vien_chi_tiet AS
SELECT nv.ma_nv,
    nv.ho_ten,
    nv.ngay_sinh,
    nv.gioi_tinh,
    CASE nv.gioi_tinh WHEN 1 THEN 'Nam' WHEN 0 THEN 'Nữ' ELSE 'Khác' END AS gioi_tinh_hien_thi,
    nv.sdt,
    nv.email,
    nv.ngay_vao_lam,
    nv.ma_pb,
    pb.ten_pb,
    nv.ma_cv,
    cv.ten_cv,
    cv.he_so_phu_cap,
    nv.dan_toc,
    nv.cccd,
    nv.noi_cap_cccd,
    nv.hoc_van,
    nv.ma_tt,
    ttlv.ky_hieu,
    ttlv.ten_tt,
    nv.ngay_nghi_viec,
    nv.ma_vt,
    vt.ky_hieu AS ky_hieu_vai_tro,
    vt.ten_vt,
    nv.anh_dai_dien
FROM nhan_vien nv
LEFT JOIN phong_ban pb ON pb.ma_pb = nv.ma_pb
LEFT JOIN chuc_vu cv ON cv.ma_cv = nv.ma_cv
LEFT JOIN trang_thai_lam_viec ttlv ON ttlv.ma_tt = nv.ma_tt
LEFT JOIN vai_tro vt ON vt.ma_vt = nv.ma_vt;
