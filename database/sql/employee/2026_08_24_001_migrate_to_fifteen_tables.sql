-- Employee module 15-table migration for an existing approved disposable DB.
--
-- SAFETY: MariaDB DDL implicitly commits. Take a physical backup first, run
-- each numbered section separately, inspect the verification SELECTs, and do
-- not expect a surrounding transaction to roll back this script. This file
-- never selects a database and must not be run against the live source DB.
-- Rollback requires restoring the backup; reconstructing the dropped address
-- table or removed symbol columns from application data is not automatic.

-- 0. READ-ONLY PREFLIGHT (must return the expected legacy objects before DDL)
SELECT TABLE_NAME
FROM information_schema.TABLES
WHERE TABLE_SCHEMA = DATABASE()
  AND TABLE_NAME IN ('nhan_vien', 'dia_chi_nhan_vien', 'vai_tro', 'trang_thai_lam_viec')
ORDER BY TABLE_NAME;

SELECT ma_nv
FROM nhan_vien
WHERE ma_nv NOT REGEXP '^NV[0-9]{3}$'
   OR CAST(SUBSTRING(ma_nv, 3) AS UNSIGNED) NOT BETWEEN 1 AND 999;

SELECT MAX(CAST(SUBSTRING(ma_nv, 3) AS UNSIGNED)) AS max_valid_employee_number
FROM nhan_vien
WHERE ma_nv REGEXP '^NV[0-9]{3}$';

-- STOP before DDL if any invalid employee code is returned, if the expected
-- legacy tables are missing, or if max_valid_employee_number is above 999.

SELECT TABLE_NAME, COLUMN_NAME
FROM information_schema.COLUMNS
WHERE TABLE_SCHEMA = DATABASE()
  AND ((TABLE_NAME = 'vai_tro' AND COLUMN_NAME = 'ky_hieu')
    OR (TABLE_NAME = 'trang_thai_lam_viec' AND COLUMN_NAME = 'ky_hieu'))
ORDER BY TABLE_NAME;

-- STOP if the two legacy symbol columns are absent: the migration has already
-- been partially applied or the target is not the expected 16-table shape.

-- 0b. SEMANTIC PREFLIGHT (fixed IDs are part of the application contract).
-- The temporary guard turns a conflict into a hard error before any persisted
-- DDL/DML. It is connection-local and is dropped at the end of this script.
CREATE TEMPORARY TABLE _employee_migration_preflight (
    check_name VARCHAR(64) NOT NULL PRIMARY KEY,
    check_ok TINYINT NOT NULL CHECK (check_ok = 1)
) ENGINE = InnoDB;

INSERT INTO _employee_migration_preflight (check_name, check_ok)
SELECT 'role_fixed_ids', IF(
    (SELECT COUNT(*) FROM vai_tro WHERE
        (ma_vt = 1 AND BINARY TRIM(ten_vt) = BINARY 'Super Admin') OR
        (ma_vt = 2 AND BINARY TRIM(ten_vt) = BINARY 'Quản trị Nhân sự') OR
        (ma_vt = 3 AND BINARY TRIM(ten_vt) = BINARY 'Quản trị CBL') OR
        (ma_vt = 4 AND BINARY TRIM(ten_vt) = BINARY 'Trưởng phòng') OR
        (ma_vt = 5 AND BINARY TRIM(ten_vt) = BINARY 'Nhân viên')
    ) = 5, 1, 0
);

INSERT INTO _employee_migration_preflight (check_name, check_ok)
SELECT 'status_fixed_ids', IF(
    (SELECT COUNT(*) FROM trang_thai_lam_viec WHERE
        (ma_tt = 1 AND BINARY TRIM(ten_tt) = BINARY 'Thử việc') OR
        (ma_tt = 2 AND BINARY TRIM(ten_tt) = BINARY 'Đang làm việc') OR
        (ma_tt = 3 AND BINARY TRIM(ten_tt) = BINARY 'Tạm nghỉ không lương') OR
        (ma_tt = 4 AND BINARY TRIM(ten_tt) = BINARY 'Đã nghỉ việc')
    ) = 4, 1, 0
);

INSERT INTO _employee_migration_preflight (check_name, check_ok)
SELECT 'employee_role_status_ids', IF(NOT EXISTS (
    SELECT 1 FROM nhan_vien WHERE ma_vt NOT BETWEEN 1 AND 5 OR ma_tt NOT BETWEEN 1 AND 4
), 1, 0);

-- Existing 101..104 rows must be the canonical IDs, and 105 must not collide
-- with a different permission. Missing rows are added below; no existing row
-- or mapping is deleted.
INSERT INTO _employee_migration_preflight (check_name, check_ok)
SELECT 'permission_id_conflicts', IF(NOT EXISTS (
    SELECT 1 FROM quyen q
    WHERE q.ma_quyen IN (101, 102, 103, 104, 105)
      AND NOT (
          (q.ma_quyen = 101 AND q.ky_hieu_quyen = 'NV_VIEW' AND q.module = 'NhanVien') OR
          (q.ma_quyen = 102 AND q.ky_hieu_quyen = 'NV_CREATE' AND q.module = 'NhanVien') OR
          (q.ma_quyen = 103 AND q.ky_hieu_quyen = 'NV_EDIT' AND q.module = 'NhanVien') OR
          (q.ma_quyen = 104 AND q.ky_hieu_quyen = 'NV_DELETE' AND q.module = 'NhanVien') OR
          (q.ma_quyen = 105 AND q.ky_hieu_quyen = 'NV_RESET_PASSWORD' AND q.module = 'NhanVien')
      )
), 1, 0);

INSERT INTO _employee_migration_preflight (check_name, check_ok)
SELECT 'permission_symbol_conflicts', IF(NOT EXISTS (
    SELECT 1 FROM quyen q
    WHERE q.ky_hieu_quyen IN ('NV_VIEW', 'NV_CREATE', 'NV_EDIT', 'NV_DELETE', 'NV_RESET_PASSWORD')
      AND q.ma_quyen NOT IN (101, 102, 103, 104, 105)
), 1, 0);

INSERT INTO _employee_migration_preflight (check_name, check_ok)
SELECT 'department_permission_id_conflicts', IF(NOT EXISTS (
    SELECT 1 FROM quyen q
    WHERE q.ma_quyen IN (201, 202, 203, 204)
      AND NOT (
          (q.ma_quyen = 201 AND q.ky_hieu_quyen = 'PB_VIEW' AND q.module = 'PhongBan') OR
          (q.ma_quyen = 202 AND q.ky_hieu_quyen = 'PB_CREATE' AND q.module = 'PhongBan') OR
          (q.ma_quyen = 203 AND q.ky_hieu_quyen = 'PB_EDIT' AND q.module = 'PhongBan') OR
          (q.ma_quyen = 204 AND q.ky_hieu_quyen = 'PB_DELETE' AND q.module = 'PhongBan')
      )
), 1, 0);

INSERT INTO _employee_migration_preflight (check_name, check_ok)
SELECT 'department_permission_symbol_conflicts', IF(NOT EXISTS (
    SELECT 1 FROM quyen q
    WHERE q.ky_hieu_quyen IN ('PB_VIEW', 'PB_CREATE', 'PB_EDIT', 'PB_DELETE')
      AND q.ma_quyen NOT IN (201, 202, 203, 204)
), 1, 0);

INSERT INTO _employee_migration_preflight (check_name, check_ok)
SELECT 'position_permission_id_conflicts', IF(NOT EXISTS (
    SELECT 1 FROM quyen q
    WHERE q.ma_quyen IN (301, 302, 303, 304)
      AND NOT (
          (q.ma_quyen = 301 AND q.ky_hieu_quyen = 'CV_VIEW' AND q.module = 'ChucVu') OR
          (q.ma_quyen = 302 AND q.ky_hieu_quyen = 'CV_CREATE' AND q.module = 'ChucVu') OR
          (q.ma_quyen = 303 AND q.ky_hieu_quyen = 'CV_EDIT' AND q.module = 'ChucVu') OR
          (q.ma_quyen = 304 AND q.ky_hieu_quyen = 'CV_DELETE' AND q.module = 'ChucVu')
      )
), 1, 0);

INSERT INTO _employee_migration_preflight (check_name, check_ok)
SELECT 'position_permission_symbol_conflicts', IF(NOT EXISTS (
    SELECT 1 FROM quyen q
    WHERE q.ky_hieu_quyen IN ('CV_VIEW', 'CV_CREATE', 'CV_EDIT', 'CV_DELETE')
      AND q.ma_quyen NOT IN (301, 302, 303, 304)
), 1, 0);

SELECT * FROM _employee_migration_preflight ORDER BY check_name;

-- 1. Add the target columns before copying address data.
ALTER TABLE nhan_vien
    ADD COLUMN IF NOT EXISTS dia_chi_cu_the VARCHAR(255) NULL,
    ADD COLUMN IF NOT EXISTS phuong_xa VARCHAR(100) NULL,
    ADD COLUMN IF NOT EXISTS quan_huyen VARCHAR(100) NULL,
    ADD COLUMN IF NOT EXISTS tinh_thanh VARCHAR(100) NULL,
    ADD COLUMN IF NOT EXISTS anh_dai_dien VARCHAR(255) NULL,
    ADD COLUMN IF NOT EXISTS ngay_nghi_viec DATE NULL;

-- 2. Copy one-to-one addresses. Verify the counts before proceeding.
UPDATE nhan_vien nv
JOIN dia_chi_nhan_vien dc ON dc.ma_nv = nv.ma_nv
SET nv.dia_chi_cu_the = dc.dia_chi_cu_the,
    nv.phuong_xa = dc.phuong_xa,
    nv.quan_huyen = dc.quan_huyen,
    nv.tinh_thanh = dc.tinh_thanh;

SELECT
    (SELECT COUNT(*) FROM dia_chi_nhan_vien) AS legacy_address_rows,
    (SELECT COUNT(*) FROM nhan_vien nv JOIN dia_chi_nhan_vien dc ON dc.ma_nv = nv.ma_nv) AS copied_address_rows,
    (SELECT COUNT(*) FROM dia_chi_nhan_vien dc LEFT JOIN nhan_vien nv ON nv.ma_nv = dc.ma_nv WHERE nv.ma_nv IS NULL) AS orphan_address_rows;

-- STOP if copied_address_rows differs from legacy_address_rows or orphan rows
-- are non-zero. Correct the data and rerun section 2 after backup review.

-- 3. Provision/repair the counter from the existing maximum. Do not decrease
-- it: generated codes are never reused.
CREATE TABLE IF NOT EXISTS bo_dem_ma_nhan_vien (
    ten_bo_dem VARCHAR(32) NOT NULL PRIMARY KEY,
    so_da_cap SMALLINT UNSIGNED NOT NULL
) ENGINE = InnoDB;

INSERT INTO bo_dem_ma_nhan_vien (ten_bo_dem, so_da_cap)
SELECT 'NHAN_VIEN', COALESCE(MAX(CAST(SUBSTRING(ma_nv, 3) AS UNSIGNED)), 0)
FROM nhan_vien
WHERE ma_nv REGEXP '^NV[0-9]{3}$'
ON DUPLICATE KEY UPDATE so_da_cap = GREATEST(so_da_cap, VALUES(so_da_cap));

SELECT * FROM bo_dem_ma_nhan_vien WHERE ten_bo_dem = 'NHAN_VIEN';

-- 4. Add missing canonical employee permissions and the minimum mappings used
-- by admin/HR employee authorization. INSERT ... SELECT preserves existing
-- module permissions and role mappings and is safe to rerun after review.
INSERT INTO quyen (ma_quyen, ky_hieu_quyen, ten_quyen, module)
SELECT 101, 'NV_VIEW', 'Xem danh sách nhân viên', 'NhanVien'
WHERE NOT EXISTS (SELECT 1 FROM quyen WHERE ma_quyen = 101);
INSERT INTO quyen (ma_quyen, ky_hieu_quyen, ten_quyen, module)
SELECT 102, 'NV_CREATE', 'Thêm mới nhân viên', 'NhanVien'
WHERE NOT EXISTS (SELECT 1 FROM quyen WHERE ma_quyen = 102);
INSERT INTO quyen (ma_quyen, ky_hieu_quyen, ten_quyen, module)
SELECT 103, 'NV_EDIT', 'Cập nhật nhân viên', 'NhanVien'
WHERE NOT EXISTS (SELECT 1 FROM quyen WHERE ma_quyen = 103);
INSERT INTO quyen (ma_quyen, ky_hieu_quyen, ten_quyen, module)
SELECT 104, 'NV_DELETE', 'Xóa nhân viên', 'NhanVien'
WHERE NOT EXISTS (SELECT 1 FROM quyen WHERE ma_quyen = 104);
INSERT INTO quyen (ma_quyen, ky_hieu_quyen, ten_quyen, module)
SELECT 105, 'NV_RESET_PASSWORD', 'Đặt lại mật khẩu nhân viên', 'NhanVien'
WHERE NOT EXISTS (SELECT 1 FROM quyen WHERE ma_quyen = 105);

INSERT INTO quyen (ma_quyen, ky_hieu_quyen, ten_quyen, module)
SELECT 201, 'PB_VIEW', 'Xem danh sách phòng ban', 'PhongBan'
WHERE NOT EXISTS (SELECT 1 FROM quyen WHERE ma_quyen = 201);
INSERT INTO quyen (ma_quyen, ky_hieu_quyen, ten_quyen, module)
SELECT 202, 'PB_CREATE', 'Thêm phòng ban', 'PhongBan'
WHERE NOT EXISTS (SELECT 1 FROM quyen WHERE ma_quyen = 202);
INSERT INTO quyen (ma_quyen, ky_hieu_quyen, ten_quyen, module)
SELECT 203, 'PB_EDIT', 'Cập nhật phòng ban', 'PhongBan'
WHERE NOT EXISTS (SELECT 1 FROM quyen WHERE ma_quyen = 203);
INSERT INTO quyen (ma_quyen, ky_hieu_quyen, ten_quyen, module)
SELECT 204, 'PB_DELETE', 'Xóa phòng ban', 'PhongBan'
WHERE NOT EXISTS (SELECT 1 FROM quyen WHERE ma_quyen = 204);
INSERT INTO quyen (ma_quyen, ky_hieu_quyen, ten_quyen, module)
SELECT 301, 'CV_VIEW', 'Xem danh sách chức vụ', 'ChucVu'
WHERE NOT EXISTS (SELECT 1 FROM quyen WHERE ma_quyen = 301);
INSERT INTO quyen (ma_quyen, ky_hieu_quyen, ten_quyen, module)
SELECT 302, 'CV_CREATE', 'Thêm chức vụ', 'ChucVu'
WHERE NOT EXISTS (SELECT 1 FROM quyen WHERE ma_quyen = 302);
INSERT INTO quyen (ma_quyen, ky_hieu_quyen, ten_quyen, module)
SELECT 303, 'CV_EDIT', 'Cập nhật chức vụ', 'ChucVu'
WHERE NOT EXISTS (SELECT 1 FROM quyen WHERE ma_quyen = 303);
INSERT INTO quyen (ma_quyen, ky_hieu_quyen, ten_quyen, module)
SELECT 304, 'CV_DELETE', 'Xóa chức vụ', 'ChucVu'
WHERE NOT EXISTS (SELECT 1 FROM quyen WHERE ma_quyen = 304);

INSERT INTO vai_tro_quyen (ma_vt, ma_quyen)
SELECT roles.ma_vt, permissions.ma_quyen
FROM (SELECT 1 AS ma_vt UNION ALL SELECT 2) roles
CROSS JOIN (SELECT 101 AS ma_quyen UNION ALL SELECT 102 UNION ALL SELECT 103 UNION ALL SELECT 104 UNION ALL SELECT 105) permissions
WHERE NOT EXISTS (
    SELECT 1 FROM vai_tro_quyen existing
    WHERE existing.ma_vt = roles.ma_vt AND existing.ma_quyen = permissions.ma_quyen
);

INSERT INTO vai_tro_quyen (ma_vt, ma_quyen)
SELECT 2, permissions.ma_quyen
FROM (
    SELECT 201 AS ma_quyen UNION ALL SELECT 202 UNION ALL SELECT 203 UNION ALL SELECT 204
    UNION ALL SELECT 301 UNION ALL SELECT 302 UNION ALL SELECT 303 UNION ALL SELECT 304
) permissions
WHERE NOT EXISTS (
    SELECT 1 FROM vai_tro_quyen existing
    WHERE existing.ma_vt = 2 AND existing.ma_quyen = permissions.ma_quyen
);

INSERT INTO _employee_migration_preflight (check_name, check_ok)
SELECT 'role_two_department_position_mappings', IF(
    (SELECT COUNT(*) FROM vai_tro_quyen
     WHERE ma_vt = 2 AND ma_quyen IN (201, 202, 203, 204, 301, 302, 303, 304)) = 8,
    1, 0
);

INSERT INTO _employee_migration_preflight (check_name, check_ok)
SELECT 'role_two_permission_count', IF(
    (SELECT COUNT(*) FROM vai_tro_quyen
     WHERE ma_vt = 2
       AND ma_quyen IN (101, 102, 103, 104, 105, 201, 202, 203, 204,
                        301, 302, 303, 304, 401, 402, 403, 404)) = 17,
    1, 0
);

SELECT vt.ma_vt, COUNT(vtq.ma_quyen) AS employee_permission_count
FROM vai_tro vt
LEFT JOIN vai_tro_quyen vtq ON vtq.ma_vt = vt.ma_vt AND vtq.ma_quyen BETWEEN 101 AND 105
WHERE vt.ma_vt IN (1, 2)
GROUP BY vt.ma_vt
ORDER BY vt.ma_vt;

-- 5. Drop the legacy address table only after section 2 verification.
ALTER TABLE dia_chi_nhan_vien DROP FOREIGN KEY fk_dia_chi_nhan_vien_nhan_vien;
DROP TABLE dia_chi_nhan_vien;

-- 6. Legacy symbol columns are no longer part of the target contract. Confirm
-- the application version is already ID-based before executing these DDLs.
ALTER TABLE vai_tro DROP COLUMN IF EXISTS ky_hieu;
ALTER TABLE trang_thai_lam_viec DROP COLUMN IF EXISTS ky_hieu;

-- 7. FINAL READ-ONLY POSTCHECK. The target must contain exactly 15 tables;
-- legacy views/routines/triggers are removed only by the explicit allowlisted
-- cleanup script 2026_08_24_002_cleanup_legacy_employee_objects.sql.
SELECT COUNT(*) AS table_count
FROM information_schema.TABLES
WHERE TABLE_SCHEMA = DATABASE()
  AND TABLE_TYPE = 'BASE TABLE';

SELECT TABLE_NAME, COLUMN_NAME
FROM information_schema.COLUMNS
WHERE TABLE_SCHEMA = DATABASE()
  AND TABLE_NAME IN ('nhan_vien', 'vai_tro', 'trang_thai_lam_viec')
  AND COLUMN_NAME IN ('dia_chi_cu_the', 'phuong_xa', 'quan_huyen', 'tinh_thanh', 'anh_dai_dien', 'ngay_nghi_viec', 'ky_hieu')
ORDER BY TABLE_NAME, COLUMN_NAME;

SELECT 'permission_postcheck' AS check_name,
       (SELECT COUNT(*) FROM quyen WHERE ma_quyen IN (101, 102, 103, 104, 105)) AS employee_permission_count,
       (SELECT COUNT(*) FROM vai_tro_quyen WHERE ma_vt IN (1, 2) AND ma_quyen BETWEEN 101 AND 105) AS admin_hr_mapping_count;

SELECT 'role_two_postcheck' AS check_name,
       (SELECT COUNT(*) FROM vai_tro_quyen
        WHERE ma_vt = 2
          AND ma_quyen IN (101, 102, 103, 104, 105, 201, 202, 203, 204,
                           301, 302, 303, 304, 401, 402, 403, 404)) AS role_two_permission_count,
       (SELECT COUNT(*) FROM vai_tro_quyen
        WHERE ma_vt = 2 AND ma_quyen IN (201, 202, 203, 204, 301, 302, 303, 304)) AS role_two_department_position_count;

DROP TEMPORARY TABLE _employee_migration_preflight;
