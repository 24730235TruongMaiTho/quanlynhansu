-- Các thủ tục nghiệp vụ cho vai trò, quyền và gán vai trò nội bộ.
-- Lược đồ hiện hành dùng mã số tường minh; không có cột ky_hieu trong vai_tro.

USE quan_ly_nhan_su;

DELIMITER //

DROP PROCEDURE IF EXISTS sp_vai_tro_them//

CREATE PROCEDURE sp_vai_tro_them(
    IN p_ten_vt NVARCHAR(100),
    IN p_mo_ta NVARCHAR(255)
)
BEGIN
    DECLARE v_ma_vt INT;
    DECLARE EXIT HANDLER FOR SQLEXCEPTION
    BEGIN
        ROLLBACK;
        RESIGNAL;
    END;

    IF EXISTS (SELECT 1 FROM vai_tro WHERE ten_vt = p_ten_vt) THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Tên vai trò đã tồn tại.';
    END IF;

    START TRANSACTION;
    SELECT COALESCE(MAX(ma_vt), 0) + 1 INTO v_ma_vt FROM vai_tro FOR UPDATE;
    IF v_ma_vt < 1 THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Mã vai trò không hợp lệ.';
    END IF;
    INSERT INTO vai_tro(ma_vt, ten_vt, mo_ta) VALUES(v_ma_vt, p_ten_vt, p_mo_ta);
    COMMIT;
END//

DROP PROCEDURE IF EXISTS sp_vai_tro_sua//

CREATE PROCEDURE sp_vai_tro_sua(
    IN p_ma_vt INT,
    IN p_ten_vt NVARCHAR(100),
    IN p_mo_ta NVARCHAR(255)
)
BEGIN
    IF EXISTS (SELECT 1 FROM vai_tro WHERE ten_vt = p_ten_vt AND ma_vt <> p_ma_vt) THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Tên vai trò đã tồn tại.';
    END IF;
    UPDATE vai_tro SET ten_vt = p_ten_vt, mo_ta = p_mo_ta WHERE ma_vt = p_ma_vt;
END//

DROP PROCEDURE IF EXISTS sp_vai_tro_xoa//

CREATE PROCEDURE sp_vai_tro_xoa(IN p_ma_vt INT)
BEGIN
    DECLARE v_ma_vt INT DEFAULT NULL;
    DECLARE v_ma_nv VARCHAR(5) DEFAULT NULL;
    DECLARE EXIT HANDLER FOR SQLEXCEPTION
    BEGIN
        ROLLBACK;
        RESIGNAL;
    END;

    START TRANSACTION;
    SELECT ma_vt INTO v_ma_vt
    FROM vai_tro
    WHERE ma_vt = p_ma_vt
    FOR UPDATE;

    IF p_ma_vt = 5 THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'VT_DEFAULT_ROLE_FORBIDDEN';
    END IF;
    IF v_ma_vt IS NULL THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'VT_ROLE_NOT_FOUND';
    END IF;
    SELECT ma_nv INTO v_ma_nv
    FROM nhan_vien
    WHERE ma_vt = p_ma_vt
    LIMIT 1
    FOR UPDATE;
    IF v_ma_nv IS NOT NULL THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'VT_DANG_DUOC_SU_DUNG';
    END IF;
    DELETE FROM vai_tro_quyen WHERE ma_vt = p_ma_vt;
    DELETE FROM vai_tro WHERE ma_vt = p_ma_vt;
    COMMIT;
END//

DROP PROCEDURE IF EXISTS sp_vai_tro_danh_sach//

CREATE PROCEDURE sp_vai_tro_danh_sach()
BEGIN
    SELECT ma_vt, ten_vt, mo_ta FROM vai_tro ORDER BY ten_vt;
END//

DROP PROCEDURE IF EXISTS sp_quyen_them//

CREATE PROCEDURE sp_quyen_them(
    IN p_ky_hieu_quyen NVARCHAR(100),
    IN p_ten_quyen NVARCHAR(100),
    IN p_module NVARCHAR(50)
)
BEGIN
    DECLARE v_ma_quyen INT;
    DECLARE EXIT HANDLER FOR SQLEXCEPTION
    BEGIN
        ROLLBACK;
        RESIGNAL;
    END;

    IF p_ky_hieu_quyen IS NULL
       OR p_ky_hieu_quyen NOT REGEXP '^[A-Za-z][A-Za-z0-9]*\\.[A-Za-z][A-Za-z0-9]*$'
       OR p_ten_quyen IS NULL OR TRIM(p_ten_quyen) = ''
       OR p_module IS NULL OR p_module NOT REGEXP '^[A-Za-z][A-Za-z0-9]*$' THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'RBAC_PERMISSION_INVALID';
    END IF;
    IF EXISTS (SELECT 1 FROM quyen WHERE BINARY ky_hieu_quyen = BINARY p_ky_hieu_quyen) THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'RBAC_PERMISSION_DUPLICATE';
    END IF;

    START TRANSACTION;
    SELECT COALESCE(MAX(ma_quyen), 0) + 1 INTO v_ma_quyen FROM quyen FOR UPDATE;
    IF v_ma_quyen < 1 THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Mã quyền không hợp lệ.';
    END IF;
    INSERT INTO quyen(ma_quyen, ky_hieu_quyen, ten_quyen, module)
    VALUES(v_ma_quyen, p_ky_hieu_quyen, TRIM(p_ten_quyen), p_module);
    COMMIT;
END//

DROP PROCEDURE IF EXISTS sp_quyen_danh_sach//

CREATE PROCEDURE sp_quyen_danh_sach()
BEGIN
    SELECT q.ma_quyen, q.ky_hieu_quyen, q.ten_quyen, q.module
    FROM quyen q
    ORDER BY q.ky_hieu_quyen ASC, q.ma_quyen ASC;
END//

DROP PROCEDURE IF EXISTS sp_quyen_xoa//

CREATE PROCEDURE sp_quyen_xoa(IN p_ma_quyen INT)
BEGIN
    DECLARE EXIT HANDLER FOR SQLEXCEPTION
    BEGIN
        ROLLBACK;
        RESIGNAL;
    END;

    START TRANSACTION;
    IF NOT EXISTS (SELECT 1 FROM quyen WHERE ma_quyen = p_ma_quyen) THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Quyền không tồn tại.';
    END IF;
    DELETE FROM vai_tro_quyen WHERE ma_quyen = p_ma_quyen;
    DELETE FROM quyen WHERE ma_quyen = p_ma_quyen;
    COMMIT;
END//

DROP PROCEDURE IF EXISTS sp_quyen_lay_theo_ma_nhan_vien//

CREATE PROCEDURE sp_quyen_lay_theo_ma_nhan_vien(IN p_ma_nv VARCHAR(5))
BEGIN
    SELECT DISTINCT q.ma_quyen, q.ky_hieu_quyen, q.module
    FROM nhan_vien nv
    JOIN vai_tro_quyen vtq ON vtq.ma_vt = nv.ma_vt
    JOIN quyen q ON q.ma_quyen = vtq.ma_quyen
    WHERE BINARY nv.ma_nv = BINARY TRIM(IFNULL(p_ma_nv, ''))
    ORDER BY q.ma_quyen ASC;
END//

DROP PROCEDURE IF EXISTS sp_vai_tro_quyen_them//

CREATE PROCEDURE sp_vai_tro_quyen_them(IN p_ma_vt INT, IN p_ma_quyen INT)
BEGIN
    DECLARE v_ma_vt INT DEFAULT NULL;
    DECLARE v_ma_quyen INT DEFAULT NULL;
    DECLARE v_existing_quyen INT DEFAULT NULL;
    DECLARE EXIT HANDLER FOR SQLEXCEPTION
    BEGIN
        ROLLBACK;
        RESIGNAL;
    END;

    START TRANSACTION;
    SELECT ma_vt INTO v_ma_vt FROM vai_tro WHERE ma_vt = p_ma_vt FOR UPDATE;
    IF p_ma_vt = 5 THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'VT_DEFAULT_ROLE_FORBIDDEN';
    END IF;
    IF v_ma_vt IS NULL THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'VT_ROLE_NOT_FOUND';
    END IF;
    SELECT ma_quyen INTO v_ma_quyen FROM quyen WHERE ma_quyen = p_ma_quyen FOR UPDATE;
    IF v_ma_quyen IS NULL THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'VT_PERMISSION_NOT_FOUND';
    END IF;
    SELECT ma_quyen INTO v_existing_quyen
    FROM vai_tro_quyen
    WHERE ma_vt = p_ma_vt AND ma_quyen = p_ma_quyen
    FOR UPDATE;
    IF v_existing_quyen IS NOT NULL THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'VT_PERMISSION_DUPLICATE';
    END IF;
    INSERT INTO vai_tro_quyen(ma_vt, ma_quyen) VALUES(p_ma_vt, p_ma_quyen);
    COMMIT;
END//

DROP PROCEDURE IF EXISTS sp_vai_tro_quyen_xoa//

CREATE PROCEDURE sp_vai_tro_quyen_xoa(IN p_ma_vt INT)
BEGIN
    DECLARE v_ma_vt INT DEFAULT NULL;
    DECLARE EXIT HANDLER FOR SQLEXCEPTION
    BEGIN
        ROLLBACK;
        RESIGNAL;
    END;

    START TRANSACTION;
    SELECT ma_vt INTO v_ma_vt FROM vai_tro WHERE ma_vt = p_ma_vt FOR UPDATE;
    IF p_ma_vt = 5 THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'VT_DEFAULT_ROLE_FORBIDDEN';
    END IF;
    IF v_ma_vt IS NULL THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'VT_ROLE_NOT_FOUND';
    END IF;
    DELETE FROM vai_tro_quyen WHERE ma_vt = p_ma_vt;
    COMMIT;
END//

DROP PROCEDURE IF EXISTS sp_vai_tro_quyen_lay_quyen_theo_vai_tro//

CREATE PROCEDURE sp_vai_tro_quyen_lay_quyen_theo_vai_tro(IN p_ma_vt INT)
BEGIN
    SELECT ma_quyen FROM vai_tro_quyen WHERE ma_vt = p_ma_vt ORDER BY ma_quyen;
END//

DROP PROCEDURE IF EXISTS sp_nhan_vien_gan_vai_tro_noi_bo//

CREATE PROCEDURE sp_nhan_vien_gan_vai_tro_noi_bo(
    IN p_ma_nv VARCHAR(5),
    IN p_ma_vt INT
)
BEGIN
    DECLARE v_ma_vt_hien_tai INT;
    DECLARE v_ma_vt_muc_tieu INT DEFAULT NULL;
    DECLARE EXIT HANDLER FOR SQLEXCEPTION
    BEGIN
        ROLLBACK;
        RESIGNAL;
    END;

    START TRANSACTION;
    SELECT ma_vt INTO v_ma_vt_hien_tai
    FROM nhan_vien
    WHERE BINARY ma_nv = BINARY TRIM(IFNULL(p_ma_nv, ''))
    FOR UPDATE;

    IF v_ma_vt_hien_tai IS NULL THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'NV_NOT_FOUND';
    END IF;
    IF v_ma_vt_hien_tai <> 5 THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'VT_EMPLOYEE_ROLE_INVALID';
    END IF;
    SELECT ma_vt INTO v_ma_vt_muc_tieu
    FROM vai_tro
    WHERE ma_vt = p_ma_vt
    FOR UPDATE;
    IF p_ma_vt = 5 OR v_ma_vt_muc_tieu IS NULL THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'VT_TARGET_ROLE_INVALID';
    END IF;

    UPDATE nhan_vien
    SET ma_vt = p_ma_vt
    WHERE BINARY ma_nv = BINARY TRIM(IFNULL(p_ma_nv, ''));
    COMMIT;
END//

DELIMITER ;
