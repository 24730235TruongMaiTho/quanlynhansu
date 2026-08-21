-- Remove only the synthetic 2026-08-21 employee demo data.
-- This is reversible by rerunning 2026_08_21_001_demo_seed.sql.
-- It intentionally never decrements or reuses bo_dem_ma_nhan_vien.
-- Direct SOURCE is intentionally rejected unless the same MariaDB session first
-- creates employee_demo_guard and sets @employee_demo_guard_token.

DELIMITER //

BEGIN NOT ATOMIC
    DECLARE v_count INT DEFAULT 0;
    DECLARE v_role_id INT;
    DECLARE v_role_name VARCHAR(100);
    DECLARE v_pb_1 INT DEFAULT NULL;
    DECLARE v_pb_2 INT DEFAULT NULL;
    DECLARE v_pb_3 INT DEFAULT NULL;
    DECLARE v_pb_4 INT DEFAULT NULL;
    DECLARE v_pb_5 INT DEFAULT NULL;
    DECLARE v_cv_1 INT DEFAULT NULL;
    DECLARE v_cv_2 INT DEFAULT NULL;
    DECLARE v_cv_3 INT DEFAULT NULL;
    DECLARE v_cv_4 INT DEFAULT NULL;
    DECLARE v_cv_5 INT DEFAULT NULL;
    DECLARE v_guard_count INT DEFAULT 0;

    DECLARE EXIT HANDLER FOR SQLEXCEPTION
    BEGIN
        ROLLBACK;
        RESIGNAL;
    END;

    IF DATABASE() IS NULL OR (
        BINARY DATABASE() <> BINARY 'quan_ly_nhan_su'
        AND BINARY DATABASE() NOT REGEXP BINARY '^quan_ly_nhan_su_employee_test_[a-f0-9]+$'
    ) THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'DEMO_CLEANUP_TARGET_INVALID';
    END IF;

    IF @employee_demo_guard_token IS NULL
       OR CHAR_LENGTH(@employee_demo_guard_token) <> 64
       OR BINARY @employee_demo_guard_token NOT REGEXP BINARY '^[a-f0-9]{64}$' THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'DEMO_CLEANUP_GUARD_TOKEN_INVALID';
    END IF;

    SELECT COUNT(*) INTO v_guard_count
    FROM employee_demo_guard;
    IF v_guard_count <> 1 THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'DEMO_CLEANUP_GUARD_MARKER_INVALID';
    END IF;

    SELECT COUNT(*) INTO v_guard_count
    FROM employee_demo_guard
    WHERE marker_id = 1
      AND BINARY token = BINARY @employee_demo_guard_token
      AND BINARY database_name = BINARY DATABASE();
    IF v_guard_count <> 1 THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'DEMO_CLEANUP_GUARD_MARKER_INVALID';
    END IF;

    IF DATABASE() IS NULL OR (
        BINARY DATABASE() <> BINARY 'quan_ly_nhan_su'
        AND BINARY DATABASE() NOT REGEXP BINARY '^quan_ly_nhan_su_employee_test_[a-f0-9]+$'
    ) THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'DEMO_CLEANUP_TARGET_INVALID';
    END IF;
    IF (
        SELECT COUNT(*)
        FROM information_schema.TABLES
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_TYPE = 'BASE TABLE'
          AND TABLE_NAME IN (
              'phong_ban', 'chuc_vu', 'vai_tro', 'vai_tro_quyen', 'nhan_vien',
              'dia_chi_nhan_vien', 'bo_dem_ma_nhan_vien'
          )
    ) <> 7 THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'DEMO_CLEANUP_SCHEMA_CONTRACT_INVALID';
    END IF;
    IF (
        SELECT COUNT(*)
        FROM information_schema.ROUTINES
        WHERE ROUTINE_SCHEMA = DATABASE()
          AND ROUTINE_TYPE = 'PROCEDURE'
          AND ROUTINE_NAME = 'sp_vai_tro_xoa'
    ) <> 1 THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'DEMO_CLEANUP_ROUTINE_CONTRACT_INVALID';
    END IF;

    START TRANSACTION;

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
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'DEMO_CLEANUP_EMPLOYEE_AMBIGUOUS';
    END IF;
    IF EXISTS (
        SELECT 1 FROM nhan_vien
        WHERE (LOWER(TRIM(email)) = 'demo.admin@employee.example.test'
               AND (BINARY ho_ten <> BINARY 'Demo Admin 2026' OR BINARY sdt <> BINARY '0900001001'
                    OR BINARY cccd <> BINARY '900000000001'))
           OR (LOWER(TRIM(email)) = 'demo.a@employee.example.test'
               AND (BINARY ho_ten <> BINARY 'Demo Employee A' OR BINARY sdt <> BINARY '0900001002'
                    OR BINARY cccd <> BINARY '900000000002'))
           OR (LOWER(TRIM(email)) = 'demo.b@employee.example.test'
               AND (BINARY ho_ten <> BINARY 'Demo Employee B' OR BINARY sdt <> BINARY '0900001003'
                    OR BINARY cccd <> BINARY '900000000003'))
           OR (LOWER(TRIM(email)) = 'demo.c@employee.example.test'
               AND (BINARY ho_ten <> BINARY 'Demo Employee C' OR BINARY sdt <> BINARY '0900001004'
                    OR BINARY cccd <> BINARY '900000000004'))
           OR (LOWER(TRIM(email)) = 'demo.d@employee.example.test'
               AND (BINARY ho_ten <> BINARY 'Demo Employee D' OR BINARY sdt <> BINARY '0900001005'
                    OR BINARY cccd <> BINARY '900000000005'))
    ) THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'DEMO_CLEANUP_TARGET_MISMATCH';
    END IF;

    SELECT COUNT(*) INTO v_count FROM vai_tro
    WHERE BINARY ky_hieu = BINARY 'DEMO_QUAN_TRI_NHAN_VIEN';
    IF v_count > 1 THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'DEMO_CLEANUP_ROLE_AMBIGUOUS';
    END IF;
    IF v_count = 1 THEN
        SELECT ma_vt, ten_vt INTO v_role_id, v_role_name
        FROM vai_tro WHERE BINARY ky_hieu = BINARY 'DEMO_QUAN_TRI_NHAN_VIEN';
        IF BINARY v_role_name <> BINARY 'Demo employee administrator 2026' THEN
            SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'DEMO_CLEANUP_ROLE_MISMATCH';
        END IF;
    END IF;

    SELECT COUNT(*) INTO v_count FROM phong_ban WHERE BINARY ten_pb = BINARY '[DEMO-2026-08-21] Hanh chinh';
    IF v_count > 1 THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'DEMO_CLEANUP_DEPARTMENT_AMBIGUOUS'; END IF;
    IF v_count = 1 THEN SELECT ma_pb INTO v_pb_1 FROM phong_ban WHERE BINARY ten_pb = BINARY '[DEMO-2026-08-21] Hanh chinh'; END IF;
    SELECT COUNT(*) INTO v_count FROM phong_ban WHERE BINARY ten_pb = BINARY '[DEMO-2026-08-21] Ky thuat';
    IF v_count > 1 THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'DEMO_CLEANUP_DEPARTMENT_AMBIGUOUS'; END IF;
    IF v_count = 1 THEN SELECT ma_pb INTO v_pb_2 FROM phong_ban WHERE BINARY ten_pb = BINARY '[DEMO-2026-08-21] Ky thuat'; END IF;
    SELECT COUNT(*) INTO v_count FROM phong_ban WHERE BINARY ten_pb = BINARY '[DEMO-2026-08-21] Tai chinh';
    IF v_count > 1 THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'DEMO_CLEANUP_DEPARTMENT_AMBIGUOUS'; END IF;
    IF v_count = 1 THEN SELECT ma_pb INTO v_pb_3 FROM phong_ban WHERE BINARY ten_pb = BINARY '[DEMO-2026-08-21] Tai chinh'; END IF;
    SELECT COUNT(*) INTO v_count FROM phong_ban WHERE BINARY ten_pb = BINARY '[DEMO-2026-08-21] Kinh doanh';
    IF v_count > 1 THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'DEMO_CLEANUP_DEPARTMENT_AMBIGUOUS'; END IF;
    IF v_count = 1 THEN SELECT ma_pb INTO v_pb_4 FROM phong_ban WHERE BINARY ten_pb = BINARY '[DEMO-2026-08-21] Kinh doanh'; END IF;
    SELECT COUNT(*) INTO v_count FROM phong_ban WHERE BINARY ten_pb = BINARY '[DEMO-2026-08-21] Van hanh';
    IF v_count > 1 THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'DEMO_CLEANUP_DEPARTMENT_AMBIGUOUS'; END IF;
    IF v_count = 1 THEN SELECT ma_pb INTO v_pb_5 FROM phong_ban WHERE BINARY ten_pb = BINARY '[DEMO-2026-08-21] Van hanh'; END IF;

    SELECT COUNT(*) INTO v_count FROM chuc_vu WHERE BINARY ten_cv = BINARY '[DEMO-2026-08-21] Quan tri';
    IF v_count > 1 THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'DEMO_CLEANUP_TITLE_AMBIGUOUS'; END IF;
    IF v_count = 1 THEN SELECT ma_cv INTO v_cv_1 FROM chuc_vu WHERE BINARY ten_cv = BINARY '[DEMO-2026-08-21] Quan tri'; END IF;
    SELECT COUNT(*) INTO v_count FROM chuc_vu WHERE BINARY ten_cv = BINARY '[DEMO-2026-08-21] Ky su';
    IF v_count > 1 THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'DEMO_CLEANUP_TITLE_AMBIGUOUS'; END IF;
    IF v_count = 1 THEN SELECT ma_cv INTO v_cv_2 FROM chuc_vu WHERE BINARY ten_cv = BINARY '[DEMO-2026-08-21] Ky su'; END IF;
    SELECT COUNT(*) INTO v_count FROM chuc_vu WHERE BINARY ten_cv = BINARY '[DEMO-2026-08-21] Ke toan';
    IF v_count > 1 THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'DEMO_CLEANUP_TITLE_AMBIGUOUS'; END IF;
    IF v_count = 1 THEN SELECT ma_cv INTO v_cv_3 FROM chuc_vu WHERE BINARY ten_cv = BINARY '[DEMO-2026-08-21] Ke toan'; END IF;
    SELECT COUNT(*) INTO v_count FROM chuc_vu WHERE BINARY ten_cv = BINARY '[DEMO-2026-08-21] Kinh doanh';
    IF v_count > 1 THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'DEMO_CLEANUP_TITLE_AMBIGUOUS'; END IF;
    IF v_count = 1 THEN SELECT ma_cv INTO v_cv_4 FROM chuc_vu WHERE BINARY ten_cv = BINARY '[DEMO-2026-08-21] Kinh doanh'; END IF;
    SELECT COUNT(*) INTO v_count FROM chuc_vu WHERE BINARY ten_cv = BINARY '[DEMO-2026-08-21] Dieu phoi';
    IF v_count > 1 THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'DEMO_CLEANUP_TITLE_AMBIGUOUS'; END IF;
    IF v_count = 1 THEN SELECT ma_cv INTO v_cv_5 FROM chuc_vu WHERE BINARY ten_cv = BINARY '[DEMO-2026-08-21] Dieu phoi'; END IF;

    IF EXISTS (
        SELECT 1 FROM nhan_vien
        WHERE (
            ma_pb IN (v_pb_1, v_pb_2, v_pb_3, v_pb_4, v_pb_5)
            OR ma_cv IN (v_cv_1, v_cv_2, v_cv_3, v_cv_4, v_cv_5)
        )
          AND LOWER(TRIM(email)) NOT IN (
              'demo.admin@employee.example.test', 'demo.a@employee.example.test',
              'demo.b@employee.example.test', 'demo.c@employee.example.test',
              'demo.d@employee.example.test'
          )
    ) THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'DEMO_CLEANUP_MASTER_IN_USE';
    END IF;

    DELETE FROM nhan_vien
    WHERE LOWER(TRIM(email)) IN (
        'demo.admin@employee.example.test', 'demo.a@employee.example.test',
        'demo.b@employee.example.test', 'demo.c@employee.example.test',
        'demo.d@employee.example.test'
    );

    IF v_role_id IS NOT NULL THEN
        CALL sp_vai_tro_xoa(v_role_id);
    END IF;
    DELETE FROM phong_ban WHERE ma_pb IN (v_pb_1, v_pb_2, v_pb_3, v_pb_4, v_pb_5);
    DELETE FROM chuc_vu WHERE ma_cv IN (v_cv_1, v_cv_2, v_cv_3, v_cv_4, v_cv_5);

    COMMIT;
END//

DELIMITER ;
