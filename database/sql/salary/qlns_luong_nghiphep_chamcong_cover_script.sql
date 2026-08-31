CREATE OR REPLACE FUNCTION fn_thong_bao_tinh_luong(
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

    /*
     * 1. Kiểm tra đầu vào
     */
    IF p_ma_nv IS NULL OR TRIM(p_ma_nv) = '' THEN
        RETURN 'Thiếu mã nhân viên';
    END IF;

    IF p_ky_luong IS NULL THEN
        RETURN 'Thiếu kỳ lương';
    END IF;

    SET v_ky = STR_TO_DATE(
        DATE_FORMAT(p_ky_luong, '%Y-%m-01'),
        '%Y-%m-%d'
    );

    /*
     * 2. Kiểm tra nhân viên
     */
    IF NOT EXISTS (
        SELECT 1
        FROM nhan_vien
        WHERE ma_nv = p_ma_nv
    ) THEN
        RETURN 'Nhân viên không tồn tại';
    END IF;

    /*
     * 3. Kiểm tra thông tin lương của kỳ
     */
    IF NOT EXISTS (
        SELECT 1
        FROM luong
        WHERE ma_nv = p_ma_nv
          AND ky_luong = v_ky
    ) THEN
        RETURN CONCAT(
            'Chưa tạo thông tin lương kỳ ',
            DATE_FORMAT(v_ky, '%m/%Y')
        );
    END IF;

    /*
     * 4. Kiểm tra hợp đồng và lương cơ bản
     */
    IF NOT EXISTS (
        SELECT 1
        FROM hop_dong
        WHERE ma_nv = p_ma_nv
          AND v_ky BETWEEN ngay_ky
                       AND IFNULL(ngay_het_han, v_ky)
          AND IFNULL(luong_co_ban, 0) > 0
    ) THEN
        RETURN 'Chưa có hợp đồng hoặc lương cơ bản hiệu lực';
    END IF;

    /*
     * 5. Kiểm tra hệ số lương
     */
    IF NOT EXISTS (
        SELECT 1
        FROM lich_su_he_so_luong
        WHERE ma_nv = p_ma_nv
          AND v_ky BETWEEN tu_ngay
                       AND IFNULL(den_ngay, v_ky)
          AND IFNULL(he_so_luong, 0) > 0
    ) THEN
        RETURN 'Chưa có hệ số lương hiệu lực';
    END IF;

    /*
     * 6. Kiểm tra dữ liệu chấm công
     */
    IF NOT EXISTS (
        SELECT 1
        FROM cham_cong
        WHERE ma_nv = p_ma_nv
          AND ngay_lam >= v_ky
          AND ngay_lam < DATE_ADD(
              v_ky,
              INTERVAL 1 MONTH
          )
    ) THEN
        RETURN 'Chưa có dữ liệu chấm công trong kỳ';
    END IF;

    /*
     * 7. Kiểm tra ngày công chuẩn
     */
    SET v_so_ngay_cong_chuan =
        fn_so_ngay_cong_chuan(
            p_ma_nv,
            v_ky
        );

    IF IFNULL(v_so_ngay_cong_chuan, 0) = 0 THEN
        RETURN 'Chưa có ngày công hợp lệ trong kỳ';
    END IF;

    /*
     * 8. Kiểm tra ngày công thực tế
     */
    SET v_so_ngay_cong_thuc_te =
        fn_so_ngay_cong_thuc_te(
            p_ma_nv,
            v_ky
        );

    IF IFNULL(v_so_ngay_cong_thuc_te, 0) = 0 THEN
        RETURN 'Số giờ làm chưa đủ để quy đổi ngày công';
    END IF;

    /*
     * Đủ toàn bộ dữ liệu
     */
    RETURN 'Hoàn tất tính lương';
END;


CREATE or REPLACE PROCEDURE sp_luong_tim_kiem_phan_trang (
    IN p_tu_khoa VARCHAR(255),
    IN p_ky_luong DATE,
    IN p_ma_pb INT,
    IN p_ma_cv INT,
    IN p_page INT,
    IN p_per_page INT
)
BEGIN
    DECLARE v_page INT DEFAULT 1;
    DECLARE v_per_page INT DEFAULT 15;
    DECLARE v_offset INT DEFAULT 0;

    SET v_page = IFNULL(p_page, 1);
    SET v_per_page = IFNULL(p_per_page, 15);

    IF v_page < 1 THEN
        SET v_page = 1;
    END IF;

    IF v_per_page < 1 THEN
        SET v_per_page = 15;
    END IF;

    IF v_per_page > 100 THEN
        SET v_per_page = 100;
    END IF;

    SET v_offset = (v_page - 1) * v_per_page;

    SET p_tu_khoa = NULLIF(TRIM(p_tu_khoa), '');

    IF p_ky_luong IS NOT NULL THEN
        SET p_ky_luong = STR_TO_DATE(
            DATE_FORMAT(p_ky_luong, '%Y-%m-01'),
            '%Y-%m-%d'
        );
    END IF;

    SELECT
        rs.*,
        COUNT(*) OVER() AS total_count
    FROM (
        SELECT
            nv.ma_nv,
            nv.ho_ten,
            nv.ngay_sinh,
            nv.gioi_tinh,
            nv.sdt,
            nv.email,
            nv.ngay_vao_lam,
            nv.ma_pb,
            nv.ten_pb,
            nv.ma_cv,
            nv.ten_cv,
            nv.hoc_van,

            l.ma_luong,
            l.ky_luong,
            l.thuong,
            l.phat,
            l.bao_hiem,
            l.thue,

            cv.he_so_phu_cap AS phu_cap,

            CASE
                WHEN l.ma_luong IS NULL THEN 0
                ELSE fn_tinh_luong_thuc_nhan(
                    nv.ma_nv,
                    l.ky_luong
                )
            END AS thuc_nhan,
            

            fn_thong_bao_tinh_luong(
			    nv.ma_nv,
			    COALESCE(
			        l.ky_luong,
			        p_ky_luong
			    )
			) AS thong_bao_tinh_luong,

            
            IFNULL(cc.so_ngay_cham_cong, 0)
                AS so_ngay_cham_cong,

            IFNULL(cc.so_lan_vao_muon, 0)
                AS so_lan_vao_muon,

            IFNULL(cc.so_lan_ve_som, 0)
                AS so_lan_ve_som

        FROM vw_danh_sach_nhan_vien_chi_tiet nv

        INNER JOIN chuc_vu cv
            ON cv.ma_cv = nv.ma_cv

        LEFT JOIN luong l
            ON l.ma_nv = nv.ma_nv
            AND (
                p_ky_luong IS NULL
                OR l.ky_luong = p_ky_luong
            )

        LEFT JOIN (
            SELECT
                ma_nv,

                STR_TO_DATE(
                    DATE_FORMAT(ngay_lam, '%Y-%m-01'),
                    '%Y-%m-%d'
                ) AS ky_luong,

                SUM(
                    CASE
                        WHEN so_gio_lam >= 8 THEN 1
                        WHEN so_gio_lam >= 4 THEN 0.5
                        ELSE 0
                    END
                ) AS so_ngay_cham_cong,

                SUM(
                    CASE
                        WHEN vao_muon = 1 THEN 1
                        ELSE 0
                    END
                ) AS so_lan_vao_muon,

                SUM(
                    CASE
                        WHEN ve_som = 1 THEN 1
                        ELSE 0
                    END
                ) AS so_lan_ve_som

            FROM cham_cong

            GROUP BY
                ma_nv,
                STR_TO_DATE(
                    DATE_FORMAT(ngay_lam, '%Y-%m-01'),
                    '%Y-%m-%d'
                )
        ) cc
            ON cc.ma_nv = nv.ma_nv
            AND cc.ky_luong = l.ky_luong

        WHERE
            (
                p_ma_pb IS NULL
                OR nv.ma_pb = p_ma_pb
            )
            AND (
                p_ma_cv IS NULL
                OR nv.ma_cv = p_ma_cv
            )
            AND (
                p_tu_khoa IS NULL
                OR nv.ma_nv LIKE CONCAT('%', p_tu_khoa, '%')
                OR nv.ho_ten LIKE CONCAT('%', p_tu_khoa, '%')
                OR nv.ten_pb LIKE CONCAT('%', p_tu_khoa, '%')
                OR nv.ten_cv LIKE CONCAT('%', p_tu_khoa, '%')
            )
    ) rs

    ORDER BY
        rs.ky_luong DESC,
        rs.ma_nv ASC

    LIMIT v_per_page OFFSET v_offset;
end;

CREATE OR REPLACE PROCEDURE sp_nhan_vien_danh_sach_phan_trang(
    IN p_tu_khoa VARCHAR(255),
    IN p_ma_pb INT,
    IN p_ma_cv INT,
    IN p_page INT,
    IN p_per_page INT
)
BEGIN
    DECLARE v_page INT DEFAULT 1;
    DECLARE v_per_page INT DEFAULT 15;
    DECLARE v_offset INT DEFAULT 0;

    /*
     * Chuẩn hóa pagination
     */
    SET v_page = IFNULL(p_page, 1);
    SET v_per_page = IFNULL(p_per_page, 15);

    IF v_page < 1 THEN
        SET v_page = 1;
    END IF;

    IF v_per_page < 1 THEN
        SET v_per_page = 15;
    END IF;

    IF v_per_page > 100 THEN
        SET v_per_page = 100;
    END IF;

    SET v_offset = (v_page - 1) * v_per_page;

    /*
     * Chuẩn hóa keyword:
     * '' -> NULL
     */
    SET p_tu_khoa = NULLIF(
        TRIM(p_tu_khoa),
        ''
    );

    SELECT
        nv.*,

        /*
         * Tổng số record sau khi filter,
         * trước khi LIMIT.
         */
        COUNT(*) OVER() AS total_count

    FROM vw_danh_sach_nhan_vien_chi_tiet nv

    WHERE
        /*
         * Filter phòng ban
         */
        (
            p_ma_pb IS NULL
            OR nv.ma_pb = p_ma_pb
        )

        /*
         * Filter chức vụ
         */
        AND (
            p_ma_cv IS NULL
            OR nv.ma_cv = p_ma_cv
        )

        /*
         * Search
         */
        AND (
            p_tu_khoa IS NULL

            OR nv.ma_nv LIKE CONCAT(
                '%',
                p_tu_khoa,
                '%'
            )

            OR nv.ho_ten LIKE CONCAT(
                '%',
                p_tu_khoa,
                '%'
            )

            OR nv.ten_pb LIKE CONCAT(
                '%',
                p_tu_khoa,
                '%'
            )

            OR nv.ten_cv LIKE CONCAT(
                '%',
                p_tu_khoa,
                '%'
            )
        )

    ORDER BY
        nv.ma_nv ASC

    LIMIT v_per_page
    OFFSET v_offset;
END;


CREATE OR REPLACE PROCEDURE sp_cham_cong_nhan_vien_phan_trang(
    IN p_tu_khoa VARCHAR(255),
    IN p_ma_pb INT,
    IN p_thang INT,
    IN p_nam INT,
    IN p_page INT,
    IN p_per_page INT
)
BEGIN
    DECLARE v_page INT DEFAULT 1;
    DECLARE v_per_page INT DEFAULT 15;
    DECLARE v_offset INT DEFAULT 0;
    DECLARE v_tu_ngay DATE;
    DECLARE v_den_ngay DATE;

    SET v_page = IFNULL(p_page, 1);
    SET v_per_page = IFNULL(p_per_page, 15);

    IF v_page < 1 THEN
        SET v_page = 1;
    END IF;

    IF v_per_page < 1 THEN
        SET v_per_page = 15;
    END IF;

    IF v_per_page > 100 THEN
        SET v_per_page = 100;
    END IF;

    SET v_offset =
        (v_page - 1) * v_per_page;

    SET p_tu_khoa =
        NULLIF(TRIM(p_tu_khoa), '');

    SET p_thang =
        IFNULL(p_thang, MONTH(CURDATE()));

    SET p_nam =
        IFNULL(p_nam, YEAR(CURDATE()));

    SET v_tu_ngay =
        STR_TO_DATE(
            CONCAT(
                p_nam,
                '-',
                LPAD(p_thang, 2, '0'),
                '-01'
            ),
            '%Y-%m-%d'
        );

    SET v_den_ngay =
        DATE_ADD(
            v_tu_ngay,
            INTERVAL 1 MONTH
        );

    SELECT
        rs.*,
        COUNT(*) OVER() AS total_count

    FROM (
        SELECT
            nv.ma_nv,
            nv.ho_ten,
            nv.ngay_sinh,
            nv.gioi_tinh,
            nv.sdt,
            nv.email,

            nv.ma_pb,
            nv.ten_pb,

            nv.ma_cv,
            nv.ten_cv,

            IFNULL(
                cc.so_ngay_cham_cong,
                0
            ) AS so_ngay_cham_cong,

            IFNULL(
                cc.so_lan_vao_muon,
                0
            ) AS so_lan_vao_muon,

            IFNULL(
                cc.so_lan_ve_som,
                0
            ) AS so_lan_ve_som

        FROM
            vw_danh_sach_nhan_vien_chi_tiet nv

        LEFT JOIN (
            SELECT
                ma_nv,

                SUM(
                    CASE
                        WHEN so_gio_lam >= 8
                            THEN 1
                        WHEN so_gio_lam >= 4
                            THEN 0.5
                        ELSE 0
                    END
                ) AS so_ngay_cham_cong,

                SUM(
                    CASE
                        WHEN vao_muon = 1
                            THEN 1
                        ELSE 0
                    END
                ) AS so_lan_vao_muon,

                SUM(
                    CASE
                        WHEN ve_som = 1
                            THEN 1
                        ELSE 0
                    END
                ) AS so_lan_ve_som

            FROM cham_cong

            WHERE
                ngay_lam >= v_tu_ngay

                AND ngay_lam <
                    v_den_ngay

            GROUP BY ma_nv
        ) cc
            ON cc.ma_nv = nv.ma_nv

        WHERE
            (
                p_ma_pb IS NULL
                OR nv.ma_pb = p_ma_pb
            )

            AND (
                p_tu_khoa IS NULL

                OR nv.ma_nv LIKE CONCAT(
                    '%',
                    p_tu_khoa,
                    '%'
                )

                OR nv.ho_ten LIKE CONCAT(
                    '%',
                    p_tu_khoa,
                    '%'
                )

                OR nv.ten_pb LIKE CONCAT(
                    '%',
                    p_tu_khoa,
                    '%'
                )

                OR nv.ten_cv LIKE CONCAT(
                    '%',
                    p_tu_khoa,
                    '%'
                )
            )
    ) rs

    ORDER BY
        rs.ma_nv ASC

    LIMIT v_per_page
    OFFSET v_offset;
END;

CREATE OR REPLACE PROCEDURE sp_cham_cong_chi_tiet_phan_trang(
    IN p_ma_nv VARCHAR(5),
    IN p_nam INT,
    IN p_thang INT,
    IN p_page INT,
    IN p_per_page INT
)
BEGIN
    DECLARE v_page INT DEFAULT 1;
    DECLARE v_per_page INT DEFAULT 15;
    DECLARE v_offset INT DEFAULT 0;

    SET v_page =
        IFNULL(p_page, 1);

    SET v_per_page =
        IFNULL(p_per_page, 15);

    IF v_page < 1 THEN
        SET v_page = 1;
    END IF;

    IF v_per_page < 1 THEN
        SET v_per_page = 15;
    END IF;

    IF v_per_page > 100 THEN
        SET v_per_page = 100;
    END IF;

    SET v_offset =
        (v_page - 1) * v_per_page;

    SELECT
        cc.ma_cc,
        cc.ma_nv,
        cc.ngay_lam,
        cc.so_gio_lam,
        cc.vao_muon,
        cc.ve_som,

        CASE
            WHEN cc.so_gio_lam >= 8
                THEN 1
            WHEN cc.so_gio_lam >= 4
                THEN 0.5
            ELSE 0
        END AS ngay_cong,

        COUNT(*) OVER() AS total_count

    FROM cham_cong cc

    WHERE
        cc.ma_nv = p_ma_nv

        AND YEAR(cc.ngay_lam) =
            p_nam

        AND MONTH(cc.ngay_lam) =
            p_thang

    ORDER BY
        cc.ngay_lam ASC

    LIMIT v_per_page
    OFFSET v_offset;
END;


create or REPLACE PROCEDURE sp_nghi_phep_danh_sach_phan_trang(
    IN p_tu_khoa VARCHAR(100),
    IN p_ma_pb INT,
    IN p_ma_lp INT,
    IN p_tu_ngay DATE,
    IN p_den_ngay DATE,
    IN p_tab VARCHAR(20),
    IN p_page INT,
    IN p_per_page INT
)
BEGIN
    DECLARE v_offset INT DEFAULT 0;
    DECLARE v_total INT DEFAULT 0;

    IF p_page IS NULL OR p_page < 1 THEN
        SET p_page = 1;
    END IF;

    IF p_per_page IS NULL OR p_per_page < 1 THEN
        SET p_per_page = 10;
    END IF;

    SET v_offset =
        (p_page - 1) * p_per_page;

    /*
     * Tổng số record theo filter.
     */
    SELECT COUNT(*)
    INTO v_total
    FROM nghi_phep np

    INNER JOIN nhan_vien nv
        ON nv.ma_nv = np.ma_nv

    LEFT JOIN loai_phep lp
        ON lp.ma_lp = np.ma_lp

    WHERE
        (
            p_ma_pb IS NULL
            OR nv.ma_pb = p_ma_pb
        )

        AND (
            p_ma_lp IS NULL
            OR np.ma_lp = p_ma_lp
        )

        AND (
            p_tu_khoa IS NULL
            OR TRIM(p_tu_khoa) = ''
            OR nv.ma_nv LIKE CONCAT(
                '%',
                TRIM(p_tu_khoa),
                '%'
            )
            OR nv.ho_ten LIKE CONCAT(
                '%',
                TRIM(p_tu_khoa),
                '%'
            )
        )

        /*
         * Lọc theo khoảng ngày giao nhau.
         */
        AND (
            p_tu_ngay IS NULL
            OR np.den_ngay >= p_tu_ngay
        )

        AND (
            p_den_ngay IS NULL
            OR np.tu_ngay <= p_den_ngay
        )

        /*
         * Tab.
         */
        AND (
            p_tab IS NULL
            OR p_tab = 'all'

            OR (
                p_tab = 'pending'
                AND np.trang_thai_duyet = 0
            )

            OR (
                p_tab = 'processed'
                AND np.trang_thai_duyet IN (1, 2)
            )
        );

    /*
     * Data phân trang.
     */
    SELECT
        np.ma_np,
        np.ma_nv,

        nv.ho_ten,
        nv.ma_pb,

        np.tu_ngay,
        np.den_ngay,

        DATEDIFF(
            np.den_ngay,
            np.tu_ngay
        ) + 1 AS so_ngay,

        np.ma_lp,
        lp.ten_lp,

        np.ly_do,
        np.trang_thai_duyet,

        CASE np.trang_thai_duyet
            WHEN 0 THEN 'Chờ duyệt'
            WHEN 1 THEN 'Đã duyệt'
            WHEN 2 THEN 'Từ chối'
            ELSE 'Không xác định'
        END AS ten_trang_thai,

        v_total AS total_count

    FROM nghi_phep np

    INNER JOIN nhan_vien nv
        ON nv.ma_nv = np.ma_nv

    LEFT JOIN loai_phep lp
        ON lp.ma_lp = np.ma_lp

    WHERE
        (
            p_ma_pb IS NULL
            OR nv.ma_pb = p_ma_pb
        )

        AND (
            p_ma_lp IS NULL
            OR np.ma_lp = p_ma_lp
        )

        AND (
            p_tu_khoa IS NULL
            OR TRIM(p_tu_khoa) = ''
            OR nv.ma_nv LIKE CONCAT(
                '%',
                TRIM(p_tu_khoa),
                '%'
            )
            OR nv.ho_ten LIKE CONCAT(
                '%',
                TRIM(p_tu_khoa),
                '%'
            )
        )

        AND (
            p_tu_ngay IS NULL
            OR np.den_ngay >= p_tu_ngay
        )

        AND (
            p_den_ngay IS NULL
            OR np.tu_ngay <= p_den_ngay
        )

        AND (
            p_tab IS NULL
            OR p_tab = 'all'

            OR (
                p_tab = 'pending'
                AND np.trang_thai_duyet = 0
            )

            OR (
                p_tab = 'processed'
                AND np.trang_thai_duyet IN (1, 2)
            )
        )

    ORDER BY
        np.ma_np DESC

    LIMIT p_per_page
    OFFSET v_offset;

END;