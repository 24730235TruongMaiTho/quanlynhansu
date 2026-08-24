-- Explicit allowlist cleanup for objects from the superseded employee schema.
--
-- This is a separate DDL step because MariaDB DDL implicitly commits. Run it
-- only after 2026_08_24_001 has passed its postchecks on an approved backup or
-- disposable database. It intentionally does not drop procedures for other
-- modules. The preflight and postcheck are part of the operator evidence.

-- LEGACY OBJECT PREFLIGHT: review the returned names before continuing.
SELECT ROUTINE_TYPE, ROUTINE_NAME
FROM information_schema.ROUTINES
WHERE ROUTINE_SCHEMA = DATABASE()
  AND ROUTINE_NAME IN (
      'sp_nhan_vien_tim_kiem', 'sp_nhan_vien_danh_sach',
      'sp_nhan_vien_danh_sach_phan_trang', 'sp_nhan_vien_chi_tiet',
      'fn_dem_nhan_vien_theo_phong_ban', 'fn_dem_nhan_vien_theo_chuc_vu',
      'sp_nhan_vien_them', 'sp_dia_chi_nhan_vien_luu', 'sp_nhan_vien_sua',
      'sp_nhan_vien_cap_nhat_anh', 'sp_nhan_vien_xoa',
      'sp_nhan_vien_xoa_hoac_nghi_viec', 'sp_nhan_vien_dat_lai_mat_khau',
      'sp_nhan_vien_cap_nhat_hash_xac_thuc', 'sp_nhan_vien_lay_tai_khoan_dang_nhap',
      'sp_nhan_vien_dang_nhap', 'sp_nhan_vien_gan_vai_tro_noi_bo'
  )
ORDER BY ROUTINE_TYPE, ROUTINE_NAME;

SELECT TABLE_NAME AS legacy_employee_view
FROM information_schema.VIEWS
WHERE TABLE_SCHEMA = DATABASE()
  AND TABLE_NAME IN ('vw_danh_sach_nhan_vien_chi_tiet');

DROP VIEW IF EXISTS vw_danh_sach_nhan_vien_chi_tiet;

DROP FUNCTION IF EXISTS fn_dem_nhan_vien_theo_phong_ban;
DROP FUNCTION IF EXISTS fn_dem_nhan_vien_theo_chuc_vu;

DROP PROCEDURE IF EXISTS sp_nhan_vien_tim_kiem;
DROP PROCEDURE IF EXISTS sp_nhan_vien_danh_sach;
DROP PROCEDURE IF EXISTS sp_nhan_vien_danh_sach_phan_trang;
DROP PROCEDURE IF EXISTS sp_nhan_vien_chi_tiet;
DROP PROCEDURE IF EXISTS sp_nhan_vien_them;
DROP PROCEDURE IF EXISTS sp_dia_chi_nhan_vien_luu;
DROP PROCEDURE IF EXISTS sp_nhan_vien_sua;
DROP PROCEDURE IF EXISTS sp_nhan_vien_cap_nhat_anh;
DROP PROCEDURE IF EXISTS sp_nhan_vien_xoa;
DROP PROCEDURE IF EXISTS sp_nhan_vien_xoa_hoac_nghi_viec;
DROP PROCEDURE IF EXISTS sp_nhan_vien_dat_lai_mat_khau;
DROP PROCEDURE IF EXISTS sp_nhan_vien_cap_nhat_hash_xac_thuc;
DROP PROCEDURE IF EXISTS sp_nhan_vien_lay_tai_khoan_dang_nhap;
DROP PROCEDURE IF EXISTS sp_nhan_vien_dang_nhap;
DROP PROCEDURE IF EXISTS sp_nhan_vien_gan_vai_tro_noi_bo;

-- No employee view/routine in the allowlist may remain. A non-employee
-- routine, if present, is deliberately outside this script's scope.
SELECT ROUTINE_TYPE, ROUTINE_NAME
FROM information_schema.ROUTINES
WHERE ROUTINE_SCHEMA = DATABASE()
  AND ROUTINE_NAME IN (
      'sp_nhan_vien_tim_kiem', 'sp_nhan_vien_danh_sach',
      'sp_nhan_vien_danh_sach_phan_trang', 'sp_nhan_vien_chi_tiet',
      'fn_dem_nhan_vien_theo_phong_ban', 'fn_dem_nhan_vien_theo_chuc_vu',
      'sp_nhan_vien_them', 'sp_dia_chi_nhan_vien_luu', 'sp_nhan_vien_sua',
      'sp_nhan_vien_cap_nhat_anh', 'sp_nhan_vien_xoa',
      'sp_nhan_vien_xoa_hoac_nghi_viec', 'sp_nhan_vien_dat_lai_mat_khau',
      'sp_nhan_vien_cap_nhat_hash_xac_thuc', 'sp_nhan_vien_lay_tai_khoan_dang_nhap',
      'sp_nhan_vien_dang_nhap', 'sp_nhan_vien_gan_vai_tro_noi_bo'
  );

SELECT TABLE_NAME AS remaining_employee_view
FROM information_schema.VIEWS
WHERE TABLE_SCHEMA = DATABASE()
  AND TABLE_NAME IN ('vw_danh_sach_nhan_vien_chi_tiet');
