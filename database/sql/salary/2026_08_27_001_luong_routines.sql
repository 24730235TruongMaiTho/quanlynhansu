-- ============================================================================
-- MODULE: LUONG
-- Guarded SQL migration
--
-- Creates/replaces:
--   FUNCTION  fn_thong_bao_tinh_luong
--   PROCEDURE sp_luong_tim_kiem_phan_trang
--
-- SAFETY:
-- - Explicitly select the target database.
-- - Run only after backup.
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
