DELIMITER //

# WHY: MariaDB DDL implicitly commits, so every ambiguity and orphan must be
# rejected in one read-only block before the first schema mutation below.
BEGIN NOT ATOMIC
    DECLARE v_permission_fk_count INT DEFAULT 0;
    DECLARE v_permission_fk_shape_count INT DEFAULT 0;
    DECLARE v_role_fk_count INT DEFAULT 0;
    DECLARE v_role_fk_shape_count INT DEFAULT 0;
    DECLARE v_default_role_count INT DEFAULT 0;
    DECLARE v_permission_update_rule VARCHAR(30);
    DECLARE v_permission_delete_rule VARCHAR(30);

    IF EXISTS (
        SELECT 1
        FROM quyen
        WHERE ma_quyen IS NULL OR ma_quyen <= 0
    ) THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'RBAC_PERMISSION_ID_INVALID';
    END IF;

    # WHY: ALTER ... MODIFY to signed INT is a destructive DDL boundary. Check
    # both sides while the legacy key is still intact so an over-range BIGINT
    # cannot drop the FK and then fail halfway through the rewrite.
    IF EXISTS (
        SELECT 1
        FROM quyen
        WHERE CAST(ma_quyen AS DECIMAL(20, 0)) < 1
           OR CAST(ma_quyen AS DECIMAL(20, 0)) > 2147483647
    ) OR EXISTS (
        SELECT 1
        FROM vai_tro_quyen
        WHERE CAST(ma_quyen AS DECIMAL(20, 0)) < 1
           OR CAST(ma_quyen AS DECIMAL(20, 0)) > 2147483647
    ) THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'RBAC_PERMISSION_ID_RANGE_INVALID';
    END IF;

    # WHY: a correctly named FK is not enough if legacy signedness/width drift
    # would make the parent rewrite incompatible with its still-live child.
    # Reject both key columns before the first UPDATE/DROP/ALTER; AUTO_INCREMENT
    # on the parent is intentionally not part of this type invariant.
    IF (
        SELECT COUNT(*)
        FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE()
          AND (
                (TABLE_NAME = 'quyen' AND COLUMN_NAME = 'ma_quyen')
                OR (TABLE_NAME = 'vai_tro_quyen' AND COLUMN_NAME = 'ma_quyen')
          )
          AND DATA_TYPE = 'int'
          AND IS_NULLABLE = 'NO'
          AND COLUMN_TYPE NOT LIKE '%unsigned%'
    ) <> 2 THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'RBAC_PERMISSION_ID_TYPE_INVALID';
    END IF;

    IF EXISTS (
        SELECT 1
        FROM quyen
        WHERE ky_hieu_quyen IS NULL OR TRIM(ky_hieu_quyen) = ''
    ) THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'RBAC_PERMISSION_SYMBOL_INVALID';
    END IF;

    IF EXISTS (
        SELECT UPPER(TRIM(ky_hieu_quyen))
        FROM quyen
        GROUP BY UPPER(TRIM(ky_hieu_quyen))
        HAVING COUNT(*) > 1
    ) THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'RBAC_PERMISSION_SYMBOL_DUPLICATE';
    END IF;

    IF EXISTS (
        SELECT 1
        FROM vai_tro_quyen vtq
        LEFT JOIN quyen q ON q.ma_quyen = vtq.ma_quyen
        WHERE q.ma_quyen IS NULL
    ) OR EXISTS (
        SELECT 1
        FROM vai_tro_quyen vtq
        LEFT JOIN vai_tro vt ON vt.ma_vt = vtq.ma_vt
        WHERE vt.ma_vt IS NULL
    ) THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'RBAC_ROLE_PERMISSION_ORPHAN';
    END IF;

    SELECT COUNT(*)
    INTO v_permission_fk_count
    FROM information_schema.KEY_COLUMN_USAGE k
    JOIN information_schema.REFERENTIAL_CONSTRAINTS r
      ON r.CONSTRAINT_SCHEMA = k.CONSTRAINT_SCHEMA
     AND r.CONSTRAINT_NAME = k.CONSTRAINT_NAME
     AND r.TABLE_NAME = k.TABLE_NAME
    WHERE k.CONSTRAINT_SCHEMA = DATABASE()
      AND k.TABLE_NAME = 'vai_tro_quyen'
      AND k.COLUMN_NAME = 'ma_quyen'
      AND k.REFERENCED_TABLE_SCHEMA = DATABASE();

    SELECT COUNT(*)
    INTO v_permission_fk_shape_count
    FROM information_schema.KEY_COLUMN_USAGE k
    JOIN information_schema.REFERENTIAL_CONSTRAINTS r
      ON r.CONSTRAINT_SCHEMA = k.CONSTRAINT_SCHEMA
     AND r.CONSTRAINT_NAME = k.CONSTRAINT_NAME
     AND r.TABLE_NAME = k.TABLE_NAME
    WHERE k.CONSTRAINT_SCHEMA = DATABASE()
      AND k.CONSTRAINT_NAME = 'fk_vai_tro_quyen_quyen'
      AND k.TABLE_NAME = 'vai_tro_quyen'
      AND k.COLUMN_NAME = 'ma_quyen'
      AND k.REFERENCED_TABLE_SCHEMA = DATABASE()
      AND k.REFERENCED_TABLE_NAME = 'quyen'
      AND k.REFERENCED_COLUMN_NAME = 'ma_quyen';

    IF v_permission_fk_count <> 1 OR v_permission_fk_shape_count <> 1 THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'RBAC_PERMISSION_FK_INVALID';
    END IF;

    # WHY: the role foreign key is a separate invariant and must survive the
    # parent-key rewrite untouched.
    SELECT COUNT(*)
    INTO v_role_fk_count
    FROM information_schema.KEY_COLUMN_USAGE k
    JOIN information_schema.REFERENTIAL_CONSTRAINTS r
      ON r.CONSTRAINT_SCHEMA = k.CONSTRAINT_SCHEMA
     AND r.CONSTRAINT_NAME = k.CONSTRAINT_NAME
     AND r.TABLE_NAME = k.TABLE_NAME
    WHERE k.CONSTRAINT_SCHEMA = DATABASE()
      AND k.CONSTRAINT_NAME = 'fk_vai_tro_quyen_vai_tro'
      AND k.TABLE_NAME = 'vai_tro_quyen'
      AND k.COLUMN_NAME = 'ma_vt'
      AND k.REFERENCED_TABLE_SCHEMA = DATABASE();

    SELECT COUNT(*)
    INTO v_role_fk_shape_count
    FROM information_schema.KEY_COLUMN_USAGE k
    JOIN information_schema.REFERENTIAL_CONSTRAINTS r
      ON r.CONSTRAINT_SCHEMA = k.CONSTRAINT_SCHEMA
     AND r.CONSTRAINT_NAME = k.CONSTRAINT_NAME
     AND r.TABLE_NAME = k.TABLE_NAME
    WHERE k.CONSTRAINT_SCHEMA = DATABASE()
      AND k.CONSTRAINT_NAME = 'fk_vai_tro_quyen_vai_tro'
      AND k.TABLE_NAME = 'vai_tro_quyen'
      AND k.COLUMN_NAME = 'ma_vt'
      AND k.REFERENCED_TABLE_SCHEMA = DATABASE()
      AND k.REFERENCED_TABLE_NAME = 'vai_tro'
      AND k.REFERENCED_COLUMN_NAME = 'ma_vt';

    IF v_role_fk_count <> 1 OR v_role_fk_shape_count <> 1 THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'RBAC_ROLE_FK_INVALID';
    END IF;

    SELECT r.UPDATE_RULE, r.DELETE_RULE
    INTO v_permission_update_rule, v_permission_delete_rule
    FROM information_schema.KEY_COLUMN_USAGE k
    JOIN information_schema.REFERENTIAL_CONSTRAINTS r
      ON r.CONSTRAINT_SCHEMA = k.CONSTRAINT_SCHEMA
     AND r.CONSTRAINT_NAME = k.CONSTRAINT_NAME
     AND r.TABLE_NAME = k.TABLE_NAME
    WHERE k.CONSTRAINT_SCHEMA = DATABASE()
      AND k.CONSTRAINT_NAME = 'fk_vai_tro_quyen_quyen'
      AND k.TABLE_NAME = 'vai_tro_quyen'
      AND k.COLUMN_NAME = 'ma_quyen'
      AND k.REFERENCED_TABLE_SCHEMA = DATABASE()
      AND k.REFERENCED_TABLE_NAME = 'quyen'
      AND k.REFERENCED_COLUMN_NAME = 'ma_quyen';

    IF v_permission_update_rule IS NULL OR v_permission_delete_rule IS NULL THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'RBAC_PERMISSION_FK_INVALID';
    END IF;

    SELECT COUNT(*)
    INTO v_default_role_count
    FROM vai_tro
    WHERE BINARY ky_hieu = BINARY 'NHAN_VIEN_MAC_DINH';

    IF v_default_role_count <> 1
       OR EXISTS (
            SELECT 1
            FROM vai_tro vt
            JOIN vai_tro_quyen vtq ON vtq.ma_vt = vt.ma_vt
            WHERE BINARY vt.ky_hieu = BINARY 'NHAN_VIEN_MAC_DINH'
       ) THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'RBAC_DEFAULT_ROLE_INVALID';
    END IF;

    SET @rbac_permission_update_rule = v_permission_update_rule;
    SET @rbac_permission_delete_rule = v_permission_delete_rule;
END//

DELIMITER ;

UPDATE quyen
SET ky_hieu_quyen = UPPER(TRIM(ky_hieu_quyen));

ALTER TABLE vai_tro_quyen
    DROP FOREIGN KEY fk_vai_tro_quyen_quyen;

ALTER TABLE quyen
    MODIFY ma_quyen INT NOT NULL AUTO_INCREMENT,
    DROP INDEX IF EXISTS uq_quyen_ky_hieu_quyen,
    ADD CONSTRAINT uq_quyen_ky_hieu_quyen UNIQUE (ky_hieu_quyen);

SET @rbac_fk_sql = CONCAT(
    'ALTER TABLE vai_tro_quyen ADD CONSTRAINT fk_vai_tro_quyen_quyen ',
    'FOREIGN KEY (ma_quyen) REFERENCES quyen(ma_quyen) ',
    'ON DELETE ', @rbac_permission_delete_rule,
    ' ON UPDATE ', @rbac_permission_update_rule
);
PREPARE rbac_fk_statement FROM @rbac_fk_sql;
EXECUTE rbac_fk_statement;
DEALLOCATE PREPARE rbac_fk_statement;

DELIMITER //
BEGIN NOT ATOMIC
    # WHY: verify the recreated edge after DDL before routines or seeds can
    # hide a changed referential action.
    IF (
        SELECT COUNT(*)
        FROM information_schema.KEY_COLUMN_USAGE k
        JOIN information_schema.REFERENTIAL_CONSTRAINTS r
          ON r.CONSTRAINT_SCHEMA = k.CONSTRAINT_SCHEMA
         AND r.CONSTRAINT_NAME = k.CONSTRAINT_NAME
         AND r.TABLE_NAME = k.TABLE_NAME
        WHERE k.CONSTRAINT_SCHEMA = DATABASE()
          AND k.CONSTRAINT_NAME = 'fk_vai_tro_quyen_quyen'
          AND k.TABLE_NAME = 'vai_tro_quyen'
          AND k.COLUMN_NAME = 'ma_quyen'
          AND k.REFERENCED_TABLE_NAME = 'quyen'
          AND k.REFERENCED_COLUMN_NAME = 'ma_quyen'
          AND r.UPDATE_RULE = @rbac_permission_update_rule
          AND r.DELETE_RULE = @rbac_permission_delete_rule
    ) <> 1 THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'RBAC_PERMISSION_FK_RECREATE_INVALID';
    END IF;
END//
DELIMITER ;

INSERT INTO quyen (ky_hieu_quyen, ten_quyen, module)
VALUES
    ('NHAN_VIEN_XEM', N'Xem nhân viên', 'NHAN_VIEN'),
    ('NHAN_VIEN_TAO', N'Tạo nhân viên', 'NHAN_VIEN'),
    ('NHAN_VIEN_SUA', N'Sửa nhân viên', 'NHAN_VIEN'),
    ('NHAN_VIEN_XOA', N'Xóa hoặc kết thúc làm việc', 'NHAN_VIEN'),
    ('NHAN_VIEN_DAT_LAI_MAT_KHAU', N'Đặt lại mật khẩu nhân viên', 'NHAN_VIEN')
ON DUPLICATE KEY UPDATE ky_hieu_quyen = VALUES(ky_hieu_quyen);

DELIMITER //

DROP PROCEDURE IF EXISTS sp_quyen_them//
DROP PROCEDURE IF EXISTS sp_quyen_danh_sach//
DROP PROCEDURE IF EXISTS sp_quyen_lay_theo_ma_nhan_vien//
DROP PROCEDURE IF EXISTS sp_vai_tro_quyen_them//
DROP PROCEDURE IF EXISTS sp_vai_tro_xoa//
DROP PROCEDURE IF EXISTS sp_nhan_vien_gan_vai_tro_noi_bo//

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

    IF EXISTS (
        SELECT 1 FROM quyen
        WHERE BINARY ky_hieu_quyen = BINARY v_ky_hieu_quyen
    ) THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'RBAC_PERMISSION_DUPLICATE';
    END IF;

    INSERT INTO quyen (ky_hieu_quyen, ten_quyen, module)
    VALUES (v_ky_hieu_quyen, v_ten_quyen, v_module);
END//

CREATE PROCEDURE sp_quyen_danh_sach()
BEGIN
    SELECT q.ma_quyen, q.ky_hieu_quyen, q.ten_quyen, q.module
    FROM quyen q
    ORDER BY q.ky_hieu_quyen ASC, q.ma_quyen ASC;
END//

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
    IF NOT EXISTS (SELECT 1 FROM quyen WHERE ma_quyen = p_ma_quyen) THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'VT_PERMISSION_NOT_FOUND';
    END IF;
    IF EXISTS (
        SELECT 1 FROM vai_tro_quyen WHERE ma_vt = p_ma_vt AND ma_quyen = p_ma_quyen
    ) THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'VT_PERMISSION_DUPLICATE';
    END IF;

    INSERT INTO vai_tro_quyen (ma_vt, ma_quyen) VALUES (p_ma_vt, p_ma_quyen);
END//

CREATE PROCEDURE sp_vai_tro_xoa(
    IN p_ma_vt INT
)
BEGIN
    DECLARE v_ky_hieu_vai_tro VARCHAR(50);
    DECLARE v_role_found TINYINT DEFAULT 1;
    DECLARE CONTINUE HANDLER FOR NOT FOUND SET v_role_found = 0;

    # WHY: the caller owns the transaction; lock first, then remove only the
    # role and its mappings. Employees are never deleted by this routine.
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
    IF EXISTS (SELECT 1 FROM nhan_vien WHERE ma_vt = p_ma_vt) THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'VT_DANG_DUOC_SU_DUNG';
    END IF;

    DELETE FROM vai_tro_quyen WHERE ma_vt = p_ma_vt;
    DELETE FROM vai_tro WHERE ma_vt = p_ma_vt;
END//

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
