-- ============================================================================
-- MODULE: LUONG
-- Guarded SQL migration
--
-- Creates/replaces:
--   FUNCTION  fn_thong_bao_tinh_luong
--   PROCEDURE sp_luong_tim_kiem_phan_trang
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
SELECT
    TABLE_NAME,
    TABLE_TYPE
FROM information_schema.TABLES
WHERE TABLE_SCHEMA = DATABASE()
  AND TABLE_NAME IN (
                     'nhan_vien',
                     'luong',
                     'hop_dong',
                     'lich_su_he_so_luong',
                     'cham_cong',
                     'chuc_vu',
                     'vw_danh_sach_nhan_vien_chi_tiet'
    )
ORDER BY TABLE_NAME;


SELECT
    ROUTINE_NAME,
    ROUTINE_TYPE
FROM information_schema.ROUTINES
WHERE ROUTINE_SCHEMA = DATABASE()
  AND ROUTINE_TYPE = 'FUNCTION'
  AND ROUTINE_NAME IN (
                       'fn_so_ngay_cong_chuan',
                       'fn_so_ngay_cong_thuc_te',
                       'fn_tinh_luong_thuc_nhan'
    )
ORDER BY ROUTINE_NAME;


-- ============================================================================
-- 0b. SEMANTIC PREFLIGHT
-- ============================================================================
DROP TEMPORARY TABLE IF EXISTS _luong_migration_preflight;

CREATE TEMPORARY TABLE _luong_migration_preflight (
    check_name VARCHAR(96) NOT NULL PRIMARY KEY,
    check_ok TINYINT NOT NULL CHECK (check_ok = 1)
) ENGINE = InnoDB;


INSERT INTO _luong_migration_preflight (
    check_name,
    check_ok
)
SELECT
    'required_tables_and_view',
    IF(
        (
            SELECT COUNT(*)
            FROM information_schema.TABLES
            WHERE TABLE_SCHEMA = DATABASE()
              AND TABLE_NAME IN (
                                 'nhan_vien',
                                 'luong',
                                 'hop_dong',
                                 'lich_su_he_so_luong',
                                 'cham_cong',
                                 'chuc_vu',
                                 'vw_danh_sach_nhan_vien_chi_tiet'
                )
        ) = 7,
        1,
        0
    );


INSERT INTO _luong_migration_preflight (
    check_name,
    check_ok
)
SELECT
    'dependency_functions',
    IF(
        (
            SELECT COUNT(*)
            FROM information_schema.ROUTINES
            WHERE ROUTINE_SCHEMA = DATABASE()
              AND ROUTINE_TYPE = 'FUNCTION'
              AND ROUTINE_NAME IN (
                                   'fn_so_ngay_cong_chuan',
                                   'fn_so_ngay_cong_thuc_te',
                                   'fn_tinh_luong_thuc_nhan'
                )
        ) = 3,
        1,
        0
    );


SELECT *
FROM _luong_migration_preflight
ORDER BY check_name;


-- ============================================================================
-- 1. MIGRATION
-- ============================================================================
DELIMITER $$


-- ----------------------------------------------------------------------------
-- 1.1 FUNCTION: fn_thong_bao_tinh_luong
-- ----------------------------------------------------------------------------
DROP FUNCTION IF EXISTS fn_thong_bao_tinh_luong$$

CREATE FUNCTION fn_thong_bao_tinh_luong(
    p_ma_nv VARCHAR(5),
    p_ky_luong DATE
)
    RETURNS VARCHAR(255)
    NOT DETERMINISTIC
    READS SQL DATA
BEGIN
    DECLARE v_ky DATE;
    DECLARE v_so_ngay_cong_chuan INT DEFAULT 0;
    DECLARE v_so_ngay_cong_thuc_te DECIMAL(10,2) DEFAULT 0;

    IF p_ma_nv IS NULL OR TRIM(p_ma_nv) = '' THEN
        RETURN 'Thiếu mã nhân viên';
END IF;

    IF p_ky_luong IS NULL THEN
        RETURN 'Thiếu kỳ lương';
END IF;

    SET v_ky = STR_TO_DATE(
        DATE_FORMAT(
            p_ky_luong,
            '%Y-%m-01'
        ),
        '%Y-%m-%d'
    );

    IF NOT EXISTS (
        SELECT 1
        FROM nhan_vien
        WHERE ma_nv = p_ma_nv
    ) THEN
        RETURN 'Nhân viên không tồn tại';
END IF;

    IF NOT EXISTS (
        SELECT 1
        FROM luong
        WHERE ma_nv = p_ma_nv
          AND ky_luong = v_ky
    ) THEN
        RETURN CONCAT(
            'Chưa tạo thông tin lương kỳ ',
            DATE_FORMAT(v_ky, '%m/%Y')
        );
END IF;

    IF NOT EXISTS (
        SELECT 1
        FROM hop_dong
        WHERE ma_nv = p_ma_nv
          AND v_ky BETWEEN ngay_ky
                       AND IFNULL(ngay_het_han, v_ky)
          AND IFNULL(luong_co_ban, 0) > 0
    ) THEN
        RETURN 'Chưa có hợp đồng hoặc lương cơ bản hiệu lực';
END IF;

    IF NOT EXISTS (
        SELECT 1
        FROM lich_su_he_so_luong
        WHERE ma_nv = p_ma_nv
          AND v_ky BETWEEN tu_ngay
                       AND IFNULL(den_ngay, v_ky)
          AND IFNULL(he_so_luong, 0) > 0
    ) THEN
        RETURN 'Chưa có hệ số lương hiệu lực';
END IF;

    IF NOT EXISTS (
        SELECT 1
        FROM cham_cong
        WHERE ma_nv = p_ma_nv
          AND ngay_lam >= v_ky
          AND ngay_lam < DATE_ADD(
              v_ky,
              INTERVAL 1 MONTH
          )
    ) THEN
        RETURN 'Chưa có dữ liệu chấm công trong kỳ';
END IF;

    SET v_so_ngay_cong_chuan =
        fn_so_ngay_cong_chuan(
            p_ma_nv,
            v_ky
        );

    IF IFNULL(v_so_ngay_cong_chuan, 0) = 0 THEN
        RETURN 'Chưa có ngày công hợp lệ trong kỳ';
END IF;

    SET v_so_ngay_cong_thuc_te =
        fn_so_ngay_cong_thuc_te(
            p_ma_nv,
            v_ky
        );

    IF IFNULL(v_so_ngay_cong_thuc_te, 0) = 0 THEN
        RETURN 'Số giờ làm chưa đủ để quy đổi ngày công';
END IF;

RETURN 'Hoàn tất tính lương';
END$$


-- ----------------------------------------------------------------------------
-- 1.2 PROCEDURE: sp_luong_tim_kiem_phan_trang
-- ----------------------------------------------------------------------------
DROP PROCEDURE IF EXISTS sp_luong_tim_kiem_phan_trang$$

CREATE PROCEDURE sp_luong_tim_kiem_phan_trang(
    IN p_tu_khoa VARCHAR(255),
    IN p_ky_luong DATE,
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
    DECLARE v_ky_luong DATE;

    SET v_page = IFNULL(p_page, 1);
    SET v_per_page = IFNULL(p_per_page, 15);
    SET v_tu_khoa = NULLIF(TRIM(p_tu_khoa), '');
    SET v_ky_luong = p_ky_luong;

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

    IF v_ky_luong IS NOT NULL THEN
        SET v_ky_luong = STR_TO_DATE(
            DATE_FORMAT(
                v_ky_luong,
                '%Y-%m-01'
            ),
            '%Y-%m-%d'
        );
END IF;

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
             nv.ngay_vao_lam,
             nv.ma_pb,
             nv.ten_pb,
             nv.ma_cv,
             nv.ten_cv,
             nv.hoc_van,

             l.ma_luong,
             l.ky_luong,
             l.thuong,
             l.phat,
             l.bao_hiem,
             l.thue,

             cv.he_so_phu_cap AS phu_cap,

             CASE
                 WHEN l.ma_luong IS NULL THEN 0
                 ELSE fn_tinh_luong_thuc_nhan(
                     nv.ma_nv,
                     l.ky_luong
                      )
                 END AS thuc_nhan,

             fn_thong_bao_tinh_luong(
                 nv.ma_nv,
                 COALESCE(
                     l.ky_luong,
                     v_ky_luong
                 )
             ) AS thong_bao_tinh_luong,

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

                  INNER JOIN chuc_vu cv
                             ON cv.ma_cv = nv.ma_cv

                  LEFT JOIN luong l
                            ON l.ma_nv = nv.ma_nv
                                AND (
                                   v_ky_luong IS NULL
                                       OR l.ky_luong = v_ky_luong
                                   )

                  LEFT JOIN (
             SELECT
                 ma_nv,

                 STR_TO_DATE(
                     DATE_FORMAT(
                         ngay_lam,
                         '%Y-%m-01'
                     ),
                     '%Y-%m-%d'
                 ) AS ky_luong,

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

             GROUP BY
                 ma_nv,
                 STR_TO_DATE(
                     DATE_FORMAT(
                         ngay_lam,
                         '%Y-%m-01'
                     ),
                     '%Y-%m-%d'
                 )
         ) cc
                            ON cc.ma_nv = nv.ma_nv
                                AND cc.ky_luong = l.ky_luong

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
                 OR nv.ma_nv LIKE CONCAT('%', v_tu_khoa, '%')
                 OR nv.ho_ten LIKE CONCAT('%', v_tu_khoa, '%')
                 OR nv.ten_pb LIKE CONCAT('%', v_tu_khoa, '%')
                 OR nv.ten_cv LIKE CONCAT('%', v_tu_khoa, '%')
             )
     ) rs

ORDER BY
    rs.ky_luong DESC,
    rs.ma_nv ASC

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
  AND ROUTINE_NAME IN (
                       'fn_thong_bao_tinh_luong',
                       'sp_luong_tim_kiem_phan_trang'
    )
ORDER BY ROUTINE_TYPE, ROUTINE_NAME;


INSERT INTO _luong_migration_preflight (
    check_name,
    check_ok
)
SELECT
    'target_routines',
    IF(
        (
            SELECT COUNT(*)
            FROM information_schema.ROUTINES
            WHERE ROUTINE_SCHEMA = DATABASE()
              AND ROUTINE_NAME IN (
                                   'fn_thong_bao_tinh_luong',
                                   'sp_luong_tim_kiem_phan_trang'
                )
        ) = 2,
        1,
        0
    );


SELECT
    fn_thong_bao_tinh_luong(
        NULL,
        CURDATE()
    ) AS notification_function_smoke_test;


SELECT *
FROM _luong_migration_preflight
ORDER BY check_name;


DROP TEMPORARY TABLE IF EXISTS _luong_migration_preflight;
