-- Additive RBAC upgrade for an existing database.
-- Run only after verifying the target database and taking an approved backup.
-- This script never removes or rewrites existing permissions.
-- Grants are conditional on roles 1 and 4 existing; missing roles are not
-- synthesized. Any failed canonical insert/grant stops the transaction.

USE quan_ly_nhan_su;

DELIMITER //

DROP PROCEDURE IF EXISTS sp_upgrade_nghiphep_approve_permission//

CREATE PROCEDURE sp_upgrade_nghiphep_approve_permission()
BEGIN
    DECLARE v_id_exists INT DEFAULT 0;
    DECLARE v_symbol_exists INT DEFAULT 0;
    DECLARE EXIT HANDLER FOR SQLEXCEPTION
    BEGIN
        ROLLBACK;
        RESIGNAL;
    END;

    START TRANSACTION;

    SELECT COUNT(*) INTO v_id_exists
    FROM quyen
    WHERE ma_quyen = 43;

    SELECT COUNT(*) INTO v_symbol_exists
    FROM quyen
        WHERE BINARY ky_hieu_quyen = BINARY N'NghiPhep.Approve';

    IF v_id_exists > 0 AND NOT EXISTS (
        SELECT 1
        FROM quyen
        WHERE ma_quyen = 43
          AND BINARY ky_hieu_quyen = BINARY N'NghiPhep.Approve'
          AND BINARY ten_quyen = BINARY N'Duyệt nghỉ phép'
          AND BINARY module = BINARY N'NghiPhep'
    ) THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'RBAC_NGHIPHEP_APPROVE_ID_COLLISION';
    END IF;

    IF v_symbol_exists > 0 AND NOT EXISTS (
        SELECT 1
        FROM quyen
        WHERE ma_quyen = 43
          AND BINARY ky_hieu_quyen = BINARY N'NghiPhep.Approve'
    ) THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'RBAC_NGHIPHEP_APPROVE_SYMBOL_COLLISION';
    END IF;

    IF v_id_exists = 0 AND v_symbol_exists = 0 THEN
        INSERT INTO quyen (ma_quyen, ky_hieu_quyen, ten_quyen, module)
        VALUES (43, N'NghiPhep.Approve', N'Duyệt nghỉ phép', N'NghiPhep');
    END IF;

    INSERT IGNORE INTO vai_tro_quyen (ma_vt, ma_quyen)
    SELECT 1, 43
    FROM vai_tro
    WHERE ma_vt = 1;

    INSERT IGNORE INTO vai_tro_quyen (ma_vt, ma_quyen)
    SELECT 4, 43
    FROM vai_tro
    WHERE ma_vt = 4;

    IF NOT EXISTS (
        SELECT 1
        FROM quyen
        WHERE ma_quyen = 43
          AND BINARY ky_hieu_quyen = BINARY N'NghiPhep.Approve'
          AND BINARY ten_quyen = BINARY N'Duyệt nghỉ phép'
          AND BINARY module = BINARY N'NghiPhep'
    ) THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'RBAC_NGHIPHEP_APPROVE_INSERT_FAILED';
    END IF;

    IF EXISTS (SELECT 1 FROM vai_tro WHERE ma_vt = 1)
       AND NOT EXISTS (
           SELECT 1 FROM vai_tro_quyen WHERE ma_vt = 1 AND ma_quyen = 43
       ) THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'RBAC_NGHIPHEP_APPROVE_ROLE1_GRANT_FAILED';
    END IF;

    IF EXISTS (SELECT 1 FROM vai_tro WHERE ma_vt = 4)
       AND NOT EXISTS (
           SELECT 1 FROM vai_tro_quyen WHERE ma_vt = 4 AND ma_quyen = 43
       ) THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'RBAC_NGHIPHEP_APPROVE_ROLE4_GRANT_FAILED';
    END IF;

    COMMIT;
END//

CALL sp_upgrade_nghiphep_approve_permission()//
DROP PROCEDURE IF EXISTS sp_upgrade_nghiphep_approve_permission//

DELIMITER ;
