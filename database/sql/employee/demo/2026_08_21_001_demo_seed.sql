-- Optional synthetic demo seed for the employee module.
-- Target is deliberately limited to quan_ly_nhan_su or a DisposableMariaDbGuard
-- name; the caller owns the selected database and must use the guarded helper.
-- This script stores only a Laravel-compatible bcrypt hash; it never stores plaintext.
-- Direct SOURCE is intentionally rejected unless the same MariaDB session first
-- creates employee_demo_guard and sets @employee_demo_guard_token.

DELIMITER //

BEGIN NOT ATOMIC
    DECLARE v_guard_count INT DEFAULT 0;

    IF DATABASE() IS NULL OR (
        BINARY DATABASE() <> BINARY 'quan_ly_nhan_su'
        AND BINARY DATABASE() NOT REGEXP BINARY '^quan_ly_nhan_su_employee_test_[a-f0-9]+$'
    ) THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'DEMO_SEED_TARGET_INVALID';
    END IF;

    IF @employee_demo_guard_token IS NULL
       OR CHAR_LENGTH(@employee_demo_guard_token) <> 64
       OR BINARY @employee_demo_guard_token NOT REGEXP BINARY '^[a-f0-9]{64}$' THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'DEMO_SEED_GUARD_TOKEN_INVALID';
    END IF;

    SELECT COUNT(*) INTO v_guard_count
    FROM employee_demo_guard;
    IF v_guard_count <> 1 THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'DEMO_SEED_GUARD_MARKER_INVALID';
    END IF;

    SELECT COUNT(*) INTO v_guard_count
    FROM employee_demo_guard
    WHERE marker_id = 1
      AND BINARY token = BINARY @employee_demo_guard_token
      AND BINARY database_name = BINARY DATABASE();
    IF v_guard_count <> 1 THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'DEMO_SEED_GUARD_MARKER_INVALID';
    END IF;
END//

BEGIN NOT ATOMIC
    DECLARE v_count INT DEFAULT 0;
    DECLARE v_role_id INT;
    DECLARE v_role_symbol VARCHAR(50);
    DECLARE v_role_name VARCHAR(100);
    DECLARE v_status_dang_lam TINYINT;
    DECLARE v_status_thu_viec TINYINT;
    DECLARE v_pb_1 INT;
    DECLARE v_pb_2 INT;
    DECLARE v_pb_3 INT;
    DECLARE v_pb_4 INT;
    DECLARE v_pb_5 INT;
    DECLARE v_cv_1 INT;
    DECLARE v_cv_2 INT;
    DECLARE v_cv_3 INT;
    DECLARE v_cv_4 INT;
    DECLARE v_cv_5 INT;
    DECLARE v_quyen_xem INT;
    DECLARE v_quyen_tao INT;
    DECLARE v_quyen_sua INT;
    DECLARE v_quyen_xoa INT;
    DECLARE v_quyen_reset INT;
    DECLARE v_ma_nv_1 VARCHAR(5);
    DECLARE v_ma_nv_2 VARCHAR(5);
    DECLARE v_ma_nv_3 VARCHAR(5);
    DECLARE v_ma_nv_4 VARCHAR(5);
    DECLARE v_ma_nv_5 VARCHAR(5);

    DECLARE EXIT HANDLER FOR SQLEXCEPTION
    BEGIN
        ROLLBACK;
        RESIGNAL;
    END;

    IF DATABASE() IS NULL OR (
        BINARY DATABASE() <> BINARY 'quan_ly_nhan_su'
        AND BINARY DATABASE() NOT REGEXP BINARY '^quan_ly_nhan_su_employee_test_[a-f0-9]+$'
    ) THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'DEMO_SEED_TARGET_INVALID';
    END IF;

    IF (
        SELECT COUNT(*)
        FROM information_schema.TABLES
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_TYPE = 'BASE TABLE'
          AND TABLE_NAME IN (
              'phong_ban', 'chuc_vu', 'vai_tro', 'quyen', 'vai_tro_quyen',
              'trang_thai_lam_viec', 'nhan_vien', 'dia_chi_nhan_vien',
              'bo_dem_ma_nhan_vien'
          )
    ) <> 9 THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'DEMO_SEED_SCHEMA_CONTRACT_INVALID';
    END IF;

    IF (
        SELECT COUNT(*)
        FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME = 'nhan_vien'
          AND COLUMN_NAME IN (
              'ma_nv', 'ho_ten', 'ngay_sinh', 'gioi_tinh', 'sdt', 'email',
              'ngay_vao_lam', 'ma_pb', 'ma_cv', 'dan_toc', 'cccd',
              'noi_cap_cccd', 'hoc_van', 'ma_tt', 'mat_khau', 'ma_vt',
              'anh_dai_dien', 'ngay_nghi_viec'
          )
    ) <> 18 THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'DEMO_SEED_EMPLOYEE_COLUMNS_INVALID';
    END IF;

    IF (
        SELECT COUNT(*)
        FROM information_schema.ROUTINES
        WHERE ROUTINE_SCHEMA = DATABASE()
          AND ROUTINE_TYPE = 'PROCEDURE'
          AND ROUTINE_NAME IN (
              'sp_phong_ban_danh_sach',
              'sp_chuc_vu_danh_sach',
              'sp_vai_tro_danh_sach',
              'sp_trang_thai_lam_viec_danh_sach',
              'sp_nhan_vien_danh_sach_phan_trang',
              'sp_nhan_vien_chi_tiet',
              'sp_cham_cong_nhan_vien_phan_trang',
              'sp_nhan_vien_them',
              'sp_dia_chi_nhan_vien_luu',
              'sp_nhan_vien_sua',
              'sp_nhan_vien_cap_nhat_anh',
              'sp_nhan_vien_xoa_hoac_nghi_viec',
              'sp_nhan_vien_dat_lai_mat_khau',
              'sp_nhan_vien_cap_nhat_hash_xac_thuc',
              'sp_nhan_vien_lay_tai_khoan_dang_nhap',
              'sp_quyen_them',
              'sp_quyen_danh_sach',
              'sp_quyen_lay_theo_ma_nhan_vien',
              'sp_vai_tro_quyen_them',
              'sp_vai_tro_xoa',
              'sp_nhan_vien_gan_vai_tro_noi_bo'
          )
    ) <> 21 THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'DEMO_SEED_ROUTINE_CONTRACT_INVALID';
    END IF;

    START TRANSACTION;

    SELECT COUNT(*)
    INTO v_count
    FROM trang_thai_lam_viec
    WHERE BINARY ky_hieu = BINARY 'DANG_LAM';
    IF v_count <> 1 THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'DEMO_SEED_STATUS_DANG_LAM_AMBIGUOUS';
    END IF;
    SELECT ma_tt INTO v_status_dang_lam
    FROM trang_thai_lam_viec
    WHERE BINARY ky_hieu = BINARY 'DANG_LAM';

    SELECT COUNT(*)
    INTO v_count
    FROM trang_thai_lam_viec
    WHERE BINARY ky_hieu = BINARY 'THU_VIEC';
    IF v_count <> 1 THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'DEMO_SEED_STATUS_THU_VIEC_AMBIGUOUS';
    END IF;
    SELECT ma_tt INTO v_status_thu_viec
    FROM trang_thai_lam_viec
    WHERE BINARY ky_hieu = BINARY 'THU_VIEC';

    SELECT COUNT(*)
    INTO v_count
    FROM vai_tro
    WHERE BINARY ky_hieu = BINARY 'NHAN_VIEN_MAC_DINH';
    IF v_count <> 1 THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'DEMO_SEED_DEFAULT_ROLE_INVALID';
    END IF;

    SELECT COUNT(*)
    INTO v_count
    FROM vai_tro
    WHERE BINARY ky_hieu = BINARY 'DEMO_QUAN_TRI_NHAN_VIEN';
    IF v_count > 1 THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'DEMO_SEED_ADMIN_ROLE_AMBIGUOUS';
    END IF;
    IF v_count = 0 THEN
        INSERT INTO vai_tro (ten_vt, mo_ta, ky_hieu)
        VALUES (
            'Demo employee administrator 2026',
            'Synthetic local-only administrator role for employee-module demo',
            'DEMO_QUAN_TRI_NHAN_VIEN'
        );
    END IF;
    SELECT COUNT(*)
    INTO v_count
    FROM vai_tro
    WHERE BINARY ky_hieu = BINARY 'DEMO_QUAN_TRI_NHAN_VIEN';
    IF v_count <> 1 THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'DEMO_SEED_ADMIN_ROLE_MISSING';
    END IF;
    SELECT ma_vt, ky_hieu, ten_vt
    INTO v_role_id, v_role_symbol, v_role_name
    FROM vai_tro
    WHERE BINARY ky_hieu = BINARY 'DEMO_QUAN_TRI_NHAN_VIEN';
    IF BINARY v_role_symbol <> BINARY 'DEMO_QUAN_TRI_NHAN_VIEN'
       OR BINARY v_role_name <> BINARY 'Demo employee administrator 2026' THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'DEMO_SEED_ADMIN_ROLE_INVALID';
    END IF;

    SELECT COUNT(*) INTO v_count FROM quyen
    WHERE BINARY ky_hieu_quyen = BINARY 'NHAN_VIEN_XEM';
    IF v_count <> 1 THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'DEMO_SEED_PERMISSION_INVALID'; END IF;
    SELECT ma_quyen INTO v_quyen_xem FROM quyen WHERE BINARY ky_hieu_quyen = BINARY 'NHAN_VIEN_XEM';
    SELECT COUNT(*) INTO v_count FROM quyen
    WHERE BINARY ky_hieu_quyen = BINARY 'NHAN_VIEN_TAO';
    IF v_count <> 1 THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'DEMO_SEED_PERMISSION_INVALID'; END IF;
    SELECT ma_quyen INTO v_quyen_tao FROM quyen WHERE BINARY ky_hieu_quyen = BINARY 'NHAN_VIEN_TAO';
    SELECT COUNT(*) INTO v_count FROM quyen
    WHERE BINARY ky_hieu_quyen = BINARY 'NHAN_VIEN_SUA';
    IF v_count <> 1 THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'DEMO_SEED_PERMISSION_INVALID'; END IF;
    SELECT ma_quyen INTO v_quyen_sua FROM quyen WHERE BINARY ky_hieu_quyen = BINARY 'NHAN_VIEN_SUA';
    SELECT COUNT(*) INTO v_count FROM quyen
    WHERE BINARY ky_hieu_quyen = BINARY 'NHAN_VIEN_XOA';
    IF v_count <> 1 THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'DEMO_SEED_PERMISSION_INVALID'; END IF;
    SELECT ma_quyen INTO v_quyen_xoa FROM quyen WHERE BINARY ky_hieu_quyen = BINARY 'NHAN_VIEN_XOA';
    SELECT COUNT(*) INTO v_count FROM quyen
    WHERE BINARY ky_hieu_quyen = BINARY 'NHAN_VIEN_DAT_LAI_MAT_KHAU';
    IF v_count <> 1 THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'DEMO_SEED_PERMISSION_INVALID'; END IF;
    SELECT ma_quyen INTO v_quyen_reset FROM quyen WHERE BINARY ky_hieu_quyen = BINARY 'NHAN_VIEN_DAT_LAI_MAT_KHAU';

    SELECT COUNT(*) INTO v_count FROM vai_tro_quyen WHERE ma_vt = v_role_id;
    IF v_count > 5 OR EXISTS (
        SELECT 1
        FROM vai_tro_quyen vtq
        JOIN quyen q ON q.ma_quyen = vtq.ma_quyen
        WHERE vtq.ma_vt = v_role_id
          AND BINARY q.ky_hieu_quyen NOT IN (
              BINARY 'NHAN_VIEN_XEM', BINARY 'NHAN_VIEN_TAO', BINARY 'NHAN_VIEN_SUA',
              BINARY 'NHAN_VIEN_XOA', BINARY 'NHAN_VIEN_DAT_LAI_MAT_KHAU'
          )
    ) THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'DEMO_SEED_ADMIN_PERMISSION_AMBIGUOUS';
    END IF;
    IF NOT EXISTS (SELECT 1 FROM vai_tro_quyen WHERE ma_vt = v_role_id AND ma_quyen = v_quyen_xem) THEN
        CALL sp_vai_tro_quyen_them(v_role_id, v_quyen_xem);
    END IF;
    IF NOT EXISTS (SELECT 1 FROM vai_tro_quyen WHERE ma_vt = v_role_id AND ma_quyen = v_quyen_tao) THEN
        CALL sp_vai_tro_quyen_them(v_role_id, v_quyen_tao);
    END IF;
    IF NOT EXISTS (SELECT 1 FROM vai_tro_quyen WHERE ma_vt = v_role_id AND ma_quyen = v_quyen_sua) THEN
        CALL sp_vai_tro_quyen_them(v_role_id, v_quyen_sua);
    END IF;
    IF NOT EXISTS (SELECT 1 FROM vai_tro_quyen WHERE ma_vt = v_role_id AND ma_quyen = v_quyen_xoa) THEN
        CALL sp_vai_tro_quyen_them(v_role_id, v_quyen_xoa);
    END IF;
    IF NOT EXISTS (SELECT 1 FROM vai_tro_quyen WHERE ma_vt = v_role_id AND ma_quyen = v_quyen_reset) THEN
        CALL sp_vai_tro_quyen_them(v_role_id, v_quyen_reset);
    END IF;

    SELECT COUNT(*) INTO v_count FROM phong_ban WHERE BINARY ten_pb = BINARY '[DEMO-2026-08-21] Hanh chinh';
    IF v_count > 1 THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'DEMO_SEED_DEPARTMENT_AMBIGUOUS'; END IF;
    IF v_count = 0 THEN INSERT INTO phong_ban (ten_pb) VALUES ('[DEMO-2026-08-21] Hanh chinh'); END IF;
    SELECT ma_pb INTO v_pb_1 FROM phong_ban WHERE BINARY ten_pb = BINARY '[DEMO-2026-08-21] Hanh chinh';
    SELECT COUNT(*) INTO v_count FROM phong_ban WHERE BINARY ten_pb = BINARY '[DEMO-2026-08-21] Ky thuat';
    IF v_count > 1 THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'DEMO_SEED_DEPARTMENT_AMBIGUOUS'; END IF;
    IF v_count = 0 THEN INSERT INTO phong_ban (ten_pb) VALUES ('[DEMO-2026-08-21] Ky thuat'); END IF;
    SELECT ma_pb INTO v_pb_2 FROM phong_ban WHERE BINARY ten_pb = BINARY '[DEMO-2026-08-21] Ky thuat';
    SELECT COUNT(*) INTO v_count FROM phong_ban WHERE BINARY ten_pb = BINARY '[DEMO-2026-08-21] Tai chinh';
    IF v_count > 1 THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'DEMO_SEED_DEPARTMENT_AMBIGUOUS'; END IF;
    IF v_count = 0 THEN INSERT INTO phong_ban (ten_pb) VALUES ('[DEMO-2026-08-21] Tai chinh'); END IF;
    SELECT ma_pb INTO v_pb_3 FROM phong_ban WHERE BINARY ten_pb = BINARY '[DEMO-2026-08-21] Tai chinh';
    SELECT COUNT(*) INTO v_count FROM phong_ban WHERE BINARY ten_pb = BINARY '[DEMO-2026-08-21] Kinh doanh';
    IF v_count > 1 THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'DEMO_SEED_DEPARTMENT_AMBIGUOUS'; END IF;
    IF v_count = 0 THEN INSERT INTO phong_ban (ten_pb) VALUES ('[DEMO-2026-08-21] Kinh doanh'); END IF;
    SELECT ma_pb INTO v_pb_4 FROM phong_ban WHERE BINARY ten_pb = BINARY '[DEMO-2026-08-21] Kinh doanh';
    SELECT COUNT(*) INTO v_count FROM phong_ban WHERE BINARY ten_pb = BINARY '[DEMO-2026-08-21] Van hanh';
    IF v_count > 1 THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'DEMO_SEED_DEPARTMENT_AMBIGUOUS'; END IF;
    IF v_count = 0 THEN INSERT INTO phong_ban (ten_pb) VALUES ('[DEMO-2026-08-21] Van hanh'); END IF;
    SELECT ma_pb INTO v_pb_5 FROM phong_ban WHERE BINARY ten_pb = BINARY '[DEMO-2026-08-21] Van hanh';

    SELECT COUNT(*) INTO v_count FROM chuc_vu WHERE BINARY ten_cv = BINARY '[DEMO-2026-08-21] Quan tri';
    IF v_count > 1 THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'DEMO_SEED_TITLE_AMBIGUOUS'; END IF;
    IF v_count = 0 THEN INSERT INTO chuc_vu (ten_cv, he_so_phu_cap) VALUES ('[DEMO-2026-08-21] Quan tri', 1.00); END IF;
    SELECT ma_cv INTO v_cv_1 FROM chuc_vu WHERE BINARY ten_cv = BINARY '[DEMO-2026-08-21] Quan tri';
    SELECT COUNT(*) INTO v_count FROM chuc_vu WHERE BINARY ten_cv = BINARY '[DEMO-2026-08-21] Ky su';
    IF v_count > 1 THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'DEMO_SEED_TITLE_AMBIGUOUS'; END IF;
    IF v_count = 0 THEN INSERT INTO chuc_vu (ten_cv, he_so_phu_cap) VALUES ('[DEMO-2026-08-21] Ky su', 0.80); END IF;
    SELECT ma_cv INTO v_cv_2 FROM chuc_vu WHERE BINARY ten_cv = BINARY '[DEMO-2026-08-21] Ky su';
    SELECT COUNT(*) INTO v_count FROM chuc_vu WHERE BINARY ten_cv = BINARY '[DEMO-2026-08-21] Ke toan';
    IF v_count > 1 THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'DEMO_SEED_TITLE_AMBIGUOUS'; END IF;
    IF v_count = 0 THEN INSERT INTO chuc_vu (ten_cv, he_so_phu_cap) VALUES ('[DEMO-2026-08-21] Ke toan', 0.70); END IF;
    SELECT ma_cv INTO v_cv_3 FROM chuc_vu WHERE BINARY ten_cv = BINARY '[DEMO-2026-08-21] Ke toan';
    SELECT COUNT(*) INTO v_count FROM chuc_vu WHERE BINARY ten_cv = BINARY '[DEMO-2026-08-21] Kinh doanh';
    IF v_count > 1 THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'DEMO_SEED_TITLE_AMBIGUOUS'; END IF;
    IF v_count = 0 THEN INSERT INTO chuc_vu (ten_cv, he_so_phu_cap) VALUES ('[DEMO-2026-08-21] Kinh doanh', 0.60); END IF;
    SELECT ma_cv INTO v_cv_4 FROM chuc_vu WHERE BINARY ten_cv = BINARY '[DEMO-2026-08-21] Kinh doanh';
    SELECT COUNT(*) INTO v_count FROM chuc_vu WHERE BINARY ten_cv = BINARY '[DEMO-2026-08-21] Dieu phoi';
    IF v_count > 1 THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'DEMO_SEED_TITLE_AMBIGUOUS'; END IF;
    IF v_count = 0 THEN INSERT INTO chuc_vu (ten_cv, he_so_phu_cap) VALUES ('[DEMO-2026-08-21] Dieu phoi', 0.50); END IF;
    SELECT ma_cv INTO v_cv_5 FROM chuc_vu WHERE BINARY ten_cv = BINARY '[DEMO-2026-08-21] Dieu phoi';

    IF EXISTS (
        SELECT 1 FROM nhan_vien
        WHERE LOWER(TRIM(email)) IN (
            'demo.admin@employee.example.test', 'demo.a@employee.example.test',
            'demo.b@employee.example.test', 'demo.c@employee.example.test',
            'demo.d@employee.example.test'
        )
        GROUP BY LOWER(TRIM(email))
        HAVING COUNT(*) > 1
    ) THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'DEMO_SEED_EMPLOYEE_AMBIGUOUS';
    END IF;
    IF EXISTS (
        SELECT 1 FROM nhan_vien
        WHERE (
            sdt IN ('0900001001', '0900001002', '0900001003', '0900001004', '0900001005')
            OR cccd IN ('900000000001', '900000000002', '900000000003', '900000000004', '900000000005')
        )
        AND LOWER(TRIM(email)) NOT IN (
            'demo.admin@employee.example.test', 'demo.a@employee.example.test',
            'demo.b@employee.example.test', 'demo.c@employee.example.test',
            'demo.d@employee.example.test'
        )
    ) THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'DEMO_SEED_CONTACT_CONFLICT';
    END IF;

    SELECT COUNT(*) INTO v_count FROM nhan_vien WHERE LOWER(TRIM(email)) = 'demo.admin@employee.example.test';
    IF v_count = 0 THEN
        CALL sp_nhan_vien_them(
            'Demo Admin 2026', '1988-01-15', 1, '0900001001', 'demo.admin@employee.example.test',
            '2024-01-15', v_pb_1, v_cv_1, 'Kinh', '900000000001', 'Demo Registry', 'Dai hoc',
            v_status_dang_lam, '$2y$12$3NfqjI8EJfS3fuuXLIHJNepg/fcVWH0GWO8UyaT27ngONFklP5o5u', NULL, v_ma_nv_1
        );
    ELSE
        SELECT ma_nv INTO v_ma_nv_1 FROM nhan_vien WHERE LOWER(TRIM(email)) = 'demo.admin@employee.example.test';
    END IF;
    IF NOT EXISTS (
        SELECT 1 FROM nhan_vien
        WHERE BINARY ma_nv = BINARY v_ma_nv_1 AND BINARY ho_ten = BINARY 'Demo Admin 2026'
          AND ngay_sinh = '1988-01-15' AND gioi_tinh = 1 AND BINARY sdt = BINARY '0900001001'
          AND BINARY email = BINARY 'demo.admin@employee.example.test' AND ngay_vao_lam = '2024-01-15'
          AND ma_pb = v_pb_1 AND ma_cv = v_cv_1 AND BINARY dan_toc = BINARY 'Kinh'
          AND BINARY cccd = BINARY '900000000001' AND BINARY noi_cap_cccd = BINARY 'Demo Registry'
          AND BINARY hoc_van = BINARY 'Dai hoc' AND ma_tt = v_status_dang_lam AND ngay_nghi_viec IS NULL
    ) THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'DEMO_SEED_EMPLOYEE_DATA_CONFLICT'; END IF;
    CALL sp_dia_chi_nhan_vien_luu(v_ma_nv_1, 'Demo street 1', 'Demo ward 1', 'Demo district', 'Demo city');

    SELECT COUNT(*) INTO v_count FROM nhan_vien WHERE LOWER(TRIM(email)) = 'demo.a@employee.example.test';
    IF v_count = 0 THEN
        CALL sp_nhan_vien_them(
            'Demo Employee A', '1990-02-20', 0, '0900001002', 'demo.a@employee.example.test',
            '2024-02-01', v_pb_2, v_cv_2, 'Kinh', '900000000002', 'Demo Registry', 'Cao dang',
            v_status_dang_lam, '$2y$12$3NfqjI8EJfS3fuuXLIHJNepg/fcVWH0GWO8UyaT27ngONFklP5o5u', NULL, v_ma_nv_2
        );
    ELSE
        SELECT ma_nv INTO v_ma_nv_2 FROM nhan_vien WHERE LOWER(TRIM(email)) = 'demo.a@employee.example.test';
    END IF;
    IF NOT EXISTS (
        SELECT 1 FROM nhan_vien
        WHERE BINARY ma_nv = BINARY v_ma_nv_2 AND BINARY ho_ten = BINARY 'Demo Employee A'
          AND ngay_sinh = '1990-02-20' AND gioi_tinh = 0 AND BINARY sdt = BINARY '0900001002'
          AND BINARY email = BINARY 'demo.a@employee.example.test' AND ngay_vao_lam = '2024-02-01'
          AND ma_pb = v_pb_2 AND ma_cv = v_cv_2 AND BINARY dan_toc = BINARY 'Kinh'
          AND BINARY cccd = BINARY '900000000002' AND BINARY noi_cap_cccd = BINARY 'Demo Registry'
          AND BINARY hoc_van = BINARY 'Cao dang' AND ma_tt = v_status_dang_lam AND ngay_nghi_viec IS NULL
    ) THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'DEMO_SEED_EMPLOYEE_DATA_CONFLICT'; END IF;
    CALL sp_dia_chi_nhan_vien_luu(v_ma_nv_2, 'Demo street 2', 'Demo ward 2', 'Demo district', 'Demo city');

    SELECT COUNT(*) INTO v_count FROM nhan_vien WHERE LOWER(TRIM(email)) = 'demo.b@employee.example.test';
    IF v_count = 0 THEN
        CALL sp_nhan_vien_them(
            'Demo Employee B', '1992-03-25', 1, '0900001003', 'demo.b@employee.example.test',
            '2025-03-01', v_pb_3, v_cv_3, 'Kinh', '900000000003', 'Demo Registry', 'Dai hoc',
            v_status_thu_viec, '$2y$12$3NfqjI8EJfS3fuuXLIHJNepg/fcVWH0GWO8UyaT27ngONFklP5o5u', NULL, v_ma_nv_3
        );
    ELSE
        SELECT ma_nv INTO v_ma_nv_3 FROM nhan_vien WHERE LOWER(TRIM(email)) = 'demo.b@employee.example.test';
    END IF;
    IF NOT EXISTS (
        SELECT 1 FROM nhan_vien
        WHERE BINARY ma_nv = BINARY v_ma_nv_3 AND BINARY ho_ten = BINARY 'Demo Employee B'
          AND ngay_sinh = '1992-03-25' AND gioi_tinh = 1 AND BINARY sdt = BINARY '0900001003'
          AND BINARY email = BINARY 'demo.b@employee.example.test' AND ngay_vao_lam = '2025-03-01'
          AND ma_pb = v_pb_3 AND ma_cv = v_cv_3 AND BINARY dan_toc = BINARY 'Kinh'
          AND BINARY cccd = BINARY '900000000003' AND BINARY noi_cap_cccd = BINARY 'Demo Registry'
          AND BINARY hoc_van = BINARY 'Dai hoc' AND ma_tt = v_status_thu_viec AND ngay_nghi_viec IS NULL
    ) THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'DEMO_SEED_EMPLOYEE_DATA_CONFLICT'; END IF;
    CALL sp_dia_chi_nhan_vien_luu(v_ma_nv_3, 'Demo street 3', 'Demo ward 3', 'Demo district', 'Demo city');

    SELECT COUNT(*) INTO v_count FROM nhan_vien WHERE LOWER(TRIM(email)) = 'demo.c@employee.example.test';
    IF v_count = 0 THEN
        CALL sp_nhan_vien_them(
            'Demo Employee C', '1989-04-10', 0, '0900001004', 'demo.c@employee.example.test',
            '2024-04-15', v_pb_4, v_cv_4, 'Kinh', '900000000004', 'Demo Registry', 'Dai hoc',
            v_status_dang_lam, '$2y$12$3NfqjI8EJfS3fuuXLIHJNepg/fcVWH0GWO8UyaT27ngONFklP5o5u', NULL, v_ma_nv_4
        );
    ELSE
        SELECT ma_nv INTO v_ma_nv_4 FROM nhan_vien WHERE LOWER(TRIM(email)) = 'demo.c@employee.example.test';
    END IF;
    IF NOT EXISTS (
        SELECT 1 FROM nhan_vien
        WHERE BINARY ma_nv = BINARY v_ma_nv_4 AND BINARY ho_ten = BINARY 'Demo Employee C'
          AND ngay_sinh = '1989-04-10' AND gioi_tinh = 0 AND BINARY sdt = BINARY '0900001004'
          AND BINARY email = BINARY 'demo.c@employee.example.test' AND ngay_vao_lam = '2024-04-15'
          AND ma_pb = v_pb_4 AND ma_cv = v_cv_4 AND BINARY dan_toc = BINARY 'Kinh'
          AND BINARY cccd = BINARY '900000000004' AND BINARY noi_cap_cccd = BINARY 'Demo Registry'
          AND BINARY hoc_van = BINARY 'Dai hoc' AND ma_tt = v_status_dang_lam AND ngay_nghi_viec IS NULL
    ) THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'DEMO_SEED_EMPLOYEE_DATA_CONFLICT'; END IF;
    CALL sp_dia_chi_nhan_vien_luu(v_ma_nv_4, 'Demo street 4', 'Demo ward 4', 'Demo district', 'Demo city');

    SELECT COUNT(*) INTO v_count FROM nhan_vien WHERE LOWER(TRIM(email)) = 'demo.d@employee.example.test';
    IF v_count = 0 THEN
        CALL sp_nhan_vien_them(
            'Demo Employee D', '1991-05-12', 1, '0900001005', 'demo.d@employee.example.test',
            '2025-05-15', v_pb_5, v_cv_5, 'Kinh', '900000000005', 'Demo Registry', 'Trung cap',
            v_status_thu_viec, '$2y$12$3NfqjI8EJfS3fuuXLIHJNepg/fcVWH0GWO8UyaT27ngONFklP5o5u', NULL, v_ma_nv_5
        );
    ELSE
        SELECT ma_nv INTO v_ma_nv_5 FROM nhan_vien WHERE LOWER(TRIM(email)) = 'demo.d@employee.example.test';
    END IF;
    IF NOT EXISTS (
        SELECT 1 FROM nhan_vien
        WHERE BINARY ma_nv = BINARY v_ma_nv_5 AND BINARY ho_ten = BINARY 'Demo Employee D'
          AND ngay_sinh = '1991-05-12' AND gioi_tinh = 1 AND BINARY sdt = BINARY '0900001005'
          AND BINARY email = BINARY 'demo.d@employee.example.test' AND ngay_vao_lam = '2025-05-15'
          AND ma_pb = v_pb_5 AND ma_cv = v_cv_5 AND BINARY dan_toc = BINARY 'Kinh'
          AND BINARY cccd = BINARY '900000000005' AND BINARY noi_cap_cccd = BINARY 'Demo Registry'
          AND BINARY hoc_van = BINARY 'Trung cap' AND ma_tt = v_status_thu_viec AND ngay_nghi_viec IS NULL
    ) THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'DEMO_SEED_EMPLOYEE_DATA_CONFLICT'; END IF;
    CALL sp_dia_chi_nhan_vien_luu(v_ma_nv_5, 'Demo street 5', 'Demo ward 5', 'Demo district', 'Demo city');

    SELECT COUNT(*) INTO v_count
    FROM nhan_vien nv
    JOIN vai_tro vt ON vt.ma_vt = nv.ma_vt
    WHERE nv.ma_nv = v_ma_nv_1 AND BINARY vt.ky_hieu = BINARY 'NHAN_VIEN_MAC_DINH';
    IF v_count = 1 THEN
        CALL sp_nhan_vien_gan_vai_tro_noi_bo(v_ma_nv_1, v_role_id);
    ELSEIF NOT EXISTS (
        SELECT 1 FROM nhan_vien nv
        JOIN vai_tro vt ON vt.ma_vt = nv.ma_vt
        WHERE nv.ma_nv = v_ma_nv_1 AND BINARY vt.ky_hieu = BINARY 'DEMO_QUAN_TRI_NHAN_VIEN'
    ) THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'DEMO_SEED_ADMIN_ASSIGNMENT_INVALID';
    END IF;

    IF EXISTS (
        SELECT 1
        FROM nhan_vien nv
        LEFT JOIN vai_tro vt ON vt.ma_vt = nv.ma_vt
        WHERE nv.ma_nv IN (v_ma_nv_2, v_ma_nv_3, v_ma_nv_4, v_ma_nv_5)
          AND (vt.ky_hieu IS NULL OR BINARY vt.ky_hieu <> BINARY 'NHAN_VIEN_MAC_DINH')
    ) THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'DEMO_SEED_DEFAULT_ROLE_INVALID';
    END IF;
    IF (
        SELECT COUNT(*) FROM vai_tro_quyen WHERE ma_vt = v_role_id
    ) <> 5 OR EXISTS (
        SELECT 1
        FROM vai_tro_quyen vtq
        JOIN quyen q ON q.ma_quyen = vtq.ma_quyen
        WHERE vtq.ma_vt = v_role_id
          AND BINARY q.ky_hieu_quyen NOT IN (
              BINARY 'NHAN_VIEN_XEM', BINARY 'NHAN_VIEN_TAO', BINARY 'NHAN_VIEN_SUA',
              BINARY 'NHAN_VIEN_XOA', BINARY 'NHAN_VIEN_DAT_LAI_MAT_KHAU'
          )
    ) THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'DEMO_SEED_ADMIN_PERMISSION_INVALID';
    END IF;
    IF EXISTS (
        SELECT 1
        FROM nhan_vien nv
        JOIN vai_tro_quyen vtq ON vtq.ma_vt = nv.ma_vt
        WHERE nv.ma_nv IN (v_ma_nv_2, v_ma_nv_3, v_ma_nv_4, v_ma_nv_5)
    ) THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'DEMO_SEED_NORMAL_PERMISSION_INVALID';
    END IF;

    COMMIT;
END//

DELIMITER ;
