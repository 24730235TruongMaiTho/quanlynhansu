<?php

namespace App\Repositories;

use App\Models\Luong;
use Carbon\Carbon;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

class LuongRepository
{
    public function paginateForEmployee(string $maNv, array $filters = []): LengthAwarePaginator
    {
        $page = max((int) ($filters['page'] ?? 1), 1);
        $requestedPerPage = (int) ($filters['per_page'] ?? 10);
        $perPage = in_array($requestedPerPage, [10, 20, 50], true)
            ? $requestedPerPage
            : 10;
        $period = $this->nullIfEmpty($filters['ky_luong'] ?? null);

        return DB::table('luong as l')
            ->where('l.ma_nv', '=', $maNv)
            ->when($period !== null, fn ($query) => $query->where('l.ky_luong', '=', $period))
            ->select([
                'l.ma_luong',
                'l.ma_nv',
                'l.ky_luong',
                'l.thuong',
                'l.phat',
                'l.bao_hiem',
                'l.thue',
            ])
            ->selectRaw('fn_so_ngay_cong_chuan(l.ma_nv, l.ky_luong) AS so_ngay_cong_chuan')
            ->selectRaw('fn_so_ngay_cong_thuc_te(l.ma_nv, l.ky_luong) AS so_ngay_cong_thuc_te')
            ->selectRaw('fn_tinh_luong_thuc_nhan(l.ma_nv, l.ky_luong) AS thuc_nhan')
            ->selectRaw('fn_thong_bao_tinh_luong(l.ma_nv, l.ky_luong) AS thong_bao_tinh_luong')
            ->orderByDesc('l.ky_luong')
            ->orderByDesc('l.ma_luong')
            ->paginate($perPage, ['*'], 'page', $page)
            ->withQueryString();
    }

    /**
     * Query Builder replacement for:
     * sp_luong_tim_kiem_phan_trang
     *
     * Không sử dụng:
     * - stored procedure
     * - vw_danh_sach_nhan_vien_chi_tiet
     *
     * Query trực tiếp:
     * nhan_vien
     * -> phong_ban
     * -> chuc_vu
     * -> luong
     * -> aggregate cham_cong
     */
    public function all(array $filters = []): LengthAwarePaginator
    {
        /*
         * 1. Normalize pagination.
         */
        $page = max(
            (int) ($filters['page'] ?? 1),
            1
        );

        $candidate = (int) ($filters['per_page'] ?? 10);
        $perPage = in_array($candidate, [10, 20, 50], true)
            ? $candidate
            : 10;

        /*
         * SP cũ dùng p_tu_khoa.
         *
         * Repository cũ truyền ma_nv vào p_tu_khoa,
         * nên support cả hai để giữ backward compatibility.
         */
        $maNhanVien = $this->nullIfEmpty($filters['ma_nv'] ?? null);
        $tuKhoa = $this->nullIfEmpty($filters['tu_khoa'] ?? null);

        if ($tuKhoa !== null) {
            $tuKhoa = trim(
                (string) $tuKhoa
            );

            if ($tuKhoa === '') {
                $tuKhoa = null;
            }
        }

        /*
         * 2. Normalize kỳ lương về ngày đầu tháng.
         *
         * 2026-08-27 -> 2026-08-01
         */
        $kyLuong = $this->nullIfEmpty(
            $filters['ky_luong'] ?? null
        );

        if ($kyLuong !== null) {
            $kyLuong = Carbon::parse(
                $kyLuong
            )
                ->startOfMonth()
                ->toDateString();
        }

        $maPhongBan = $this->nullableInteger(
            $filters['ma_pb'] ?? null
        );

        $maChucVu = $this->nullableInteger(
            $filters['ma_cv'] ?? null
        );

        /*
         * 3. Aggregate chấm công theo nhân viên + tháng.
         *
         * Tương đương subquery `cc` của Stored Procedure.
         */
        $attendanceQuery = DB::table(
            'cham_cong as cc_src'
        )
            ->select([
                'cc_src.ma_nv',
            ])
            ->selectRaw(
                "DATE_FORMAT(
                    cc_src.ngay_lam,
                    '%Y-%m-01'
                ) AS ky_luong"
            )
            ->selectRaw(
                'SUM(
                    CASE
                        WHEN cc_src.so_gio_lam >= 8 THEN 1
                        WHEN cc_src.so_gio_lam >= 4 THEN 0.5
                        ELSE 0
                    END
                ) AS so_ngay_cham_cong'
            )
            ->selectRaw(
                'SUM(
                    CASE
                        WHEN cc_src.vao_muon = 1 THEN 1
                        ELSE 0
                    END
                ) AS so_lan_vao_muon'
            )
            ->selectRaw(
                'SUM(
                    CASE
                        WHEN cc_src.ve_som = 1 THEN 1
                        ELSE 0
                    END
                ) AS so_lan_ve_som'
            );

        /*
         * Nếu có kỳ lương thì chỉ aggregate đúng tháng đó.
         * Giảm lượng dữ liệu phải GROUP BY.
         */
        if ($kyLuong !== null) {
            $attendanceQuery
                ->whereDate(
                    'cc_src.ngay_lam',
                    '>=',
                    $kyLuong
                )
                ->whereDate(
                    'cc_src.ngay_lam',
                    '<',
                    Carbon::parse(
                        $kyLuong
                    )
                        ->addMonth()
                        ->toDateString()
                );
        }

        $attendanceQuery
            ->groupBy(
                'cc_src.ma_nv'
            )
            ->groupByRaw(
                "DATE_FORMAT(
                    cc_src.ngay_lam,
                    '%Y-%m-01'
                )"
            );

        /*
         * 4. Main query.
         *
         * Thay thế hoàn toàn:
         * vw_danh_sach_nhan_vien_chi_tiet
         *
         * View cũ lấy:
         * - nhân viên từ nhan_vien
         * - tên phòng ban từ phong_ban
         * - tên chức vụ / hệ số phụ cấp từ chuc_vu
         *
         * Salary screen chỉ dùng các field đó,
         * nên không cần join trang_thai_lam_viec / vai_tro.
         */
        $query = DB::table(
            'nhan_vien as nv'
        )
            ->leftJoin(
                'phong_ban as pb',
                'pb.ma_pb',
                '=',
                'nv.ma_pb'
            )
            ->join(
                'chuc_vu as cv',
                'cv.ma_cv',
                '=',
                'nv.ma_cv'
            )
            ->leftJoin(
                'luong as l',
                function ($join) use ($kyLuong) {
                    $join->on(
                        'l.ma_nv',
                        '=',
                        'nv.ma_nv'
                    );

                    if ($kyLuong !== null) {
                        $join->where(
                            'l.ky_luong',
                            '=',
                            $kyLuong
                        );
                    }
                }
            )
            ->leftJoinSub(
                $attendanceQuery,
                'cc',
                function ($join) {
                    $join
                        ->on(
                            'cc.ma_nv',
                            '=',
                            'nv.ma_nv'
                        )
                        ->on(
                            'cc.ky_luong',
                            '=',
                            'l.ky_luong'
                        );
                }
            )
            ->select([
                /*
                 * Nhan vien
                 */
                'nv.ma_nv',
                'nv.ho_ten',
                'nv.ngay_sinh',
                'nv.gioi_tinh',
                'nv.sdt',
                'nv.email',
                'nv.ngay_vao_lam',
                'nv.ma_pb',
                'pb.ten_pb',
                'nv.ma_cv',
                'cv.ten_cv',
                'nv.hoc_van',

                /*
                 * Luong
                 */
                'l.ma_luong',
                'l.ky_luong',
                'l.thuong',
                'l.phat',
                'l.bao_hiem',
                'l.thue',
            ])
            ->selectRaw(
                'cv.he_so_phu_cap AS phu_cap'
            )
            ->selectRaw(
                "CASE
                    WHEN nv.gioi_tinh = 1 THEN 'Nam'
                    WHEN nv.gioi_tinh = 0 THEN 'Nữ'
                    ELSE 'Khác'
                 END AS gioi_tinh_hien_thi"
            )
            ->selectRaw(
                'CASE
                    WHEN l.ma_luong IS NULL THEN 0
                    ELSE fn_tinh_luong_thuc_nhan(
                        nv.ma_nv,
                        l.ky_luong
                    )
                 END AS thuc_nhan'
            )
            ->selectRaw(
                'fn_thong_bao_tinh_luong(
                    nv.ma_nv,
                    COALESCE(
                        l.ky_luong,
                        ?
                    )
                 ) AS thong_bao_tinh_luong',
                [
                    $kyLuong,
                ]
            )
            ->selectRaw(
                'IFNULL(
                    cc.so_ngay_cham_cong,
                    0
                 ) AS so_ngay_cham_cong'
            )
            ->selectRaw(
                'IFNULL(
                    cc.so_lan_vao_muon,
                    0
                 ) AS so_lan_vao_muon'
            )
            ->selectRaw(
                'IFNULL(
                    cc.so_lan_ve_som,
                    0
                 ) AS so_lan_ve_som'
            );

        /*
         * 5. Filter phòng ban.
         */
        if ($maPhongBan !== null) {
            $query->where(
                'nv.ma_pb',
                $maPhongBan
            );
        }

        /*
         * 6. Filter chức vụ.
         */
        if ($maChucVu !== null) {
            $query->where(
                'nv.ma_cv',
                $maChucVu
            );
        }

        // Mã nhân viên là bộ lọc định danh, luôn so sánh chính xác. Không
        // dùng LIKE ở đây để tránh lộ bản ghi lương của người khác.
        if ($maNhanVien !== null) {
            $query->where('nv.ma_nv', (string) $maNhanVien);
        }

        /*
         * 7. Search.
         *
         * Giữ nguyên logic Stored Procedure:
         * - mã nhân viên
         * - họ tên
         * - tên phòng ban
         * - tên chức vụ
         */
        if ($tuKhoa !== null) {
            $query->where(
                function ($q) use ($tuKhoa) {
                    $like =
                        '%' .
                        $tuKhoa .
                        '%';

                    $q->where(
                        'nv.ma_nv',
                        'like',
                        $like
                    )
                        ->orWhere(
                            'nv.ho_ten',
                            'like',
                            $like
                        )
                        ->orWhere(
                            'pb.ten_pb',
                            'like',
                            $like
                        )
                        ->orWhere(
                            'cv.ten_cv',
                            'like',
                            $like
                        );
                }
            );
        }

        /*
         * 8. Laravel paginate() thay:
         * COUNT(*) OVER()
         * LIMIT
         * OFFSET
         */
        $sortColumns = [
            'ma_nv' => 'nv.ma_nv',
            'ho_ten' => 'nv.ho_ten',
            'ky_luong' => 'l.ky_luong',
            'thuong' => 'l.thuong',
            'phat' => 'l.phat',
            'bao_hiem' => 'l.bao_hiem',
            'thue' => 'l.thue',
            'phu_cap' => 'phu_cap',
            'thuc_nhan' => 'thuc_nhan',
            'so_ngay_cham_cong' => 'so_ngay_cham_cong',
            'so_lan_vao_muon' => 'so_lan_vao_muon',
            'so_lan_ve_som' => 'so_lan_ve_som',
        ];
        $sort = $filters['sort'] ?? 'ky_luong';
        $direction = ($filters['direction'] ?? 'desc') === 'asc' ? 'asc' : 'desc';

        $query
            ->orderBy($sortColumns[$sort] ?? $sortColumns['ky_luong'], $direction)
            ->orderBy('nv.ma_nv', 'desc')
            ->orderBy('l.ma_luong', 'desc');

        return $query
            ->paginate(
                $perPage,
                ['*'],
                'page',
                $page
            );
    }

    public function find($id, ?string $maNv = null)
    {
        $query = Luong::with('nhanVien')->whereKey($id);

        if ($maNv !== null) {
            $query->where('ma_nv', $maNv);
        }

        return $query->first();
    }

    public function create(array $data)
    {
        return Luong::create(
            $data
        );
    }

    public function update($id, array $data, ?string $maNv = null)
    {
        $query = Luong::whereKey($id);
        if ($maNv !== null) {
            $query->where('ma_nv', $maNv);
        }
        $record = $query->first();

        if ($record) {
            $record->update(
                $data
            );
        }

        return $record;
    }

    public function delete($id, ?string $maNv = null)
    {
        $query = Luong::whereKey($id);
        if ($maNv !== null) {
            $query->where('ma_nv', $maNv);
        }

        return $query->delete();
    }

    private function nullIfEmpty(
        mixed $param
    ) {
        return $param === ''
            ? null
            : $param;
    }

    private function nullableInteger(
        mixed $param
    ) {
        return is_numeric(
            $param
        )
            ? (int) $param
            : null;
    }
}
