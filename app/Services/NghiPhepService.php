<?php

namespace App\Services;

use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

class NghiPhepService
{
    /**
     * Lấy danh sách nghỉ phép.
     *
     * Filter:
     * - ma_nv
     * - trang_thai_duyet
     * - tu_ngay
     * - den_ngay
     */
    public function getAll(array $filters = []): array
    {
        try {
            $query = $this->baseLeaveQuery();

            if (! empty($filters['ma_nv'])) {
                $query->where(
                    'np.ma_nv',
                    $filters['ma_nv']
                );
            }

            /*
             * Lọc theo khoảng ngày giao nhau:
             *
             * leave.den_ngay >= filter.tu_ngay
             * leave.tu_ngay  <= filter.den_ngay
             */
            if (! empty($filters['tu_ngay'])) {
                $query->whereDate(
                    'np.den_ngay',
                    '>=',
                    $filters['tu_ngay']
                );
            }

            if (! empty($filters['den_ngay'])) {
                $query->whereDate(
                    'np.tu_ngay',
                    '<=',
                    $filters['den_ngay']
                );
            }

            $tab = $filters['tab'] ?? null;
            if (($filters['tab'] ?? null) === 'pending') {
                $query->where('np.trang_thai_duyet', 0);
            } elseif (($filters['tab'] ?? null) === 'history') {
                $query->whereIn('np.trang_thai_duyet', [1, 2]);
            } elseif (
                array_key_exists('trang_thai_duyet', $filters)
                && $filters['trang_thai_duyet'] !== null
                && $filters['trang_thai_duyet'] !== ''
            ) {
                $query->where(
                    'np.trang_thai_duyet',
                    (int) $filters['trang_thai_duyet']
                );
            }

            $counts = [
                'pending' => (clone $query)->where('np.trang_thai_duyet', 0)->count(),
                'history' => (clone $query)->whereIn('np.trang_thai_duyet', [1, 2])->count(),
            ];

            return [
                'success' => true,
                'data' => $query
                    ->orderByDesc('np.ma_np')
                    ->get(),
                'counts' => $counts,
            ];
        } catch (\Throwable $e) {
            report($e);

            return [
                'success' => false,
                'message' => 'Không thể tải danh sách nghỉ phép.',
            ];
        }
    }

    /**
     * Lấy chi tiết một đơn nghỉ phép.
     */
    public function getById($id): array
    {
        try {
            $record = $this->baseLeaveQuery()
                ->where(
                    'np.ma_np',
                    $id
                )
                ->first();

            if (! $record) {
                return [
                    'success' => false,
                    'message' => 'Không tìm thấy bản ghi',
                ];
            }

            return [
                'success' => true,
                'data' => $record,
            ];
        } catch (\Throwable $e) {
            report($e);

            return [
                'success' => false,
                'message' => 'Không thể tải chi tiết nghỉ phép.',
            ];
        }
    }

    /**
     * Query Builder replacement cho:
     * sp_nghi_phep_them
     */
    public function create(array $data): array
    {
        try {
            /*
             * 1. Validate nghiệp vụ giống Stored Procedure cũ.
             */
            if (
                isset(
                    $data['tu_ngay'],
                    $data['den_ngay']
                )
                && $data['tu_ngay'] > $data['den_ngay']
            ) {
                return [
                    'success' => false,
                    'message' =>
                        'Từ ngày phải nhỏ hơn hoặc bằng đến ngày.',
                ];
            }

            /*
             * 2. Nhân viên phải tồn tại.
             */
            $employeeExists = DB::table('nhan_vien')
                ->where(
                    'ma_nv',
                    $data['ma_nv']
                )
                ->exists();

            if (! $employeeExists) {
                return [
                    'success' => false,
                    'message' =>
                        'Mã nhân viên không tồn tại.',
                ];
            }

            /*
             * 3. Insert.
             *
             * Giả định ma_np là AUTO_INCREMENT.
             */
            $maNp = DB::table('nghi_phep')
                ->insertGetId(
                    [
                        'ma_nv' =>
                            $data['ma_nv'],

                        'tu_ngay' =>
                            $data['tu_ngay'],

                        'den_ngay' =>
                            $data['den_ngay'],

                        'ma_lp' =>
                            (int) $data['ma_lp'],

                        'ly_do' =>
                            $data['ly_do'] ?? '',

                        'trang_thai_duyet' =>
                            (int) (
                                $data[
                                'trang_thai_duyet'
                                ] ?? 0
                            ),
                    ],
                    'ma_np'
                );

            $record = $this->baseLeaveQuery()
                ->where(
                    'np.ma_np',
                    $maNp
                )
                ->first();

            return [
                'success' => true,
                'message' =>
                    'Tạo nghỉ phép thành công',
                'data' => $record,
            ];
        } catch (\Throwable $e) {
            report($e);

            return [
                'success' => false,
                'message' => 'Không thể tạo đơn nghỉ phép.',
            ];
        }
    }

    /**
     * Query Builder replacement cho:
     * sp_nghi_phep_sua
     */
    public function update(
        $id,
        array $data
    ): array {
        try {
            /*
             * 1. Validate khoảng ngày.
             */
            if (
                isset(
                    $data['tu_ngay'],
                    $data['den_ngay']
                )
                && $data['tu_ngay'] > $data['den_ngay']
            ) {
                return [
                    'success' => false,
                    'message' =>
                        'Từ ngày phải nhỏ hơn hoặc bằng đến ngày.',
                ];
            }

            /*
             * 2. Nhân viên phải tồn tại.
             */
            $employeeExists = DB::table('nhan_vien')
                ->where(
                    'ma_nv',
                    $data['ma_nv']
                )
                ->exists();

            if (! $employeeExists) {
                return [
                    'success' => false,
                    'message' =>
                        'Mã nhân viên không tồn tại.',
                ];
            }

            /*
             * 3. Đơn phải thuộc đúng nhân viên.
             */
            $leaveExists = DB::table('nghi_phep')
                ->where(
                    'ma_np',
                    $id
                )
                ->where(
                    'ma_nv',
                    $data['ma_nv']
                )
                ->exists();

            if (! $leaveExists) {
                return [
                    'success' => false,
                    'message' =>
                        'Đơn nghỉ phép của nhân viên không tồn tại.',
                ];
            }

            /*
             * 4. Update giống Stored Procedure cũ.
             */
            DB::table('nghi_phep')
                ->where(
                    'ma_np',
                    $id
                )
                ->where(
                    'ma_nv',
                    $data['ma_nv']
                )
                ->update([
                    'tu_ngay' =>
                        $data['tu_ngay'],

                    'den_ngay' =>
                        $data['den_ngay'],

                    'ly_do' =>
                        $data['ly_do'] ?? '',

                    'ma_lp' =>
                        (int) $data['ma_lp'],

                    'trang_thai_duyet' =>
                        (int) (
                            $data[
                            'trang_thai_duyet'
                            ] ?? 0
                        ),
                ]);

            /*
             * 5. Đọc lại bằng Query Builder.
             * DATE sẽ giữ dạng YYYY-MM-DD,
             * không bị Carbon serialize UTC.
             */
            $record = $this->baseLeaveQuery()
                ->where(
                    'np.ma_np',
                    $id
                )
                ->first();

            return [
                'success' => true,
                'message' =>
                    'Cập nhật nghỉ phép thành công',
                'data' => $record,
            ];
        } catch (\Throwable $e) {
            report($e);

            return [
                'success' => false,
                'message' => 'Không thể cập nhật đơn nghỉ phép.',
            ];
        }
    }

    /**
     * Query Builder replacement cho:
     * sp_nghi_phep_xoa
     */
    public function delete($id): array
    {
        try {
            $deleted = DB::table('nghi_phep')
                ->where(
                    'ma_np',
                    $id
                )
                ->delete();

            if ($deleted === 0) {
                return [
                    'success' => false,
                    'message' =>
                        'Không tìm thấy bản ghi',
                ];
            }

            return [
                'success' => true,
                'message' =>
                    'Xóa nghỉ phép thành công',
            ];
        } catch (\Throwable $e) {
            report($e);

            return [
                'success' => false,
                'message' => 'Không thể xóa đơn nghỉ phép.',
            ];
        }
    }

    /**
     * Query Builder replacement cho:
     * sp_nhan_vien_danh_sach_phan_trang
     */
    public function getEmployeesPaginated(
        ?string $tuKhoa,
        ?int $maPb,
        ?int $maCv,
        int $page = 1,
        int $perPage = 15
    ): LengthAwarePaginator {
        /*
         * Chuẩn hóa pagination giống SP.
         */
        $page = max(
            $page,
            1
        );

        $perPage = min(
            max(
                $perPage,
                1
            ),
            100
        );

        /*
         * '' -> null
         */
        $tuKhoa = trim(
            (string) $tuKhoa
        );

        if ($tuKhoa === '') {
            $tuKhoa = null;
        }

        $query = DB::table(
            'vw_danh_sach_nhan_vien_chi_tiet as nv'
        );

        /*
         * Filter phòng ban.
         */
        if ($maPb !== null) {
            $query->where(
                'nv.ma_pb',
                $maPb
            );
        }

        /*
         * Filter chức vụ.
         */
        if ($maCv !== null) {
            $query->where(
                'nv.ma_cv',
                $maCv
            );
        }

        /*
         * Search:
         * - mã NV
         * - họ tên
         * - phòng ban
         * - chức vụ
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
                            'nv.ten_pb',
                            'like',
                            $like
                        )
                        ->orWhere(
                            'nv.ten_cv',
                            'like',
                            $like
                        );
                }
            );
        }

        /*
         * paginate() tự:
         * - COUNT total
         * - LIMIT
         * - OFFSET
         * - current_page
         * - last_page
         */
        return $query
            ->orderBy(
                'nv.ma_nv',
                'asc'
            )
            ->paginate(
                $perPage,
                ['nv.*'],
                'page',
                $page
            );
    }

    /**
     * Query Builder replacement cho:
     * sp_nghi_phep_danh_sach_phan_trang
     *
     * Dùng cho màn Trưởng phòng duyệt nghỉ phép.
     */
    public function getApprovalList(
        array $filters = []
    ): LengthAwarePaginator {
        $page = max(
            (int) (
                $filters['page'] ?? 1
            ),
            1
        );

        $perPage = min(
            max(
                (int) (
                    $filters['per_page'] ?? 10
                ),
                1
            ),
            100
        );

        $tuKhoa = trim(
            (string) (
                $filters['tu_khoa'] ?? ''
            )
        );

        $maPb =
            isset($filters['ma_pb'])
                ? (int) $filters['ma_pb']
                : null;

        $maLp =
            isset($filters['ma_lp'])
            && $filters['ma_lp'] !== ''
                ? (int) $filters['ma_lp']
                : null;

        $tuNgay =
            $filters['tu_ngay'] ?? null;

        $denNgay =
            $filters['den_ngay'] ?? null;

        $tab =
            $filters['tab'] ?? 'pending';

        /*
         * Màn approval bắt buộc phải có scope phòng ban.
         * Không có ma_pb thì không được trả toàn bộ công ty.
         */
        if ($maPb === null) {
            throw new \InvalidArgumentException(
                'Thiếu phòng ban phụ trách của Trưởng phòng.'
            );
        }

        $query = DB::table('nghi_phep as np')
            ->join(
                'nhan_vien as nv',
                'nv.ma_nv',
                '=',
                'np.ma_nv'
            )
            ->leftJoin(
                'loai_phep as lp',
                'lp.ma_lp',
                '=',
                'np.ma_lp'
            )
            ->leftJoin(
                'chuc_vu as cv',
                'cv.ma_cv',
                '=',
                'nv.ma_cv'
            )
            ->leftJoin(
                'phong_ban as pb',
                'pb.ma_pb',
                '=',
                'nv.ma_pb'
            )
            ->select([
                'np.ma_np',
                'np.ma_nv',
                'nv.ho_ten',

                'nv.ma_pb',
                'pb.ten_pb',

                'nv.ma_cv',
                'cv.ten_cv',

                'np.tu_ngay',
                'np.den_ngay',

                'np.ma_lp',
                'lp.ten_lp',

                'np.ly_do',
                'np.trang_thai_duyet',
            ])
            ->selectRaw($this->leaveDaysExpression())
            ->selectRaw(
                "CASE np.trang_thai_duyet
                    WHEN 0 THEN 'Chờ duyệt'
                    WHEN 1 THEN 'Đã duyệt'
                    WHEN 2 THEN 'Từ chối'
                    ELSE 'Không xác định'
                 END AS ten_trang_thai"
            )
            ->where(
                'nv.ma_pb',
                $maPb
            );

        /*
         * Filter loại phép.
         */
        if ($maLp !== null) {
            $query->where(
                'np.ma_lp',
                $maLp
            );
        }

        /*
         * Search nhân viên.
         */
        if ($tuKhoa !== '') {
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
                        );
                }
            );
        }

        /*
         * Lọc khoảng ngày giao nhau.
         */
        if (! empty($tuNgay)) {
            $query->whereDate(
                'np.den_ngay',
                '>=',
                $tuNgay
            );
        }

        if (! empty($denNgay)) {
            $query->whereDate(
                'np.tu_ngay',
                '<=',
                $denNgay
            );
        }

        /*
         * Tab:
         * pending   = 0
         * processed = 1,2
         * all       = không filter status
         */
        if ($tab === 'pending') {
            $query->where(
                'np.trang_thai_duyet',
                0
            );
        } elseif ($tab === 'processed') {
            $query->whereIn(
                'np.trang_thai_duyet',
                [1, 2]
            );
        }

        return $query
            ->orderByDesc(
                'np.ma_np'
            )
            ->paginate(
                $perPage,
                ['*'],
                'page',
                $page
            );
    }

    /**
     * Query Builder replacement cho:
     * sp_nghi_phep_duyet_phep
     *
     * $trangThai:
     * 1 = Đã duyệt
     * 2 = Từ chối
     *
     * Chỉ xử lý đơn:
     * - đúng ma_np
     * - đúng ma_nv
     * - nhân viên thuộc ma_pb Trưởng phòng
     * - trạng thái hiện tại = 0
     */
    public function duyet(
        int $maNp,
        int $trangThai,
        int $maPb
    ): array {
        try {
            if (! in_array(
                $trangThai,
                [1, 2],
                true
            )) {
                return [
                    'success' => false,
                    'message' =>
                        'Trạng thái duyệt không hợp lệ.',
                ];
            }

            /*
             * Kiểm tra đơn nằm trong phòng ban
             * Trưởng phòng đang phụ trách.
             */
            $leave = DB::table('nghi_phep as np')
                ->join(
                    'nhan_vien as nv',
                    'nv.ma_nv',
                    '=',
                    'np.ma_nv'
                )
                ->where(
                    'np.ma_np',
                    $maNp
                )
                ->where(
                    'nv.ma_pb',
                    $maPb
                )
                ->select([
                    'np.ma_np',
                    'np.ma_nv',
                    'np.trang_thai_duyet',
                ])
                ->lockForUpdate()
                ->first();

            if (! $leave) {
                return [
                    'success' => false,
                    'message' =>
                        'Không tìm thấy đơn nghỉ phép thuộc phòng ban phụ trách.',
                ];
            }

            if (
                (int) $leave->trang_thai_duyet !== 0
            ) {
                return [
                    'success' => false,
                    'code' => 'NGHI_PHEP_ALREADY_PROCESSED',
                    'message' =>
                        'Đơn nghỉ phép đã được xử lý trước đó.',
                ];
            }

            /*
             * WHERE trang_thai_duyet = 0
             * giúp tránh update lần hai nếu có race condition.
             */
            $updated = DB::table('nghi_phep')
                ->where(
                    'ma_np',
                    $maNp
                )
                ->where(
                    'trang_thai_duyet',
                    0
                )
                ->update([
                    'trang_thai_duyet' =>
                        $trangThai,
                ]);

            if ($updated === 0) {
                return [
                    'success' => false,
                    'message' =>
                        'Không thể cập nhật trạng thái đơn nghỉ phép.',
                ];
            }

            $record = $this->baseLeaveQuery()
                ->where(
                    'np.ma_np',
                    $maNp
                )
                ->first();

            return [
                'success' => true,
                'message' =>
                    $trangThai === 1
                        ? 'Phê duyệt nghỉ phép thành công'
                        : 'Từ chối nghỉ phép thành công',
                'data' => $record,
            ];
        } catch (\Throwable $e) {
            report($e);

            return [
                'success' => false,
                'message' => $e->getMessage(),
            ];
        }
    }

    /**
     * Lookup phòng ban.
     */
    public function getPhongBan()
    {
        return DB::table('phong_ban')
            ->select([
                'ma_pb',
                'ten_pb',
            ])
            ->orderBy(
                'ma_pb',
                'asc'
            )
            ->get();
    }

    /**
     * Lookup chức vụ.
     */
    public function getChucVu()
    {
        return DB::table('chuc_vu')
            ->select([
                'ma_cv',
                'ten_cv',
            ])
            ->orderBy(
                'ma_cv',
                'asc'
            )
            ->get();
    }

    /**
     * Lookup loại phép.
     */
    public function getLoaiPhep()
    {
        return DB::table('loai_phep')
            ->select([
                'ma_lp',
                'ten_lp',
            ])
            ->orderBy(
                'ma_lp',
                'asc'
            )
            ->get();
    }

    /**
     * Query dùng chung khi trả dữ liệu nghỉ phép.
     *
     * Dùng Query Builder thay Eloquent để DATE
     * trả trực tiếp dạng YYYY-MM-DD.
     */
    private function baseLeaveQuery()
    {
        return DB::table('nghi_phep as np')
            ->join(
                'nhan_vien as nv',
                'nv.ma_nv',
                '=',
                'np.ma_nv'
            )
            ->leftJoin(
                'loai_phep as lp',
                'lp.ma_lp',
                '=',
                'np.ma_lp'
            )
            ->leftJoin(
                'phong_ban as pb',
                'pb.ma_pb',
                '=',
                'nv.ma_pb'
            )
            ->leftJoin(
                'chuc_vu as cv',
                'cv.ma_cv',
                '=',
                'nv.ma_cv'
            )
            ->select([
                'np.ma_np',
                'np.ma_nv',

                'nv.ho_ten',

                'nv.ma_pb',
                'pb.ten_pb',

                'nv.ma_cv',
                'cv.ten_cv',

                'np.tu_ngay',
                'np.den_ngay',

                'np.ma_lp',
                'lp.ten_lp',

                'np.ly_do',
                'np.trang_thai_duyet',
            ])
            ->selectRaw($this->leaveDaysExpression())
            ->selectRaw(
                "CASE np.trang_thai_duyet
                    WHEN 0 THEN 'Chờ duyệt'
                    WHEN 1 THEN 'Đã duyệt'
                    WHEN 2 THEN 'Từ chối'
                    ELSE 'Không xác định'
                 END AS ten_trang_thai"
            );
    }

    private function leaveDaysExpression(): string
    {
        return DB::connection()->getDriverName() === 'sqlite'
            ? 'CAST(julianday(np.den_ngay) - julianday(np.tu_ngay) AS INTEGER) + 1 AS so_ngay'
            : 'DATEDIFF(np.den_ngay, np.tu_ngay) + 1 AS so_ngay';
    }
}
