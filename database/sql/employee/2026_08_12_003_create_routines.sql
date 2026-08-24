-- LEGACY EMPLOYEE ROUTINES (superseded 2026-08-24). Historical audit only;
-- do not source as active setup. Current code uses direct Query Builder.

DELIMITER //

DROP PROCEDURE IF EXISTS sp_nhan_vien_them//
CREATE PROCEDURE sp_nhan_vien_them(
    IN p_ho_ten NVARCHAR(50),
    IN p_ngay_sinh DATE,
    IN p_gioi_tinh TINYINT,
    IN p_sdt VARCHAR(15),
    IN p_email NVARCHAR(100),
    IN p_ngay_vao_lam DATE,
    IN p_ma_pb INT,
    IN p_ma_cv INT,
    IN p_dan_toc NVARCHAR(50),
    IN p_cccd VARCHAR(12),
    IN p_noi_cap_cccd NVARCHAR(50),
    IN p_hoc_van NVARCHAR(50),
    IN p_ma_tt TINYINT,
    IN p_mat_khau_hash VARCHAR(255),
    IN p_anh_dai_dien VARCHAR(255),
    OUT p_ma_nv VARCHAR(5)
)
BEGIN
    DECLARE v_so_da_cap SMALLINT UNSIGNED;
    DECLARE v_ma_nv VARCHAR(5);
    DECLARE v_ho_ten NVARCHAR(50);
    DECLARE v_sdt VARCHAR(15);
    DECLARE v_email NVARCHAR(100);
    DECLARE v_dan_toc NVARCHAR(50);
    DECLARE v_cccd VARCHAR(12);
    DECLARE v_noi_cap_cccd NVARCHAR(50);
    DECLARE v_hoc_van NVARCHAR(50);
    DECLARE v_ma_vt INT;
    DECLARE v_so_vai_tro INT DEFAULT 0;

    SET p_ma_nv = NULL;
    SET v_ho_ten = TRIM(IFNULL(p_ho_ten, N''));
    SET v_sdt = TRIM(IFNULL(p_sdt, ''));
    SET v_email = LOWER(TRIM(IFNULL(p_email, N'')));
    SET v_dan_toc = TRIM(IFNULL(p_dan_toc, N''));
    SET v_cccd = TRIM(IFNULL(p_cccd, ''));
    SET v_noi_cap_cccd = TRIM(IFNULL(p_noi_cap_cccd, N''));
    SET v_hoc_van = TRIM(IFNULL(p_hoc_van, N''));

    SELECT so_da_cap
    INTO v_so_da_cap
    FROM bo_dem_ma_nhan_vien
    WHERE BINARY ten_bo_dem = BINARY 'NHAN_VIEN'
    FOR UPDATE;

    IF v_so_da_cap IS NULL OR v_so_da_cap >= 999 THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'NV_CODE_EXHAUSTED';
    END IF;

    SET v_ma_nv = CONCAT('NV', LPAD(v_so_da_cap + 1, 3, '0'));

    IF v_ho_ten = N''
       OR p_ngay_sinh IS NULL
       OR p_ngay_vao_lam IS NULL
       OR p_ngay_sinh >= p_ngay_vao_lam
       OR TIMESTAMPDIFF(YEAR, p_ngay_sinh, p_ngay_vao_lam) < 18
       OR p_gioi_tinh IS NULL OR p_gioi_tinh NOT IN (0, 1)
       OR v_sdt NOT REGEXP '^0[0-9]{9}$'
       OR v_email NOT REGEXP '^[^@[:space:]]+@[^@[:space:]]+[.][^@[:space:]]+$'
       OR v_dan_toc = N''
       OR v_cccd NOT REGEXP '^[0-9]{12}$'
       OR v_noi_cap_cccd = N''
       OR v_hoc_van = N''
       OR p_mat_khau_hash IS NULL OR TRIM(p_mat_khau_hash) = ''
       OR (p_anh_dai_dien IS NOT NULL AND (TRIM(p_anh_dai_dien) = '' OR LENGTH(p_anh_dai_dien) > 255)) THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'NV_REFERENCE_INVALID';
    END IF;

    IF NOT EXISTS (SELECT 1 FROM phong_ban WHERE ma_pb = p_ma_pb)
       OR NOT EXISTS (SELECT 1 FROM chuc_vu WHERE ma_cv = p_ma_cv) THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'NV_REFERENCE_INVALID';
    END IF;

    IF NOT EXISTS (
        SELECT 1
        FROM trang_thai_lam_viec
        WHERE ma_tt = p_ma_tt
          AND BINARY ky_hieu IN (BINARY 'DANG_LAM', BINARY 'THU_VIEC')
    ) THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'NV_STATUS_MISSING';
    END IF;

    IF EXISTS (SELECT 1 FROM nhan_vien WHERE LOWER(TRIM(email)) = v_email) THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'NV_EMAIL_DUPLICATE';
    END IF;

    IF EXISTS (SELECT 1 FROM nhan_vien WHERE TRIM(cccd) = v_cccd) THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'NV_CCCD_DUPLICATE';
    END IF;

    SELECT COUNT(*)
    INTO v_so_vai_tro
    FROM vai_tro
    WHERE BINARY ky_hieu = BINARY 'NHAN_VIEN_MAC_DINH';

    IF v_so_vai_tro <> 1 THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'NV_DEFAULT_ROLE_INVALID';
    END IF;

    SELECT ma_vt
    INTO v_ma_vt
    FROM vai_tro
    WHERE BINARY ky_hieu = BINARY 'NHAN_VIEN_MAC_DINH'
    FOR UPDATE;

    IF EXISTS (SELECT 1 FROM vai_tro_quyen WHERE ma_vt = v_ma_vt) THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'NV_DEFAULT_ROLE_INVALID';
    END IF;

    IF EXISTS (SELECT 1 FROM nhan_vien WHERE ma_nv = v_ma_nv) THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'NV_CODE_EXHAUSTED';
    END IF;

    UPDATE bo_dem_ma_nhan_vien
    SET so_da_cap = v_so_da_cap + 1
    WHERE BINARY ten_bo_dem = BINARY 'NHAN_VIEN';

    INSERT INTO nhan_vien (
        ma_nv, ho_ten, ngay_sinh, gioi_tinh, sdt, email, ngay_vao_lam,
        ma_pb, ma_cv, dan_toc, cccd, noi_cap_cccd, hoc_van, ma_tt,
        mat_khau, ma_vt, anh_dai_dien, ngay_nghi_viec
    ) VALUES (
        v_ma_nv, v_ho_ten, p_ngay_sinh, p_gioi_tinh, v_sdt, v_email, p_ngay_vao_lam,
        p_ma_pb, p_ma_cv, v_dan_toc, v_cccd, v_noi_cap_cccd, v_hoc_van, p_ma_tt,
        p_mat_khau_hash, v_ma_vt, p_anh_dai_dien, NULL
    );

    SET p_ma_nv = v_ma_nv;
END//

DROP PROCEDURE IF EXISTS sp_dia_chi_nhan_vien_luu//
CREATE PROCEDURE sp_dia_chi_nhan_vien_luu(
    IN p_ma_nv VARCHAR(5),
    IN p_dia_chi_cu_the NVARCHAR(255),
    IN p_phuong_xa NVARCHAR(100),
    IN p_quan_huyen NVARCHAR(100),
    IN p_tinh_thanh NVARCHAR(100)
)
BEGIN
    DECLARE v_ma_nv VARCHAR(5);
    DECLARE v_dia_chi_cu_the NVARCHAR(255);
    DECLARE v_phuong_xa NVARCHAR(100);
    DECLARE v_quan_huyen NVARCHAR(100);
    DECLARE v_tinh_thanh NVARCHAR(100);

    SET v_ma_nv = UPPER(TRIM(IFNULL(p_ma_nv, '')));
    SET v_dia_chi_cu_the = TRIM(IFNULL(p_dia_chi_cu_the, N''));
    SET v_phuong_xa = TRIM(IFNULL(p_phuong_xa, N''));
    SET v_quan_huyen = TRIM(IFNULL(p_quan_huyen, N''));
    SET v_tinh_thanh = TRIM(IFNULL(p_tinh_thanh, N''));

    IF BINARY v_ma_nv NOT REGEXP '^NV(00[1-9]|0[1-9][0-9]|[1-9][0-9]{2})$'
       OR NOT EXISTS (SELECT 1 FROM nhan_vien WHERE ma_nv = v_ma_nv) THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'NV_NOT_FOUND';
    END IF;

    IF v_dia_chi_cu_the = N'' OR v_phuong_xa = N'' OR v_quan_huyen = N'' OR v_tinh_thanh = N'' THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'NV_REFERENCE_INVALID';
    END IF;

    INSERT INTO dia_chi_nhan_vien (
        ma_nv, dia_chi_cu_the, phuong_xa, quan_huyen, tinh_thanh
    ) VALUES (
        v_ma_nv, v_dia_chi_cu_the, v_phuong_xa, v_quan_huyen, v_tinh_thanh
    )
    ON DUPLICATE KEY UPDATE
        dia_chi_cu_the = VALUES(dia_chi_cu_the),
        phuong_xa = VALUES(phuong_xa),
        quan_huyen = VALUES(quan_huyen),
        tinh_thanh = VALUES(tinh_thanh);
END//

DELIMITER ;
