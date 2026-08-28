-- ============================================================================
-- MODULE: CHAM CONG
-- Guarded SQL migration
--
-- Creates/replaces:
--   PROCEDURE sp_cham_cong_nhan_vien_phan_trang
--   PROCEDURE sp_cham_cong_chi_tiet_phan_trang
-- ============================================================================


-- ============================================================================
-- 0. READ-ONLY PREFLIGHT
-- ============================================================================
SELECT
    TABLE_NAME,
    TABLE_TYPE
FROM information_schema.TABLES
WHERE TABLE_SCHEMA = DATABASE()
  AND TABLE_NAME IN (
      'cham_cong',
      'vw_danh_sach_nhan_vien_chi_tiet'
  )
ORDER BY TABLE_NAME;


SELECT
    TABLE_NAME,
    COLUMN_NAME,
    DATA_TYPE
FROM information_schema.COLUMNS
WHERE TABLE_SCHEMA = DATABASE()
  AND (
       (
           TABLE_NAME = 'cham_cong'
           AND COLUMN_NAME IN (
               'ma_cc',
               'ma_nv',
               'ngay_lam',
               'so_gio_lam',
               'vao_muon',
               've_som'
           )
       )
       OR
       (
           TABLE_NAME = 'vw_danh_sach_nhan_vien_chi_tiet'
           AND COLUMN_NAME IN (
               'ma_nv',
               'ho_ten',
               'ngay_sinh',
               'gioi_tinh',
               'sdt',
               'email',
               'ma_pb',
               'ten_pb',
               'ma_cv',
               'ten_cv'
           )
       )
  )
ORDER BY TABLE_NAME, ORDINAL_POSITION;


-- ============================================================================
-- 0b. SEMANTIC PREFLIGHT
-- ============================================================================
CREATE TEMPORARY TABLE _cham_cong_migration_preflight (
    check_name VARCHAR(96) NOT NULL PRIMARY KEY,
    check_ok TINYINT NOT NULL CHECK (check_ok = 1)
) ENGINE = InnoDB;


INSERT INTO _cham_cong_migration_preflight (
    check_name,
    check_ok
)
SELECT
    'required_table_and_view',
    IF(
        (
            SELECT COUNT(*)
            FROM information_schema.TABLES
            WHERE TABLE_SCHEMA = DATABASE()
              AND TABLE_NAME IN (
                  'cham_cong',
                  'vw_danh_sach_nhan_vien_chi_tiet'
              )
        ) = 2,
        1,
        0
    );


INSERT INTO _cham_cong_migration_preflight (
    check_name,
    check_ok
)
SELECT
    'cham_cong_columns',
    IF(
        (
            SELECT COUNT(*)
            FROM information_schema.COLUMNS
            WHERE TABLE_SCHEMA = DATABASE()
              AND TABLE_NAME = 'cham_cong'
              AND COLUMN_NAME IN (
                  'ma_cc',
                  'ma_nv',
                  'ngay_lam',
                  'so_gio_lam',
                  'vao_muon',
                  've_som'
              )
        ) = 6,
        1,
        0
    );


SELECT *
FROM _cham_cong_migration_preflight
ORDER BY check_name;


-- ============================================================================
-- 1. MIGRATION
-- ============================================================================
DELIMITER $$


-- ----------------------------------------------------------------------------
-- 1.1 PROCEDURE: sp_cham_cong_nhan_vien_phan_trang
-- ----------------------------------------------------------------------------
DROP PROCEDURE IF EXISTS sp_cham_cong_nhan_vien_phan_trang$$

CREATE PROCEDURE sp_cham_cong_nhan_vien_phan_trang(
    IN p_tu_khoa VARCHAR(255),
    IN p_ma_pb INT,
    IN p_thang INT,
    IN p_nam INT,
    IN p_page INT,
    IN p_per_page INT
)
BEGIN
    DECLARE v_page INT DEFAULT 1;
    DECLARE v_per_page INT DEFAULT 15;
    DECLARE v_offset INT DEFAULT 0;
    DECLARE v_tu_ngay DATE;
    DECLARE v_den_ngay DATE;

    SET v_page =
        IFNULL(
            p_page,
            1
        );

    SET v_per_page =
        IFNULL(
            p_per_page,
            15
        );

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

    SET p_tu_khoa =
        NULLIF(
            TRIM(p_tu_khoa),
            ''
        );

    SET p_thang =
        IFNULL(
            p_thang,
            MONTH(CURDATE())
        );

    SET p_nam =
        IFNULL(
            p_nam,
            YEAR(CURDATE())
        );

    SET v_tu_ngay =
        STR_TO_DATE(
            CONCAT(
                p_nam,
                '-',
                LPAD(
                    p_thang,
                    2,
                    '0'
                ),
                '-01'
            ),
            '%Y-%m-%d'
        );

    SET v_den_ngay =
        DATE_ADD(
            v_tu_ngay,
            INTERVAL 1 MONTH
        );

    SELECT
        rs.*,
        COUNT(*) OVER() AS total_count

    FROM (
        SELECT
            nv.ma_nv,
            nv.ho_ten,
            nv.ngay_sinh,
            nv.gioi_tinh,
            nv.sdt,
            nv.email,
            nv.ma_pb,
            nv.ten_pb,
            nv.ma_cv,
            nv.ten_cv,

            IFNULL(
                cc.so_ngay_cham_cong,
                0
            ) AS so_ngay_cham_cong,

            IFNULL(
                cc.so_lan_vao_muon,
                0
            ) AS so_lan_vao_muon,

            IFNULL(
                cc.so_lan_ve_som,
                0
            ) AS so_lan_ve_som

        FROM vw_danh_sach_nhan_vien_chi_tiet nv

        LEFT JOIN (
            SELECT
                ma_nv,

                SUM(
                    CASE
                        WHEN so_gio_lam >= 8 THEN 1
                        WHEN so_gio_lam >= 4 THEN 0.5
                        ELSE 0
                    END
                ) AS so_ngay_cham_cong,

                SUM(
                    CASE
                        WHEN vao_muon = 1 THEN 1
                        ELSE 0
                    END
                ) AS so_lan_vao_muon,

                SUM(
                    CASE
                        WHEN ve_som = 1 THEN 1
                        ELSE 0
                    END
                ) AS so_lan_ve_som

            FROM cham_cong

            WHERE
                ngay_lam >= v_tu_ngay
                AND ngay_lam < v_den_ngay

            GROUP BY
                ma_nv
        ) cc
            ON cc.ma_nv = nv.ma_nv

        WHERE
            (
                p_ma_pb IS NULL
                OR nv.ma_pb = p_ma_pb
            )

            AND (
                p_tu_khoa IS NULL

                OR nv.ma_nv LIKE CONCAT(
                    '%',
                    p_tu_khoa,
                    '%'
                )

                OR nv.ho_ten LIKE CONCAT(
                    '%',
                    p_tu_khoa,
                    '%'
                )

                OR nv.ten_pb LIKE CONCAT(
                    '%',
                    p_tu_khoa,
                    '%'
                )

                OR nv.ten_cv LIKE CONCAT(
                    '%',
                    p_tu_khoa,
                    '%'
                )
            )
    ) rs

    ORDER BY
        rs.ma_nv ASC

    LIMIT v_per_page
    OFFSET v_offset;
END$$


-- ----------------------------------------------------------------------------
-- 1.2 PROCEDURE: sp_cham_cong_chi_tiet_phan_trang
-- ----------------------------------------------------------------------------
DROP PROCEDURE IF EXISTS sp_cham_cong_chi_tiet_phan_trang$$

CREATE PROCEDURE sp_cham_cong_chi_tiet_phan_trang(
    IN p_ma_nv VARCHAR(5),
    IN p_nam INT,
    IN p_thang INT,
    IN p_page INT,
    IN p_per_page INT
)
BEGIN
    DECLARE v_page INT DEFAULT 1;
    DECLARE v_per_page INT DEFAULT 15;
    DECLARE v_offset INT DEFAULT 0;

    SET v_page =
        IFNULL(
            p_page,
            1
        );

    SET v_per_page =
        IFNULL(
            p_per_page,
            15
        );

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
        cc.ma_cc,
        cc.ma_nv,
        cc.ngay_lam,
        cc.so_gio_lam,
        cc.vao_muon,
        cc.ve_som,

        CASE
            WHEN cc.so_gio_lam >= 8 THEN 1
            WHEN cc.so_gio_lam >= 4 THEN 0.5
            ELSE 0
        END AS ngay_cong,

        COUNT(*) OVER() AS total_count

    FROM cham_cong cc

    WHERE
        cc.ma_nv = p_ma_nv
        AND YEAR(
            cc.ngay_lam
        ) = p_nam
        AND MONTH(
            cc.ngay_lam
        ) = p_thang

    ORDER BY
        cc.ngay_lam ASC

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
      'sp_cham_cong_nhan_vien_phan_trang',
      'sp_cham_cong_chi_tiet_phan_trang'
  )
ORDER BY ROUTINE_NAME;


INSERT INTO _cham_cong_migration_preflight (
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
                  'sp_cham_cong_nhan_vien_phan_trang',
                  'sp_cham_cong_chi_tiet_phan_trang'
              )
        ) = 2,
        1,
        0
    );


SELECT *
FROM _cham_cong_migration_preflight
ORDER BY check_name;


DROP TEMPORARY TABLE _cham_cong_migration_preflight;
