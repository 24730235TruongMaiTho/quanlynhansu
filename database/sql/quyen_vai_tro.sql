DELIMITER //

/* ============================
   VAI TRÒ
   ============================ */

/* --------------------------------------
   Thêm vai trò
   -------------------------------------- */
DROP PROCEDURE IF EXISTS sp_vai_tro_them//

CREATE PROCEDURE sp_vai_tro_them(
    IN p_ten_vt NVARCHAR(100),
    IN p_mo_ta NVARCHAR(255)
)
BEGIN
    IF EXISTS (SELECT 1 FROM vai_tro WHERE ten_vt = p_ten_vt) THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Tên vai trò đã tồn tại.';
    END IF;
    INSERT INTO vai_tro(ten_vt, mo_ta) VALUES(p_ten_vt, p_mo_ta);
END//
/* --------------------------------------
   Sửa vai trò
   -------------------------------------- */
DROP PROCEDURE IF EXISTS sp_vai_tro_sua//

CREATE PROCEDURE sp_vai_tro_sua(
    IN p_ma_vt INT,
    IN p_ten_vt NVARCHAR(100),
    IN p_mo_ta NVARCHAR(255)
)
BEGIN
    IF EXISTS (SELECT 1 FROM vai_tro WHERE ten_vt = p_ten_vt AND ma_vt <> p_ma_vt) THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = N'Tên vai trò đã tồn tại.';
    END IF;
    UPDATE vai_tro SET ten_vt = p_ten_vt, mo_ta = p_mo_ta WHERE ma_vt = p_ma_vt;
END//

/* --------------------------------------
   Xóa vai trò
   -------------------------------------- */
DROP PROCEDURE IF EXISTS sp_vai_tro_xoa//

CREATE PROCEDURE sp_vai_tro_xoa(
    IN p_ma_vt INT
)
BEGIN
    DECLARE v_ky_hieu_vai_tro VARCHAR(50);
    DECLARE v_role_found TINYINT DEFAULT 1;
    DECLARE CONTINUE HANDLER FOR NOT FOUND SET v_role_found = 0;

    # WHY: the caller owns the transaction; lock first, then remove only the
    # role and its mappings. Employees are never deleted by this routine.
    SELECT ky_hieu INTO v_ky_hieu_vai_tro
    FROM vai_tro WHERE ma_vt = p_ma_vt FOR UPDATE;
    IF v_role_found = 0 THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'VT_ROLE_NOT_FOUND';
    END IF;
    IF BINARY v_ky_hieu_vai_tro = BINARY 'NHAN_VIEN_MAC_DINH' THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'VT_DEFAULT_ROLE_FORBIDDEN';
    END IF;
    IF EXISTS (SELECT 1 FROM nhan_vien WHERE ma_vt = p_ma_vt) THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'VT_DANG_DUOC_SU_DUNG';
    END IF;
    DELETE FROM vai_tro_quyen WHERE ma_vt = p_ma_vt;
    DELETE FROM vai_tro WHERE ma_vt = p_ma_vt;
END//

/* --------------------------------------
   Danh sách vai trò
   -------------------------------------- */
DROP PROCEDURE IF EXISTS sp_vai_tro_danh_sach//

CREATE PROCEDURE sp_vai_tro_danh_sach()
BEGIN
    SELECT ma_vt, ten_vt, mo_ta FROM vai_tro ORDER BY ten_vt;
END//

/* ============================
   QUYỀN
   ============================ */

/* --------------------------------------
   Thêm quyền
   -------------------------------------- */
DROP PROCEDURE IF EXISTS sp_quyen_them//

CREATE PROCEDURE sp_quyen_them(
    IN p_ky_hieu_quyen NVARCHAR(100),
    IN p_ten_quyen NVARCHAR(50),
    IN p_module NVARCHAR(50)
)
BEGIN
    DECLARE v_ky_hieu_quyen VARCHAR(100);
    DECLARE v_ten_quyen VARCHAR(50);
    DECLARE v_module VARCHAR(50);
    SET v_ky_hieu_quyen = UPPER(TRIM(IFNULL(p_ky_hieu_quyen, '')));
    SET v_ten_quyen = TRIM(IFNULL(p_ten_quyen, N''));
    SET v_module = UPPER(TRIM(IFNULL(p_module, '')));
    IF v_ky_hieu_quyen NOT REGEXP '^[A-Z][A-Z0-9_]{0,99}$'
       OR v_ten_quyen = N''
       OR v_module NOT REGEXP '^[A-Z][A-Z0-9_]{0,49}$' THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'RBAC_PERMISSION_INVALID';
    END IF;
    IF EXISTS (SELECT 1 FROM quyen WHERE BINARY ky_hieu_quyen = BINARY v_ky_hieu_quyen) THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'RBAC_PERMISSION_DUPLICATE';
    END IF;
    INSERT INTO quyen(ky_hieu_quyen, ten_quyen, module)
    VALUES(v_ky_hieu_quyen, v_ten_quyen, v_module);
END//

DROP PROCEDURE IF EXISTS sp_quyen_danh_sach//

CREATE PROCEDURE sp_quyen_danh_sach()
BEGIN
    SELECT q.ma_quyen, q.ky_hieu_quyen, q.ten_quyen, q.module
    FROM quyen q
    ORDER BY q.ky_hieu_quyen ASC, q.ma_quyen ASC;
END//

/* --------------------------------------
   Xóa quyền
   -------------------------------------- */
DROP PROCEDURE IF EXISTS sp_quyen_xoa//

CREATE PROCEDURE sp_quyen_xoa(
    IN p_ma_quyen INT
)
BEGIN
    DECLARE EXIT HANDLER FOR SQLEXCEPTION
    BEGIN
        ROLLBACK;
        RESIGNAL;
    END;
    
    START TRANSACTION;
    IF NOT EXISTS (SELECT 1 FROM quyen WHERE ma_quyen = p_ma_quyen) THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = N'Quyền không tồn tại.';
    END IF;
    DELETE FROM vai_tro_quyen WHERE ma_quyen = p_ma_quyen;
    DELETE FROM quyen WHERE ma_quyen = p_ma_quyen;
    COMMIT;
END//

/* --------------------------------------
   Lấy quyền theo mã nhân viên
   -------------------------------------- */
DROP PROCEDURE IF EXISTS sp_quyen_lay_theo_ma_nhan_vien//

CREATE PROCEDURE sp_quyen_lay_theo_ma_nhan_vien(
    IN p_ma_nv VARCHAR(5)
)
BEGIN
    SELECT DISTINCT UPPER(TRIM(q.ky_hieu_quyen)) AS ky_hieu_quyen
    FROM nhan_vien nv
    JOIN vai_tro_quyen vtq ON vtq.ma_vt = nv.ma_vt
    JOIN quyen q ON q.ma_quyen = vtq.ma_quyen
    WHERE BINARY nv.ma_nv = BINARY UPPER(TRIM(IFNULL(p_ma_nv, '')))
    ORDER BY ky_hieu_quyen ASC;
END//

/* ============================
   VAI TRÒ - QUYỀN
   ============================ */

/* --------------------------------------
   Gán quyền cho vai trò
   -------------------------------------- */
DROP PROCEDURE IF EXISTS sp_vai_tro_quyen_them//

CREATE PROCEDURE sp_vai_tro_quyen_them(
    IN p_ma_vt INT,
    IN p_ma_quyen INT
)
BEGIN
    DECLARE v_ky_hieu_vai_tro VARCHAR(50);
    DECLARE v_role_found TINYINT DEFAULT 1;
    DECLARE CONTINUE HANDLER FOR NOT FOUND SET v_role_found = 0;

    # WHY: lock the role before validating the baseline invariant so concurrent
    # role edits cannot grant a system role a permission.
    SELECT ky_hieu INTO v_ky_hieu_vai_tro
    FROM vai_tro WHERE ma_vt = p_ma_vt FOR UPDATE;
    IF v_role_found = 0 THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'VT_ROLE_NOT_FOUND';
    END IF;
    IF BINARY v_ky_hieu_vai_tro = BINARY 'NHAN_VIEN_MAC_DINH' THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'VT_DEFAULT_ROLE_FORBIDDEN';
    END IF;
    IF NOT EXISTS (SELECT 1 FROM quyen WHERE ma_quyen = p_ma_quyen) THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'VT_PERMISSION_NOT_FOUND';
    END IF;
    IF EXISTS (SELECT 1 FROM vai_tro_quyen WHERE ma_vt = p_ma_vt AND ma_quyen = p_ma_quyen) THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'VT_PERMISSION_DUPLICATE';
    END IF;
    INSERT INTO vai_tro_quyen(ma_vt, ma_quyen) VALUES(p_ma_vt, p_ma_quyen);
END//

/* --------------------------------------
   Xóa quyền khỏi vai trò
   -------------------------------------- */
DROP PROCEDURE IF EXISTS sp_vai_tro_quyen_xoa//

CREATE PROCEDURE sp_vai_tro_quyen_xoa(
    IN p_ma_vt INT
)
BEGIN
    DECLARE v_ky_hieu_vai_tro VARCHAR(50);
    DECLARE v_role_found TINYINT DEFAULT 1;
    DECLARE CONTINUE HANDLER FOR NOT FOUND SET v_role_found = 0;

    SELECT ky_hieu
    INTO v_ky_hieu_vai_tro
    FROM vai_tro
    WHERE ma_vt = p_ma_vt
    FOR UPDATE;

    IF v_role_found = 0 THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'VT_ROLE_NOT_FOUND';
    END IF;
    IF BINARY v_ky_hieu_vai_tro = BINARY 'NHAN_VIEN_MAC_DINH' THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'VT_DEFAULT_ROLE_FORBIDDEN';
    END IF;
    DELETE FROM vai_tro_quyen WHERE ma_vt = p_ma_vt;
END//

/* --------------------------------------
   Lấy quyền theo vai trò
   -------------------------------------- */
DROP PROCEDURE IF EXISTS sp_vai_tro_quyen_lay_quyen_theo_vai_tro//

CREATE PROCEDURE sp_vai_tro_quyen_lay_quyen_theo_vai_tro(
    IN p_ma_vt INT
)
BEGIN
    SELECT ma_quyen FROM vai_tro_quyen WHERE ma_vt = p_ma_vt;
END//

DROP PROCEDURE IF EXISTS sp_nhan_vien_gan_vai_tro_noi_bo//

CREATE PROCEDURE sp_nhan_vien_gan_vai_tro_noi_bo(
    IN p_ma_nv VARCHAR(5),
    IN p_ma_vt INT
)
BEGIN
    DECLARE v_ma_nv VARCHAR(5);
    DECLARE v_ma_vt_hien_tai INT;
    DECLARE v_ky_hieu_vai_tro_hien_tai VARCHAR(50);
    DECLARE v_ky_hieu_vai_tro_moi VARCHAR(50);
    DECLARE v_lookup_found TINYINT DEFAULT 1;
    DECLARE CONTINUE HANDLER FOR NOT FOUND SET v_lookup_found = 0;

    SET v_ma_nv = UPPER(TRIM(IFNULL(p_ma_nv, '')));

    # WHY: employee then target-role is the single lock order for this internal
    # seam; the caller owns commit/rollback and this routine changes only ma_vt.
    SELECT nv.ma_vt, vt.ky_hieu
    INTO v_ma_vt_hien_tai, v_ky_hieu_vai_tro_hien_tai
    FROM nhan_vien nv
    LEFT JOIN vai_tro vt ON vt.ma_vt = nv.ma_vt
    WHERE BINARY nv.ma_nv = BINARY v_ma_nv
    FOR UPDATE;

    IF v_lookup_found = 0 THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'NV_NOT_FOUND';
    END IF;
    IF BINARY IFNULL(v_ky_hieu_vai_tro_hien_tai, '') <> BINARY 'NHAN_VIEN_MAC_DINH' THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'VT_EMPLOYEE_ROLE_INVALID';
    END IF;

    SET v_lookup_found = 1;
    SELECT ky_hieu
    INTO v_ky_hieu_vai_tro_moi
    FROM vai_tro
    WHERE ma_vt = p_ma_vt
    FOR UPDATE;

    IF v_lookup_found = 0 THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'VT_TARGET_ROLE_INVALID';
    END IF;
    IF BINARY v_ky_hieu_vai_tro_moi = BINARY 'NHAN_VIEN_MAC_DINH' THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'VT_TARGET_ROLE_INVALID';
    END IF;

    UPDATE nhan_vien
    SET ma_vt = p_ma_vt
    WHERE BINARY ma_nv = BINARY v_ma_nv;
END//

DELIMITER ;