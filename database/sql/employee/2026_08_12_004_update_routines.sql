-- LEGACY EMPLOYEE ROUTINES (superseded 2026-08-24). Historical audit only;
-- do not source as active setup. Current code uses direct Query Builder.

DELIMITER //

DROP PROCEDURE IF EXISTS sp_nhan_vien_sua//
CREATE PROCEDURE sp_nhan_vien_sua(
    IN p_ma_nv VARCHAR(5),
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
    IN p_ma_tt TINYINT
)
BEGIN
    DECLARE v_ma_nv VARCHAR(5);
    DECLARE v_ho_ten NVARCHAR(50);
    DECLARE v_sdt VARCHAR(15);
    DECLARE v_email NVARCHAR(100);
    DECLARE v_dan_toc NVARCHAR(50);
    DECLARE v_cccd VARCHAR(12);
    DECLARE v_noi_cap_cccd NVARCHAR(50);
    DECLARE v_hoc_van NVARCHAR(50);
    DECLARE v_ngay_nghi_viec DATE;
    DECLARE v_ky_hieu_vai_tro VARCHAR(50);
    DECLARE v_ky_hieu_trang_thai VARCHAR(20);
    DECLARE v_ky_hieu_trang_thai_moi VARCHAR(20);
    DECLARE v_tim_thay TINYINT DEFAULT 1;
    DECLARE CONTINUE HANDLER FOR NOT FOUND SET v_tim_thay = 0;

    SET v_ma_nv = UPPER(TRIM(IFNULL(p_ma_nv, '')));

    # The caller owns the surrounding transaction; this routine only validates and mutates within it.
    # Lock the target before the exact-role guard so privileged-target precedence is stable under
    # concurrency and cannot become a validation/reference enumeration oracle.
    SELECT nv.ngay_nghi_viec, vt.ky_hieu, tt.ky_hieu
    INTO v_ngay_nghi_viec, v_ky_hieu_vai_tro, v_ky_hieu_trang_thai
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

    SET v_ho_ten = TRIM(IFNULL(p_ho_ten, N''));
    SET v_sdt = TRIM(IFNULL(p_sdt, ''));
    SET v_email = LOWER(TRIM(IFNULL(p_email, N'')));
    SET v_dan_toc = TRIM(IFNULL(p_dan_toc, N''));
    SET v_cccd = TRIM(IFNULL(p_cccd, ''));
    SET v_noi_cap_cccd = TRIM(IFNULL(p_noi_cap_cccd, N''));
    SET v_hoc_van = TRIM(IFNULL(p_hoc_van, N''));

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
       OR v_hoc_van = N'' THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'NV_REFERENCE_INVALID';
    END IF;

    IF NOT EXISTS (SELECT 1 FROM phong_ban WHERE ma_pb = p_ma_pb)
       OR NOT EXISTS (SELECT 1 FROM chuc_vu WHERE ma_cv = p_ma_cv) THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'NV_REFERENCE_INVALID';
    END IF;

    SET v_ky_hieu_trang_thai_moi = NULL;
    SELECT ky_hieu
    INTO v_ky_hieu_trang_thai_moi
    FROM trang_thai_lam_viec
    WHERE ma_tt = p_ma_tt;

    IF v_ky_hieu_trang_thai IS NULL
       OR v_ky_hieu_trang_thai_moi IS NULL
       OR (
            BINARY v_ky_hieu_trang_thai IN (BINARY 'DANG_LAM', BINARY 'THU_VIEC')
            AND (
                v_ngay_nghi_viec IS NOT NULL
                OR BINARY v_ky_hieu_trang_thai_moi NOT IN (BINARY 'DANG_LAM', BINARY 'THU_VIEC')
            )
       )
       OR (
            BINARY v_ky_hieu_trang_thai = BINARY 'DA_NGHI'
            AND (
                v_ngay_nghi_viec IS NULL
                OR BINARY v_ky_hieu_trang_thai_moi <> BINARY 'DA_NGHI'
            )
       )
       OR BINARY v_ky_hieu_trang_thai NOT IN (BINARY 'DANG_LAM', BINARY 'THU_VIEC', BINARY 'DA_NGHI') THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'NV_STATUS_MISSING';
    END IF;

    IF EXISTS (
        SELECT 1 FROM nhan_vien
        WHERE LOWER(TRIM(email)) = v_email AND BINARY ma_nv <> BINARY v_ma_nv
    ) THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'NV_EMAIL_DUPLICATE';
    END IF;

    IF EXISTS (
        SELECT 1 FROM nhan_vien
        WHERE TRIM(cccd) = v_cccd AND BINARY ma_nv <> BINARY v_ma_nv
    ) THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'NV_CCCD_DUPLICATE';
    END IF;

    UPDATE nhan_vien
    SET ho_ten = v_ho_ten,
        ngay_sinh = p_ngay_sinh,
        gioi_tinh = p_gioi_tinh,
        sdt = v_sdt,
        email = v_email,
        ngay_vao_lam = p_ngay_vao_lam,
        ma_pb = p_ma_pb,
        ma_cv = p_ma_cv,
        dan_toc = v_dan_toc,
        cccd = v_cccd,
        noi_cap_cccd = v_noi_cap_cccd,
        hoc_van = v_hoc_van,
        ma_tt = p_ma_tt
    WHERE BINARY ma_nv = BINARY v_ma_nv;
END//

DROP PROCEDURE IF EXISTS sp_nhan_vien_cap_nhat_anh//
CREATE PROCEDURE sp_nhan_vien_cap_nhat_anh(
    IN p_ma_nv VARCHAR(5),
    IN p_anh_moi VARCHAR(255),
    OUT p_anh_cu VARCHAR(255)
)
BEGIN
    DECLARE v_ma_nv VARCHAR(5);
    DECLARE v_anh_cu VARCHAR(255);
    DECLARE v_ky_hieu_vai_tro VARCHAR(50);
    DECLARE v_tim_thay TINYINT DEFAULT 1;
    DECLARE CONTINUE HANDLER FOR NOT FOUND SET v_tim_thay = 0;

    SET p_anh_cu = NULL;
    SET v_ma_nv = UPPER(TRIM(IFNULL(p_ma_nv, '')));

    # The caller owns the surrounding transaction. The row lock precedes the exact-role guard so
    # target precedence remains stable under concurrent changes. This routine returns only the old
    # path; PHP owns filesystem ownership checks and upload compensation/cleanup.
    SELECT nv.anh_dai_dien, vt.ky_hieu
    INTO v_anh_cu, v_ky_hieu_vai_tro
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

    IF p_anh_moi IS NOT NULL
       AND (
            p_anh_moi = ''
            OR BINARY p_anh_moi <> BINARY TRIM(p_anh_moi)
            OR OCTET_LENGTH(p_anh_moi) > 255
       ) THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'NV_REFERENCE_INVALID';
    END IF;

    SET p_anh_cu = v_anh_cu;

    UPDATE nhan_vien
    SET anh_dai_dien = p_anh_moi
    WHERE BINARY ma_nv = BINARY v_ma_nv;
END//

DELIMITER ;
