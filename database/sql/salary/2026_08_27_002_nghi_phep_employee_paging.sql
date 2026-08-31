-- ============================================================================
-- MODULE: NGHI PHEP
-- Guarded SQL migration
--
-- Creates/replaces:
--   PROCEDURE sp_nhan_vien_danh_sach_phan_trang
--   PROCEDURE sp_nghi_phep_danh_sach_phan_trang
--
-- SAFETY:
-- - Explicitly selects database `quan_ly_nhan_su`.
-- - Run only against the intended database after backup.
-- - Routine DDL implicitly commits in MariaDB/MySQL.
-- ============================================================================

USE `quan_ly_nhan_su`;


-- ============================================================================
-- 0. READ-ONLY PREFLIGHT
-- ============================================================================

-- Required view / tables
SELECT
    TABLE_NAME,
    TABLE_TYPE
FROM information_schema.TABLES
WHERE TABLE_SCHEMA = DATABASE()
  AND TABLE_NAME IN (
                     'vw_danh_sach_nhan_vien_chi_tiet',
                     'nghi_phep',
                     'nhan_vien',
                     'loai_phep',
                     'chuc_vu'
    )
ORDER BY TABLE_NAME;


-- Required columns of employee detail view
SELECT
    COLUMN_NAME,
    DATA_TYPE
FROM information_schema.COLUMNS
WHERE TABLE_SCHEMA = DATABASE()
  AND TABLE_NAME = 'vw_danh_sach_nhan_vien_chi_tiet'
  AND COLUMN_NAME IN (
                      'ma_nv',
                      'ho_ten',
                      'ma_pb',
                      'ten_pb',
                      'ma_cv',
                      'ten_cv'
    )
ORDER BY ORDINAL_POSITION;


-- ============================================================================
-- 0b. SEMANTIC PREFLIGHT
-- ============================================================================

DROP TEMPORARY TABLE IF EXISTS _nghi_phep_migration_preflight;

CREATE TEMPORARY TABLE _nghi_phep_migration_preflight (
    check_name VARCHAR(96) NOT NULL PRIMARY KEY,
    check_ok TINYINT NOT NULL CHECK (check_ok = 1)
) ENGINE = InnoDB;


-- Employee-detail view must exist
INSERT INTO _nghi_phep_migration_preflight (
    check_name,
    check_ok
)
SELECT
    'employee_detail_view',
    IF(
        (
            SELECT COUNT(*)
            FROM information_schema.TABLES
            WHERE TABLE_SCHEMA = DATABASE()
              AND TABLE_TYPE = 'VIEW'
              AND TABLE_NAME = 'vw_danh_sach_nhan_vien_chi_tiet'
        ) = 1,
        1,
        0
    );


-- Required columns of the employee-detail view must exist
INSERT INTO _nghi_phep_migration_preflight (
    check_name,
    check_ok
)
SELECT
    'required_view_columns',
    IF(
        (
            SELECT COUNT(*)
            FROM information_schema.COLUMNS
            WHERE TABLE_SCHEMA = DATABASE()
              AND TABLE_NAME = 'vw_danh_sach_nhan_vien_chi_tiet'
              AND COLUMN_NAME IN (
                                  'ma_nv',
                                  'ho_ten',
                                  'ma_pb',
                                  'ten_pb',
                                  'ma_cv',
                                  'ten_cv'
                )
        ) = 6,
        1,
        0
    );


-- Required tables for leave approval paging must exist
INSERT INTO _nghi_phep_migration_preflight (
    check_name,
    check_ok
)
SELECT
    'leave_approval_dependencies',
    IF(
        (
            SELECT COUNT(*)
            FROM information_schema.TABLES
            WHERE TABLE_SCHEMA = DATABASE()
              AND TABLE_TYPE = 'BASE TABLE'
              AND TABLE_NAME IN (
                                 'nghi_phep',
                                 'nhan_vien',
                                 'loai_phep',
                                 'chuc_vu'
                )
        ) = 4,
        1,
        0
    );


SELECT *
FROM _nghi_phep_migration_preflight
ORDER BY check_name;


-- ============================================================================
-- 1. MIGRATION
-- ============================================================================

DELIMITER $$


-- ----------------------------------------------------------------------------
-- 1.1 PROCEDURE: sp_nhan_vien_danh_sach_phan_trang
-- ----------------------------------------------------------------------------

DROP PROCEDURE IF EXISTS sp_nhan_vien_danh_sach_phan_trang$$

CREATE PROCEDURE sp_nhan_vien_danh_sach_phan_trang(
    IN p_tu_khoa VARCHAR(255),
    IN p_ma_pb INT,
    IN p_ma_cv INT,
    IN p_page INT,
    IN p_per_page INT
)
BEGIN
    DECLARE v_page INT DEFAULT 1;
    DECLARE v_per_page INT DEFAULT 15;
    DECLARE v_offset INT DEFAULT 0;
    DECLARE v_tu_khoa VARCHAR(255);

    SET v_page = IFNULL(p_page, 1);
    SET v_per_page = IFNULL(p_per_page, 15);
    SET v_tu_khoa = NULLIF(TRIM(p_tu_khoa), '');

    IF v_page < 1 THEN
        SET v_page = 1;
END IF;

    IF v_per_page < 1 THEN
        SET v_per_page = 15;
END IF;

    IF v_per_page > 100 THEN
        SET v_per_page = 100;
END IF;

    SET v_offset =
        (v_page - 1) * v_per_page;

SELECT
    nv.*,
    COUNT(*) OVER() AS total_count

FROM vw_danh_sach_nhan_vien_chi_tiet nv

WHERE
    (
        p_ma_pb IS NULL
            OR nv.ma_pb = p_ma_pb
        )
  AND (
    p_ma_cv IS NULL
        OR nv.ma_cv = p_ma_cv
    )
  AND (
    v_tu_khoa IS NULL

        OR nv.ma_nv LIKE CONCAT(
        '%',
        v_tu_khoa,
        '%'
                         )

        OR nv.ho_ten LIKE CONCAT(
        '%',
        v_tu_khoa,
        '%'
                          )

        OR nv.ten_pb LIKE CONCAT(
        '%',
        v_tu_khoa,
        '%'
                          )

        OR nv.ten_cv LIKE CONCAT(
        '%',
        v_tu_khoa,
        '%'
                          )
    )

ORDER BY
    nv.ma_nv ASC

    LIMIT v_per_page
OFFSET v_offset;
END$$


-- ----------------------------------------------------------------------------
-- 1.2 PROCEDURE: sp_nghi_phep_danh_sach_phan_trang
-- ----------------------------------------------------------------------------

DROP PROCEDURE IF EXISTS sp_nghi_phep_danh_sach_phan_trang$$

CREATE PROCEDURE sp_nghi_phep_danh_sach_phan_trang(
    IN p_tu_khoa VARCHAR(100),
    IN p_ma_pb INT,
    IN p_ma_lp INT,
    IN p_tu_ngay DATE,
    IN p_den_ngay DATE,
    IN p_tab VARCHAR(20),
    IN p_page INT,
    IN p_per_page INT
)
BEGIN
    DECLARE v_page INT DEFAULT 1;
    DECLARE v_per_page INT DEFAULT 10;
    DECLARE v_offset INT DEFAULT 0;
    DECLARE v_total INT DEFAULT 0;

    DECLARE v_tu_khoa VARCHAR(100);
    DECLARE v_tab VARCHAR(20);

    SET v_page = IFNULL(p_page, 1);
    SET v_per_page = IFNULL(p_per_page, 10);

    SET v_tu_khoa =
        NULLIF(
            TRIM(p_tu_khoa),
            ''
        );

    SET v_tab =
        NULLIF(
            TRIM(p_tab),
            ''
        );

    IF v_page < 1 THEN
        SET v_page = 1;
END IF;

    IF v_per_page < 1 THEN
        SET v_per_page = 10;
END IF;

    IF v_per_page > 100 THEN
        SET v_per_page = 100;
END IF;

    IF v_tab IS NULL THEN
        SET v_tab = 'pending';
END IF;

    SET v_offset =
        (v_page - 1) * v_per_page;


    -- ========================================================================
    -- COUNT
    -- ========================================================================

SELECT
    COUNT(*)
INTO
    v_total
FROM
    nghi_phep np

        INNER JOIN nhan_vien nv
                   ON nv.ma_nv = np.ma_nv

WHERE
    (
        p_ma_pb IS NULL
            OR nv.ma_pb = p_ma_pb
        )

  AND (
    p_ma_lp IS NULL
        OR np.ma_lp = p_ma_lp
    )

  AND (
    v_tu_khoa IS NULL

        OR nv.ma_nv LIKE CONCAT(
        '%',
        v_tu_khoa,
        '%'
                         )

        OR nv.ho_ten LIKE CONCAT(
        '%',
        v_tu_khoa,
        '%'
                          )
    )

  -- Interval overlap:
  -- leave.den_ngay >= filter.from
  -- leave.tu_ngay <= filter.to
  AND (
    p_tu_ngay IS NULL
        OR np.den_ngay >= p_tu_ngay
    )

  AND (
    p_den_ngay IS NULL
        OR np.tu_ngay <= p_den_ngay
    )

  AND (
    v_tab = 'all'

        OR (
        v_tab = 'pending'
            AND np.trang_thai_duyet = 0
        )

        OR (
        v_tab = 'processed'
            AND np.trang_thai_duyet IN (1, 2)
        )
    );


-- ========================================================================
-- DATA
-- ========================================================================

SELECT
    np.ma_np,
    np.ma_nv,

    nv.ho_ten,

    nv.ma_pb,

    nv.ma_cv,
    cv.ten_cv,

    np.tu_ngay,
    np.den_ngay,

    DATEDIFF(
        np.den_ngay,
        np.tu_ngay
    ) + 1 AS so_ngay,

    np.ma_lp,
    lp.ten_lp,

    np.ly_do,

    np.trang_thai_duyet,

    CASE np.trang_thai_duyet
        WHEN 0 THEN 'Chờ duyệt'
        WHEN 1 THEN 'Đã duyệt'
        WHEN 2 THEN 'Từ chối'
        ELSE 'Không xác định'
        END AS ten_trang_thai,

    v_total AS total_count

FROM
    nghi_phep np

        INNER JOIN nhan_vien nv
                   ON nv.ma_nv = np.ma_nv

        LEFT JOIN loai_phep lp
                  ON lp.ma_lp = np.ma_lp

        LEFT JOIN chuc_vu cv
                  ON cv.ma_cv = nv.ma_cv

WHERE
    (
        p_ma_pb IS NULL
            OR nv.ma_pb = p_ma_pb
        )

  AND (
    p_ma_lp IS NULL
        OR np.ma_lp = p_ma_lp
    )

  AND (
    v_tu_khoa IS NULL

        OR nv.ma_nv LIKE CONCAT(
        '%',
        v_tu_khoa,
        '%'
                         )

        OR nv.ho_ten LIKE CONCAT(
        '%',
        v_tu_khoa,
        '%'
                          )
    )

  AND (
    p_tu_ngay IS NULL
        OR np.den_ngay >= p_tu_ngay
    )

  AND (
    p_den_ngay IS NULL
        OR np.tu_ngay <= p_den_ngay
    )

  AND (
    v_tab = 'all'

        OR (
        v_tab = 'pending'
            AND np.trang_thai_duyet = 0
        )

        OR (
        v_tab = 'processed'
            AND np.trang_thai_duyet IN (1, 2)
        )
    )

ORDER BY
    np.ma_np DESC

    LIMIT v_per_page
OFFSET v_offset;
END$$


DELIMITER ;


-- ============================================================================
-- 2. FINAL POSTCHECK
-- ============================================================================

SELECT
    ROUTINE_NAME,
    ROUTINE_TYPE,
    LAST_ALTERED
FROM information_schema.ROUTINES
WHERE ROUTINE_SCHEMA = DATABASE()
  AND ROUTINE_TYPE = 'PROCEDURE'
  AND ROUTINE_NAME IN (
                       'sp_nhan_vien_danh_sach_phan_trang',
                       'sp_nghi_phep_danh_sach_phan_trang'
    )
ORDER BY ROUTINE_NAME;


INSERT INTO _nghi_phep_migration_preflight (
    check_name,
    check_ok
)
SELECT
    'target_procedures',
    IF(
        (
            SELECT COUNT(*)
            FROM information_schema.ROUTINES
            WHERE ROUTINE_SCHEMA = DATABASE()
              AND ROUTINE_TYPE = 'PROCEDURE'
              AND ROUTINE_NAME IN (
                                   'sp_nhan_vien_danh_sach_phan_trang',
                                   'sp_nghi_phep_danh_sach_phan_trang'
                )
        ) = 2,
        1,
        0
    );


SELECT *
FROM _nghi_phep_migration_preflight
ORDER BY check_name;


DROP TEMPORARY TABLE IF EXISTS _nghi_phep_migration_preflight;
