-- Canonical salary compatibility functions for the active 15-table contract.
-- Run after tao_bang.sql, du_lieu_mau.sql and quyen_vai_tro.sql.
--
-- This focused source contains functions only. It intentionally creates no
-- view or procedure, so the active salary repository can use the same
-- 15-table schema on a fresh disposable database and on an approved existing
-- database. Routine DDL implicitly commits in MariaDB/MySQL; verify the
-- target and take an approved backup before replacing routines on an existing
-- database.

USE quan_ly_nhan_su;

DELIMITER //

DROP FUNCTION IF EXISTS fn_so_ngay_cong_chuan//

CREATE FUNCTION fn_so_ngay_cong_chuan(
    p_ma_nv VARCHAR(5),
    p_ky_luong DATE
)
RETURNS INT
DETERMINISTIC
BEGIN
    DECLARE v_ky DATE;
    DECLARE v_ngay_dau_thang DATE;
    DECLARE v_ngay_cuoi_thang DATE;
    DECLARE v_so_ngay_cong_chuan INT DEFAULT 0;

    IF p_ma_nv IS NULL OR TRIM(p_ma_nv) = '' OR p_ky_luong IS NULL THEN
        RETURN 0;
    END IF;

    SET v_ky = DATE_FORMAT(p_ky_luong, '%Y-%m-01');
    SET v_ngay_dau_thang = v_ky;
    SET v_ngay_cuoi_thang = LAST_DAY(v_ky);

    SELECT COUNT(*) INTO v_so_ngay_cong_chuan
    FROM cham_cong
    WHERE ma_nv = p_ma_nv
      AND ngay_lam BETWEEN v_ngay_dau_thang AND v_ngay_cuoi_thang
      AND so_gio_lam > 0;

    RETURN IFNULL(v_so_ngay_cong_chuan, 0);
END//

DROP FUNCTION IF EXISTS fn_so_ngay_cong_thuc_te//

CREATE FUNCTION fn_so_ngay_cong_thuc_te(
    p_ma_nv VARCHAR(5),
    p_ky_luong DATE
)
RETURNS DECIMAL(10,2)
DETERMINISTIC
BEGIN
    DECLARE v_ky DATE;
    DECLARE v_ngay_dau_thang DATE;
    DECLARE v_ngay_cuoi_thang DATE;
    DECLARE v_so_ngay_cong_thuc_te DECIMAL(10,2) DEFAULT 0;

    IF p_ma_nv IS NULL OR TRIM(p_ma_nv) = '' OR p_ky_luong IS NULL THEN
        RETURN 0;
    END IF;

    SET v_ky = DATE_FORMAT(p_ky_luong, '%Y-%m-01');
    SET v_ngay_dau_thang = v_ky;
    SET v_ngay_cuoi_thang = LAST_DAY(v_ky);

    SELECT SUM(
        CASE
            WHEN so_gio_lam >= 8 THEN 1.0
            WHEN so_gio_lam >= 4 THEN 0.5
            ELSE 0
        END
    ) INTO v_so_ngay_cong_thuc_te
    FROM cham_cong
    WHERE ma_nv = p_ma_nv
      AND ngay_lam BETWEEN v_ngay_dau_thang AND v_ngay_cuoi_thang;

    RETURN IFNULL(v_so_ngay_cong_thuc_te, 0);
END//

DROP FUNCTION IF EXISTS fn_tinh_luong_thuc_nhan//

CREATE FUNCTION fn_tinh_luong_thuc_nhan(
    p_ma_nv VARCHAR(5),
    p_ky_luong DATE
)
RETURNS DECIMAL(18,0)
DETERMINISTIC
BEGIN
    DECLARE v_luong_co_ban DECIMAL(18,2);
    DECLARE v_ky DATE;
    DECLARE v_he_so_luong DECIMAL(18,2);
    DECLARE v_so_ngay_cong_chuan INT DEFAULT 0;
    DECLARE v_so_ngay_cong_thuc_te DECIMAL(10,2) DEFAULT 0;
    DECLARE v_phu_cap_chuc_vu DECIMAL(18,2) DEFAULT 0;
    DECLARE v_thuong DECIMAL(18,2) DEFAULT 0;
    DECLARE v_phat DECIMAL(18,2) DEFAULT 0;
    DECLARE v_bao_hiem DECIMAL(18,2) DEFAULT 0;
    DECLARE v_thue DECIMAL(18,2) DEFAULT 0;
    DECLARE v_luong_theo_ngay_cong DECIMAL(18,2) DEFAULT 0;
    DECLARE v_thuc_nhan DECIMAL(18,0) DEFAULT 0;

    IF p_ma_nv IS NULL OR TRIM(p_ma_nv) = '' OR p_ky_luong IS NULL THEN
        RETURN 0;
    END IF;

    SET v_ky = DATE_FORMAT(p_ky_luong, '%Y-%m-01');

    -- Pick the latest effective contract, matching the historical salary rule.
    SELECT (
        SELECT hd.luong_co_ban
        FROM hop_dong AS hd
        WHERE hd.ma_nv = p_ma_nv
          AND p_ky_luong BETWEEN hd.ngay_ky AND IFNULL(hd.ngay_het_han, p_ky_luong)
        ORDER BY hd.ngay_ky DESC
        LIMIT 1
    ) INTO v_luong_co_ban;

    -- Pick the latest effective coefficient; the active schema stores den_ngay
    -- as NOT NULL, but IFNULL keeps this function safe for compatible fixtures.
    SELECT (
        SELECT ls.he_so_luong
        FROM lich_su_he_so_luong AS ls
        WHERE ls.ma_nv = p_ma_nv
          AND p_ky_luong BETWEEN ls.tu_ngay AND IFNULL(ls.den_ngay, p_ky_luong)
        ORDER BY ls.tu_ngay DESC
        LIMIT 1
    ) INTO v_he_so_luong;

    IF v_luong_co_ban IS NULL OR v_he_so_luong IS NULL THEN
        RETURN 0;
    END IF;

    SET v_so_ngay_cong_chuan = fn_so_ngay_cong_chuan(p_ma_nv, v_ky);
    SET v_so_ngay_cong_thuc_te = fn_so_ngay_cong_thuc_te(p_ma_nv, v_ky);

    IF v_so_ngay_cong_chuan IS NULL OR v_so_ngay_cong_chuan = 0 THEN
        RETURN 0;
    END IF;

    SELECT COALESCE((
        SELECT cv.he_so_phu_cap
        FROM nhan_vien AS nv
        INNER JOIN chuc_vu AS cv ON cv.ma_cv = nv.ma_cv
        WHERE nv.ma_nv = p_ma_nv
        LIMIT 1
    ), 0) INTO v_phu_cap_chuc_vu;

    SELECT
        COALESCE((SELECT l.thuong FROM luong AS l WHERE l.ma_nv = p_ma_nv AND l.ky_luong = v_ky LIMIT 1), 0),
        COALESCE((SELECT l.phat FROM luong AS l WHERE l.ma_nv = p_ma_nv AND l.ky_luong = v_ky LIMIT 1), 0),
        COALESCE((SELECT l.bao_hiem FROM luong AS l WHERE l.ma_nv = p_ma_nv AND l.ky_luong = v_ky LIMIT 1), 0),
        COALESCE((SELECT l.thue FROM luong AS l WHERE l.ma_nv = p_ma_nv AND l.ky_luong = v_ky LIMIT 1), 0)
    INTO v_thuong, v_phat, v_bao_hiem, v_thue;

    SET v_luong_theo_ngay_cong =
        (v_luong_co_ban / v_so_ngay_cong_chuan)
        * v_so_ngay_cong_thuc_te
        * v_he_so_luong;

    SET v_thuc_nhan =
        v_luong_theo_ngay_cong
        + v_phu_cap_chuc_vu * v_luong_co_ban
        + v_thuong
        - v_phat
        - v_thue
        - v_bao_hiem;

    RETURN v_thuc_nhan;
END//

DROP FUNCTION IF EXISTS fn_thong_bao_tinh_luong//

CREATE FUNCTION fn_thong_bao_tinh_luong(
    p_ma_nv VARCHAR(5),
    p_ky_luong DATE
)
RETURNS VARCHAR(255)
NOT DETERMINISTIC
READS SQL DATA
BEGIN
    DECLARE v_ky DATE;
    DECLARE v_so_ngay_cong_chuan INT DEFAULT 0;
    DECLARE v_so_ngay_cong_thuc_te DECIMAL(10,2) DEFAULT 0;

    IF p_ma_nv IS NULL OR TRIM(p_ma_nv) = '' THEN
        RETURN 'Thiếu mã nhân viên';
    END IF;

    IF p_ky_luong IS NULL THEN
        RETURN 'Thiếu kỳ lương';
    END IF;

    SET v_ky = DATE_FORMAT(p_ky_luong, '%Y-%m-01');

    IF NOT EXISTS (
        SELECT 1 FROM nhan_vien WHERE ma_nv = p_ma_nv
    ) THEN
        RETURN 'Nhân viên không tồn tại';
    END IF;

    IF NOT EXISTS (
        SELECT 1 FROM luong WHERE ma_nv = p_ma_nv AND ky_luong = v_ky
    ) THEN
        RETURN CONCAT('Chưa tạo thông tin lương kỳ ', DATE_FORMAT(v_ky, '%m/%Y'));
    END IF;

    IF NOT EXISTS (
        SELECT 1
        FROM hop_dong
        WHERE ma_nv = p_ma_nv
          AND v_ky BETWEEN ngay_ky AND IFNULL(ngay_het_han, v_ky)
          AND luong_co_ban > 0
    ) THEN
        RETURN 'Chưa có hợp đồng hoặc lương cơ bản hiệu lực';
    END IF;

    IF NOT EXISTS (
        SELECT 1
        FROM lich_su_he_so_luong
        WHERE ma_nv = p_ma_nv
          AND v_ky BETWEEN tu_ngay AND IFNULL(den_ngay, v_ky)
          AND he_so_luong > 0
    ) THEN
        RETURN 'Chưa có hệ số lương hiệu lực';
    END IF;

    IF NOT EXISTS (
        SELECT 1
        FROM cham_cong
        WHERE ma_nv = p_ma_nv
          AND ngay_lam >= v_ky
          AND ngay_lam < DATE_ADD(v_ky, INTERVAL 1 MONTH)
    ) THEN
        RETURN 'Chưa có dữ liệu chấm công trong kỳ';
    END IF;

    SET v_so_ngay_cong_chuan = fn_so_ngay_cong_chuan(p_ma_nv, v_ky);
    IF IFNULL(v_so_ngay_cong_chuan, 0) = 0 THEN
        RETURN 'Chưa có ngày công hợp lệ trong kỳ';
    END IF;

    SET v_so_ngay_cong_thuc_te = fn_so_ngay_cong_thuc_te(p_ma_nv, v_ky);
    IF IFNULL(v_so_ngay_cong_thuc_te, 0) = 0 THEN
        RETURN 'Số giờ làm chưa đủ để quy đổi ngày công';
    END IF;

    RETURN 'Hoàn tất tính lương';
END//

DELIMITER ;
