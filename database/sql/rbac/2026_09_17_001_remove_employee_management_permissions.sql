-- Approval-gated remediation for an existing database.
-- Preflight the exact target and take an approved backup before running.
-- Codex does not execute this script against live or non-disposable data.
-- It removes only role 5 assignments for permission ids 25, 26 and 33.
-- Any other role 5 assignment is preserved for explicit manual review.

USE quan_ly_nhan_su;

DELIMITER //

DROP PROCEDURE IF EXISTS sp_remove_employee_management_permissions//

CREATE PROCEDURE sp_remove_employee_management_permissions()
BEGIN
    DECLARE v_remaining INT DEFAULT 0;
    DECLARE EXIT HANDLER FOR SQLEXCEPTION
    BEGIN
        ROLLBACK;
        SET @approved_employee_self_service_cleanup = NULL;
        RESIGNAL;
    END;

    IF COALESCE(@approved_employee_self_service_cleanup, 0) <> 1 THEN
        SIGNAL SQLSTATE '45000'
            SET MESSAGE_TEXT = 'RBAC_EMPLOYEE_SELF_SERVICE_CLEANUP_APPROVAL_REQUIRED';
    END IF;

    START TRANSACTION;

    IF (SELECT COUNT(*) FROM vai_tro WHERE ma_vt = 5) <> 1 THEN
        SIGNAL SQLSTATE '45000'
            SET MESSAGE_TEXT = 'RBAC_EMPLOYEE_ROLE_MISSING';
    END IF;

    IF (SELECT COUNT(*) FROM quyen
        WHERE (ma_quyen = 25 AND BINARY ky_hieu_quyen = BINARY N'NghiPhep.Read')
           OR (ma_quyen = 26 AND BINARY ky_hieu_quyen = BINARY N'NghiPhep.Insert')
           OR (ma_quyen = 33 AND BINARY ky_hieu_quyen = BINARY N'Luong.Read')) <> 3 THEN
        SIGNAL SQLSTATE '45000'
            SET MESSAGE_TEXT = 'RBAC_EMPLOYEE_PERMISSION_METADATA_COLLISION';
    END IF;

    DELETE FROM vai_tro_quyen
    WHERE ma_vt = 5 AND ma_quyen IN (25, 26, 33);

    SELECT COUNT(*) INTO v_remaining
    FROM vai_tro_quyen
    WHERE ma_vt = 5 AND ma_quyen IN (25, 26, 33);

    IF v_remaining <> 0 THEN
        SIGNAL SQLSTATE '45000'
            SET MESSAGE_TEXT = 'RBAC_EMPLOYEE_PERMISSION_CLEANUP_FAILED';
    END IF;

    COMMIT;
    SET @approved_employee_self_service_cleanup = NULL;
END//

CALL sp_remove_employee_management_permissions()//
DROP PROCEDURE IF EXISTS sp_remove_employee_management_permissions//

DELIMITER ;
