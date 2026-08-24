-- Department contract v1 for MariaDB 10.4.
-- Run only on an approved/disposable target selected by the caller.
-- This script does not USE, CREATE, or DROP DATABASE, and never maps roles.
-- Before any contract routine or table DDL, a fail-closed preflight rejects
-- NULL, blank, untrimmed, or duplicate names without changing the data.

DELIMITER //

DROP PROCEDURE IF EXISTS sp_phong_ban_contract_preflight//
CREATE PROCEDURE sp_phong_ban_contract_preflight()
BEGIN
    IF EXISTS (
        SELECT 1
        FROM phong_ban
        WHERE ten_pb IS NULL
           OR BINARY ten_pb <> BINARY TRIM(ten_pb)
           OR TRIM(ten_pb) = N''
    ) THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'PB_CONTRACT_PREFLIGHT_FAILED';
    END IF;

    IF EXISTS (
        SELECT 1
        FROM phong_ban
        GROUP BY ten_pb
        HAVING COUNT(*) > 1
    ) THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'PB_CONTRACT_PREFLIGHT_FAILED';
    END IF;
END//

CALL sp_phong_ban_contract_preflight()//
DROP PROCEDURE IF EXISTS sp_phong_ban_contract_preflight//

DELIMITER ;

SET @pb_unique_index_exists = (
    SELECT COUNT(*)
    FROM information_schema.STATISTICS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'phong_ban'
      AND INDEX_NAME = 'uq_phong_ban_ten_pb'
      AND NON_UNIQUE = 0
      AND COLUMN_NAME = 'ten_pb'
);
SET @pb_unique_index_sql = IF(
    @pb_unique_index_exists = 1,
    'SET @pb_unique_noop = 1',
    'ALTER TABLE phong_ban ADD UNIQUE INDEX uq_phong_ban_ten_pb (ten_pb)'
);
PREPARE pb_unique_index_statement FROM @pb_unique_index_sql;
EXECUTE pb_unique_index_statement;
DEALLOCATE PREPARE pb_unique_index_statement;
SET @pb_unique_index_exists = NULL;
SET @pb_unique_index_sql = NULL;
SET @pb_unique_noop = NULL;

DELIMITER //

DROP PROCEDURE IF EXISTS sp_phong_ban_them//
CREATE PROCEDURE sp_phong_ban_them(IN p_ten_pb NVARCHAR(100))
BEGIN
    SET p_ten_pb = LTRIM(RTRIM(IFNULL(p_ten_pb, N'')));

    IF p_ten_pb = N'' THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'PB_NAME_REQUIRED';
    END IF;
    IF CHAR_LENGTH(p_ten_pb) > 100 THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'PB_NAME_TOO_LONG';
    END IF;
    IF EXISTS (SELECT 1 FROM phong_ban WHERE ten_pb = p_ten_pb) THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'PB_NAME_DUPLICATE';
    END IF;

    INSERT INTO phong_ban (ten_pb) VALUES (p_ten_pb);
END//

DROP PROCEDURE IF EXISTS sp_phong_ban_sua//
CREATE PROCEDURE sp_phong_ban_sua(
    IN p_ma_pb INT,
    IN p_ten_pb NVARCHAR(100)
)
BEGIN
    SET p_ten_pb = LTRIM(RTRIM(IFNULL(p_ten_pb, N'')));

    IF p_ma_pb IS NULL OR p_ma_pb < 1 THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'PB_ID_INVALID';
    END IF;
    IF NOT EXISTS (SELECT 1 FROM phong_ban WHERE ma_pb = p_ma_pb) THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'PB_NOT_FOUND';
    END IF;
    IF p_ten_pb = N'' THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'PB_NAME_REQUIRED';
    END IF;
    IF CHAR_LENGTH(p_ten_pb) > 100 THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'PB_NAME_TOO_LONG';
    END IF;
    IF EXISTS (
        SELECT 1 FROM phong_ban
        WHERE ten_pb = p_ten_pb AND ma_pb <> p_ma_pb
    ) THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'PB_NAME_DUPLICATE';
    END IF;

    UPDATE phong_ban SET ten_pb = p_ten_pb WHERE ma_pb = p_ma_pb;
END//

DROP PROCEDURE IF EXISTS sp_phong_ban_xoa//
CREATE PROCEDURE sp_phong_ban_xoa(IN p_ma_pb INT)
BEGIN
    IF p_ma_pb IS NULL OR p_ma_pb < 1 THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'PB_ID_INVALID';
    END IF;
    IF NOT EXISTS (SELECT 1 FROM phong_ban WHERE ma_pb = p_ma_pb) THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'PB_NOT_FOUND';
    END IF;
    IF EXISTS (SELECT 1 FROM nhan_vien WHERE ma_pb = p_ma_pb) THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'PB_IN_USE';
    END IF;

    DELETE FROM phong_ban WHERE ma_pb = p_ma_pb;
END//

DROP PROCEDURE IF EXISTS sp_phong_ban_danh_sach//
CREATE PROCEDURE sp_phong_ban_danh_sach()
BEGIN
    SELECT pb.ma_pb,
        pb.ten_pb,
        fn_dem_nhan_vien_theo_phong_ban(pb.ma_pb) AS so_nhan_vien
    FROM phong_ban pb
    ORDER BY pb.ma_pb ASC;
END//

DROP PROCEDURE IF EXISTS sp_phong_ban_chi_tiet//
CREATE PROCEDURE sp_phong_ban_chi_tiet(IN p_ma_pb INT)
BEGIN
    IF p_ma_pb IS NULL OR p_ma_pb < 1 THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'PB_ID_INVALID';
    END IF;
    IF NOT EXISTS (SELECT 1 FROM phong_ban WHERE ma_pb = p_ma_pb) THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'PB_NOT_FOUND';
    END IF;

    SELECT pb.ma_pb,
        pb.ten_pb,
        fn_dem_nhan_vien_theo_phong_ban(pb.ma_pb) AS so_nhan_vien
    FROM phong_ban pb
    WHERE pb.ma_pb = p_ma_pb;
END//

DELIMITER ;

-- Canonical definitions; this is intentionally a catalog-only provision.
INSERT INTO quyen (ky_hieu_quyen, ten_quyen, module)
SELECT 'PHONG_BAN_XEM', 'Xem phòng ban', 'PHONG_BAN'
WHERE NOT EXISTS (SELECT 1 FROM quyen WHERE ky_hieu_quyen = 'PHONG_BAN_XEM');
INSERT INTO quyen (ky_hieu_quyen, ten_quyen, module)
SELECT 'PHONG_BAN_TAO', 'Tạo phòng ban', 'PHONG_BAN'
WHERE NOT EXISTS (SELECT 1 FROM quyen WHERE ky_hieu_quyen = 'PHONG_BAN_TAO');
INSERT INTO quyen (ky_hieu_quyen, ten_quyen, module)
SELECT 'PHONG_BAN_SUA', 'Sửa phòng ban', 'PHONG_BAN'
WHERE NOT EXISTS (SELECT 1 FROM quyen WHERE ky_hieu_quyen = 'PHONG_BAN_SUA');
INSERT INTO quyen (ky_hieu_quyen, ten_quyen, module)
SELECT 'PHONG_BAN_XOA', 'Xóa phòng ban', 'PHONG_BAN'
WHERE NOT EXISTS (SELECT 1 FROM quyen WHERE ky_hieu_quyen = 'PHONG_BAN_XOA');
