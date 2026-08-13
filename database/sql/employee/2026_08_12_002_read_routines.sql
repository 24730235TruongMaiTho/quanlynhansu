DELIMITER //

DROP PROCEDURE IF EXISTS sp_nhan_vien_tim_kiem//
DROP PROCEDURE IF EXISTS sp_nhan_vien_danh_sach//

DROP PROCEDURE IF EXISTS sp_phong_ban_danh_sach//
CREATE PROCEDURE sp_phong_ban_danh_sach()
BEGIN
    SELECT pb.ma_pb,
        pb.ten_pb,
        fn_dem_nhan_vien_theo_phong_ban(pb.ma_pb) AS so_nhan_vien
    FROM phong_ban pb
    ORDER BY pb.ma_pb;
END//

DROP PROCEDURE IF EXISTS sp_chuc_vu_danh_sach//
CREATE PROCEDURE sp_chuc_vu_danh_sach()
BEGIN
    SELECT cv.ma_cv,
        cv.ten_cv,
        cv.he_so_phu_cap
    FROM chuc_vu cv
    ORDER BY cv.ma_cv;
END//

DROP PROCEDURE IF EXISTS sp_vai_tro_danh_sach//
CREATE PROCEDURE sp_vai_tro_danh_sach()
BEGIN
    SELECT vt.ma_vt,
        vt.ten_vt,
        vt.mo_ta
    FROM vai_tro vt
    ORDER BY vt.ten_vt, vt.ma_vt;
END//

DROP PROCEDURE IF EXISTS sp_trang_thai_lam_viec_danh_sach//
CREATE PROCEDURE sp_trang_thai_lam_viec_danh_sach()
BEGIN
    SELECT ttlv.ma_tt,
        ttlv.ky_hieu,
        ttlv.ten_tt
    FROM trang_thai_lam_viec ttlv
    ORDER BY ttlv.ma_tt;
END//

DROP PROCEDURE IF EXISTS sp_nhan_vien_danh_sach_phan_trang//
CREATE PROCEDURE sp_nhan_vien_danh_sach_phan_trang(
    IN p_tu_khoa NVARCHAR(100),
    IN p_ma_pb INT,
    IN p_ma_cv INT,
    IN p_ma_tt TINYINT,
    IN p_trang INT,
    IN p_so_dong INT,
    OUT p_tong_so BIGINT
)
BEGIN
    DECLARE v_tu_khoa NVARCHAR(100);
    DECLARE v_vi_tri BIGINT;

    IF p_trang IS NULL OR p_trang < 1
       OR p_so_dong IS NULL OR p_so_dong < 1 OR p_so_dong > 100 THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'NV_PAGINATION_INVALID';
    END IF;

    SET v_tu_khoa = TRIM(IFNULL(p_tu_khoa, N''));
    SET v_vi_tri = (p_trang - 1) * p_so_dong;

    SELECT COUNT(*)
    INTO p_tong_so
    FROM vw_danh_sach_nhan_vien_chi_tiet nv
    WHERE (
            v_tu_khoa = N''
            OR nv.ma_nv LIKE CONCAT('%', v_tu_khoa, '%')
            OR nv.ho_ten LIKE CONCAT('%', v_tu_khoa, '%')
            OR nv.sdt LIKE CONCAT('%', v_tu_khoa, '%')
            OR nv.email LIKE CONCAT('%', v_tu_khoa, '%')
            OR nv.cccd LIKE CONCAT('%', v_tu_khoa, '%')
            OR nv.ten_pb LIKE CONCAT('%', v_tu_khoa, '%')
            OR nv.ten_cv LIKE CONCAT('%', v_tu_khoa, '%')
        )
      AND (p_ma_pb IS NULL OR nv.ma_pb = p_ma_pb)
      AND (p_ma_cv IS NULL OR nv.ma_cv = p_ma_cv)
      AND (p_ma_tt IS NULL OR nv.ma_tt = p_ma_tt);

    SELECT nv.ma_nv,
        nv.ho_ten,
        nv.sdt,
        nv.email,
        nv.ngay_vao_lam,
        nv.anh_dai_dien,
        nv.ma_pb,
        nv.ten_pb,
        nv.ma_cv,
        nv.ten_cv,
        nv.ma_tt,
        nv.ky_hieu,
        nv.ten_tt
    FROM vw_danh_sach_nhan_vien_chi_tiet nv
    WHERE (
            v_tu_khoa = N''
            OR nv.ma_nv LIKE CONCAT('%', v_tu_khoa, '%')
            OR nv.ho_ten LIKE CONCAT('%', v_tu_khoa, '%')
            OR nv.sdt LIKE CONCAT('%', v_tu_khoa, '%')
            OR nv.email LIKE CONCAT('%', v_tu_khoa, '%')
            OR nv.cccd LIKE CONCAT('%', v_tu_khoa, '%')
            OR nv.ten_pb LIKE CONCAT('%', v_tu_khoa, '%')
            OR nv.ten_cv LIKE CONCAT('%', v_tu_khoa, '%')
        )
      AND (p_ma_pb IS NULL OR nv.ma_pb = p_ma_pb)
      AND (p_ma_cv IS NULL OR nv.ma_cv = p_ma_cv)
      AND (p_ma_tt IS NULL OR nv.ma_tt = p_ma_tt)
    ORDER BY nv.ma_nv ASC
    LIMIT v_vi_tri, p_so_dong;
END//

DROP PROCEDURE IF EXISTS sp_nhan_vien_chi_tiet//
CREATE PROCEDURE sp_nhan_vien_chi_tiet(
    IN p_ma_nv VARCHAR(5)
)
BEGIN
    SELECT nv.ma_nv,
        nv.ho_ten,
        nv.ngay_sinh,
        nv.gioi_tinh,
        nv.sdt,
        nv.email,
        nv.ngay_vao_lam,
        nv.ma_pb,
        pb.ten_pb,
        nv.ma_cv,
        cv.ten_cv,
        nv.dan_toc,
        nv.cccd,
        nv.noi_cap_cccd,
        nv.hoc_van,
        nv.ma_tt,
        ttlv.ky_hieu,
        ttlv.ten_tt,
        nv.ngay_nghi_viec,
        nv.ma_vt,
        vt.ky_hieu AS ky_hieu_vai_tro,
        vt.ten_vt,
        nv.anh_dai_dien,
        dc.dia_chi_cu_the,
        dc.phuong_xa,
        dc.quan_huyen,
        dc.tinh_thanh
    FROM nhan_vien nv
    JOIN phong_ban pb ON pb.ma_pb = nv.ma_pb
    JOIN chuc_vu cv ON cv.ma_cv = nv.ma_cv
    JOIN trang_thai_lam_viec ttlv ON ttlv.ma_tt = nv.ma_tt
    JOIN vai_tro vt ON vt.ma_vt = nv.ma_vt
    LEFT JOIN dia_chi_nhan_vien dc ON dc.ma_nv = nv.ma_nv
    WHERE nv.ma_nv = p_ma_nv;
END//

DROP PROCEDURE IF EXISTS sp_cham_cong_nhan_vien_phan_trang//
CREATE PROCEDURE sp_cham_cong_nhan_vien_phan_trang(
    IN p_tu_khoa NVARCHAR(100),
    IN p_ma_pb INT,
    IN p_thang INT,
    IN p_nam INT,
    IN p_trang INT,
    IN p_so_dong INT,
    OUT p_tong_so BIGINT
)
BEGIN
    DECLARE v_tu_khoa NVARCHAR(100);
    DECLARE v_vi_tri BIGINT;

    IF p_thang IS NULL OR p_thang < 1 OR p_thang > 12
       OR p_nam IS NULL OR p_nam < 2000 OR p_nam > 2100
       OR p_trang IS NULL OR p_trang < 1
       OR p_so_dong IS NULL OR p_so_dong < 1 OR p_so_dong > 100 THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'NV_PAGINATION_INVALID';
    END IF;

    SET v_tu_khoa = TRIM(IFNULL(p_tu_khoa, N''));
    SET v_vi_tri = (p_trang - 1) * p_so_dong;

    SELECT COUNT(*)
    INTO p_tong_so
    FROM nhan_vien nv
    JOIN phong_ban pb ON pb.ma_pb = nv.ma_pb
    JOIN chuc_vu cv ON cv.ma_cv = nv.ma_cv
    WHERE (
            v_tu_khoa = N''
            OR nv.ma_nv LIKE CONCAT('%', v_tu_khoa, '%')
            OR nv.ho_ten LIKE CONCAT('%', v_tu_khoa, '%')
            OR nv.sdt LIKE CONCAT('%', v_tu_khoa, '%')
            OR nv.email LIKE CONCAT('%', v_tu_khoa, '%')
            OR pb.ten_pb LIKE CONCAT('%', v_tu_khoa, '%')
            OR cv.ten_cv LIKE CONCAT('%', v_tu_khoa, '%')
        )
      AND (p_ma_pb IS NULL OR nv.ma_pb = p_ma_pb);

    SELECT nv.ma_nv,
        nv.ho_ten,
        nv.gioi_tinh,
        nv.sdt,
        nv.email,
        nv.ma_pb,
        pb.ten_pb,
        nv.ma_cv,
        cv.ten_cv,
        COALESCE(cc.so_lan_vao_muon, 0) AS so_lan_vao_muon,
        COALESCE(cc.so_lan_ve_som, 0) AS so_lan_ve_som,
        COALESCE(cc.so_ngay_cham_cong, 0) AS so_ngay_cham_cong
    FROM nhan_vien nv
    JOIN phong_ban pb ON pb.ma_pb = nv.ma_pb
    JOIN chuc_vu cv ON cv.ma_cv = nv.ma_cv
    LEFT JOIN (
        SELECT c.ma_nv,
            SUM(CASE WHEN c.vao_muon = b'1' THEN 1 ELSE 0 END) AS so_lan_vao_muon,
            SUM(CASE WHEN c.ve_som = b'1' THEN 1 ELSE 0 END) AS so_lan_ve_som,
            SUM(
                CASE
                    WHEN c.so_gio_lam >= 8 THEN 1
                    WHEN c.so_gio_lam >= 4 THEN 0.5
                    ELSE 0
                END
            ) AS so_ngay_cham_cong
        FROM cham_cong c
        WHERE MONTH(c.ngay_lam) = p_thang
          AND YEAR(c.ngay_lam) = p_nam
        GROUP BY c.ma_nv
    ) cc ON cc.ma_nv = nv.ma_nv
    WHERE (
            v_tu_khoa = N''
            OR nv.ma_nv LIKE CONCAT('%', v_tu_khoa, '%')
            OR nv.ho_ten LIKE CONCAT('%', v_tu_khoa, '%')
            OR nv.sdt LIKE CONCAT('%', v_tu_khoa, '%')
            OR nv.email LIKE CONCAT('%', v_tu_khoa, '%')
            OR pb.ten_pb LIKE CONCAT('%', v_tu_khoa, '%')
            OR cv.ten_cv LIKE CONCAT('%', v_tu_khoa, '%')
        )
      AND (p_ma_pb IS NULL OR nv.ma_pb = p_ma_pb)
    ORDER BY nv.ma_nv ASC
    LIMIT v_vi_tri, p_so_dong;
END//

DELIMITER ;
