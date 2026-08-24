-- LEGACY EMPLOYEE ROUTINES (superseded 2026-08-24). Historical audit only;
-- do not source as active setup. Current code uses direct Query Builder.

DELIMITER //

# Legacy delete/login procedures owned transactions or accepted plaintext; remove them before
# installing the caller-owned lifecycle and server-only authentication contracts.
DROP PROCEDURE IF EXISTS sp_nhan_vien_xoa//
DROP PROCEDURE IF EXISTS sp_nhan_vien_dang_nhap//
DROP PROCEDURE IF EXISTS sp_nhan_vien_xoa_hoac_nghi_viec//
DROP PROCEDURE IF EXISTS sp_nhan_vien_dat_lai_mat_khau//
DROP PROCEDURE IF EXISTS sp_nhan_vien_cap_nhat_hash_xac_thuc//
DROP PROCEDURE IF EXISTS sp_nhan_vien_lay_tai_khoan_dang_nhap//

CREATE PROCEDURE sp_nhan_vien_xoa_hoac_nghi_viec(
    IN p_ma_nv VARCHAR(5),
    IN p_ngay_nghi_viec DATE,
    OUT p_hanh_dong VARCHAR(12),
    OUT p_anh_cu VARCHAR(255)
)
BEGIN
    DECLARE v_ma_nv VARCHAR(5);
    DECLARE v_ngay_vao_lam DATE;
    DECLARE v_ngay_nghi_cu DATE;
    DECLARE v_ky_hieu_vai_tro VARCHAR(50);
    DECLARE v_ky_hieu_trang_thai VARCHAR(20);
    DECLARE v_ma_tt_da_nghi TINYINT;
    DECLARE v_so_da_nghi INT DEFAULT 0;
    DECLARE v_tim_thay TINYINT DEFAULT 1;
    DECLARE CONTINUE HANDLER FOR NOT FOUND SET v_tim_thay = 0;

    SET p_hanh_dong = NULL;
    SET p_anh_cu = NULL;
    SET v_ma_nv = UPPER(TRIM(IFNULL(p_ma_nv, '')));

    # The caller owns the transaction; locking before role/dependency checks keeps precedence
    # stable and makes the dependency decision atomic with the termination update or delete.
    SELECT nv.ngay_vao_lam, nv.ngay_nghi_viec, nv.anh_dai_dien,
           vt.ky_hieu, tt.ky_hieu
    INTO v_ngay_vao_lam, v_ngay_nghi_cu, p_anh_cu,
         v_ky_hieu_vai_tro, v_ky_hieu_trang_thai
    FROM nhan_vien nv
    LEFT JOIN vai_tro vt ON vt.ma_vt = nv.ma_vt
    LEFT JOIN trang_thai_lam_viec tt ON tt.ma_tt = nv.ma_tt
    WHERE BINARY nv.ma_nv = BINARY v_ma_nv
    FOR UPDATE;

    IF v_tim_thay = 0 THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'NV_NOT_FOUND';
    END IF;

    IF v_ky_hieu_vai_tro IS NULL
       OR BINARY v_ky_hieu_vai_tro <> BINARY 'NHAN_VIEN_MAC_DINH' THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'NV_PRIVILEGED_TARGET';
    END IF;

    IF p_ngay_nghi_viec IS NULL OR p_ngay_nghi_viec < v_ngay_vao_lam THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'NV_REFERENCE_INVALID';
    END IF;

    # A committed DA_NGHI row is terminal: never inspect dependencies or overwrite its first date.
    IF BINARY v_ky_hieu_trang_thai = BINARY 'DA_NGHI'
       AND v_ngay_nghi_cu IS NOT NULL THEN
        SET p_hanh_dong = 'TERMINATED';
    ELSE
        SELECT COUNT(*), MIN(ma_tt)
        INTO v_so_da_nghi, v_ma_tt_da_nghi
        FROM trang_thai_lam_viec
        WHERE BINARY ky_hieu = BINARY 'DA_NGHI';

        IF v_so_da_nghi <> 1 OR v_ma_tt_da_nghi IS NULL THEN
            SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'NV_STATUS_MISSING';
        END IF;

        IF v_ky_hieu_trang_thai IS NULL
           OR BINARY v_ky_hieu_trang_thai NOT IN (BINARY 'DANG_LAM', BINARY 'THU_VIEC')
           OR v_ngay_nghi_cu IS NOT NULL THEN
            SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'NV_STATUS_MISSING';
        END IF;

        IF EXISTS (SELECT 1 FROM hop_dong WHERE BINARY ma_nv = BINARY v_ma_nv)
           OR EXISTS (SELECT 1 FROM cham_cong WHERE BINARY ma_nv = BINARY v_ma_nv)
           OR EXISTS (SELECT 1 FROM nghi_phep WHERE BINARY ma_nv = BINARY v_ma_nv)
           OR EXISTS (SELECT 1 FROM luong WHERE BINARY ma_nv = BINARY v_ma_nv)
           OR EXISTS (SELECT 1 FROM lich_su_he_so_luong WHERE BINARY ma_nv = BINARY v_ma_nv) THEN
            SET p_hanh_dong = 'TERMINATED';
            UPDATE nhan_vien
            SET ma_tt = v_ma_tt_da_nghi,
                ngay_nghi_viec = p_ngay_nghi_viec
            WHERE BINARY ma_nv = BINARY v_ma_nv;
        ELSE
            SET p_hanh_dong = 'DELETED';
            DELETE FROM nhan_vien WHERE BINARY ma_nv = BINARY v_ma_nv;
        END IF;
    END IF;
END//

CREATE PROCEDURE sp_nhan_vien_dat_lai_mat_khau(
    IN p_ma_nv VARCHAR(5),
    IN p_mat_khau_hash VARCHAR(255)
)
BEGIN
    DECLARE v_ma_nv VARCHAR(5);
    DECLARE v_ky_hieu_vai_tro VARCHAR(50);
    DECLARE v_tim_thay TINYINT DEFAULT 1;
    DECLARE CONTINUE HANDLER FOR NOT FOUND SET v_tim_thay = 0;

    SET v_ma_nv = UPPER(TRIM(IFNULL(p_ma_nv, '')));

    # Lock and authorize before validating the new hash so privileged-target precedence is fail closed.
    SELECT vt.ky_hieu
    INTO v_ky_hieu_vai_tro
    FROM nhan_vien nv
    LEFT JOIN vai_tro vt ON vt.ma_vt = nv.ma_vt
    WHERE BINARY nv.ma_nv = BINARY v_ma_nv
    FOR UPDATE;

    IF v_tim_thay = 0 THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'NV_NOT_FOUND';
    END IF;

    IF v_ky_hieu_vai_tro IS NULL
       OR BINARY v_ky_hieu_vai_tro <> BINARY 'NHAN_VIEN_MAC_DINH' THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'NV_PRIVILEGED_TARGET';
    END IF;

    IF p_mat_khau_hash IS NULL OR TRIM(p_mat_khau_hash) = '' OR OCTET_LENGTH(p_mat_khau_hash) > 255 THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'NV_REFERENCE_INVALID';
    END IF;

    UPDATE nhan_vien
    SET mat_khau = p_mat_khau_hash
    WHERE BINARY ma_nv = BINARY v_ma_nv;
END//

CREATE PROCEDURE sp_nhan_vien_cap_nhat_hash_xac_thuc(
    IN p_ma_nv VARCHAR(5),
    IN p_hash_hien_tai VARCHAR(255),
    IN p_hash_moi VARCHAR(255)
)
BEGIN
    DECLARE v_ma_nv VARCHAR(5);
    DECLARE v_hash_hien_tai VARCHAR(255);
    DECLARE v_tim_thay TINYINT DEFAULT 1;
    DECLARE CONTINUE HANDLER FOR NOT FOUND SET v_tim_thay = 0;

    SET v_ma_nv = UPPER(TRIM(IFNULL(p_ma_nv, '')));

    # CAS locks every role because authentication rehash must not become a privileged-role bypass.
    SELECT mat_khau
    INTO v_hash_hien_tai
    FROM nhan_vien
    WHERE BINARY ma_nv = BINARY v_ma_nv
    FOR UPDATE;

    IF v_tim_thay = 0
       OR p_hash_hien_tai IS NULL
       OR NOT (BINARY v_hash_hien_tai = BINARY p_hash_hien_tai) THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'NV_AUTH_HASH_STALE';
    END IF;

    IF p_hash_moi IS NULL OR TRIM(p_hash_moi) = '' OR OCTET_LENGTH(p_hash_moi) > 255 THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'NV_REFERENCE_INVALID';
    END IF;

    UPDATE nhan_vien
    SET mat_khau = p_hash_moi
    WHERE BINARY ma_nv = BINARY v_ma_nv;
END//

CREATE PROCEDURE sp_nhan_vien_lay_tai_khoan_dang_nhap(
    IN p_dinh_danh NVARCHAR(100)
)
BEGIN
    DECLARE v_dinh_danh VARCHAR(100);
    SET v_dinh_danh = TRIM(IFNULL(p_dinh_danh, ''));

    # Return only the six server-side auth fields; terminated status remains visible to the provider,
    # which is the layer that rejects login/session creation in the later auth task.
    SELECT nv.ma_nv, nv.ho_ten, nv.email, nv.mat_khau, nv.ma_vt, tt.ky_hieu
    FROM nhan_vien nv
    LEFT JOIN trang_thai_lam_viec tt ON tt.ma_tt = nv.ma_tt
    WHERE BINARY UPPER(TRIM(nv.ma_nv)) = BINARY UPPER(v_dinh_danh)
       OR BINARY LOWER(TRIM(nv.email)) = BINARY LOWER(v_dinh_danh)
    ORDER BY nv.ma_nv ASC
    LIMIT 1;
END//

DELIMITER ;
