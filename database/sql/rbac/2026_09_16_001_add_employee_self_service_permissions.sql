-- Superseded RBAC script retained as a migration-history marker.
-- Employee self-service is auth-scoped in the application and no longer
-- requires role 5 assignments in the fresh RBAC snapshot.
-- Do not run this file. Review and approve the dated cleanup script instead.

USE quan_ly_nhan_su;

DELIMITER //

DROP PROCEDURE IF EXISTS sp_upgrade_employee_self_service_permissions//

CREATE PROCEDURE sp_upgrade_employee_self_service_permissions()
BEGIN
    SIGNAL SQLSTATE '45000'
        SET MESSAGE_TEXT = 'RBAC_EMPLOYEE_SELF_SERVICE_SCRIPT_SUPERSEDED';
END//

CALL sp_upgrade_employee_self_service_permissions()//
DROP PROCEDURE IF EXISTS sp_upgrade_employee_self_service_permissions//

DELIMITER ;
