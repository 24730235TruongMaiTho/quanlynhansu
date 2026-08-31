<?php

namespace App\Services;

use App\Repositories\ChamCongRepository;
use Carbon\Carbon;
use Exception;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

class ChamCongService
{
    protected $repository;

    public function __construct(ChamCongRepository $repository)
    {
        $this->repository = $repository;
    }

    public function getAll($filters = [])
    {
        try {
            return [
                'success' => true,
                'data' => $this->repository->all($filters),
            ];
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => $e->getMessage(),
            ];
        }
    }

    public function getById($id)
    {
        try {
            $record = $this->repository->find($id);

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
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => $e->getMessage(),
            ];
        }
    }

    public function create(array $data)
    {
        try {
            $record = $this->repository->create($data);

            return [
                'success' => true,
                'message' => 'Tạo thành công',
                'data' => $record,
            ];
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => $e->getMessage(),
            ];
        }
    }

    public function update($id, array $data)
    {
        try {
            $record = $this->repository->update($id, $data);

            if (! $record) {
                return [
                    'success' => false,
                    'message' => 'Không tìm thấy bản ghi',
                ];
            }

            return [
                'success' => true,
                'message' => 'Cập nhật thành công',
                'data' => $record,
            ];
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => $e->getMessage(),
            ];
        }
    }

    public function delete($id)
    {
        try {
            $result = $this->repository->delete($id);

            if (! $result) {
                return [
                    'success' => false,
                    'message' => 'Không tìm thấy bản ghi',
                ];
            }

            return [
                'success' => true,
                'message' => 'Xóa thành công',
            ];
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => $e->getMessage(),
            ];
        }
    }

    /**
     * Query Builder replacement for:
     * sp_cham_cong_nhan_vien_phan_trang
     *
     * Không dùng Stored Procedure và không dùng
     * vw_danh_sach_nhan_vien_chi_tiet.
     *
     * Filters:
     * - tu_khoa?: string|null
     * - ma_pb?: int|null
     * - thang?: int
     * - nam?: int
     * - page?: int
     * - so_dong?: int
     */
    public function paginateEmployeeAttendance(array $filters): LengthAwarePaginator
    {
        $filters += [
            'tu_khoa' => null,
            'ma_pb' => null,
            'thang' => now()->month,
            'nam' => now()->year,
            'page' => 1,
            'so_dong' => 15,
        ];

        $keyword = trim((string) ($filters['tu_khoa'] ?? ''));
        $keyword = $keyword === '' ? null : $keyword;

        $department = is_numeric($filters['ma_pb'] ?? null)
            ? (int) $filters['ma_pb']
            : null;

        $month = (int) ($filters['thang'] ?? now()->month);
        $year = (int) ($filters['nam'] ?? now()->year);

        if ($month < 1 || $month > 12) {
            $month = now()->month;
        }

        if ($year < 1900 || $year > 2100) {
            $year = now()->year;
        }

        $page = max((int) ($filters['page'] ?? 1), 1);

        $perPage = min(
            max((int) ($filters['so_dong'] ?? 15), 1),
            100
        );

        $fromDate = Carbon::create($year, $month, 1)
            ->startOfMonth()
            ->toDateString();

        $toDate = Carbon::create($year, $month, 1)
            ->startOfMonth()
            ->addMonth()
            ->toDateString();

        /*
         * Aggregate chấm công theo nhân viên trong tháng đang chọn.
         */
        $attendanceSubQuery = DB::table('cham_cong as cc')
            ->select('cc.ma_nv')
            ->selectRaw(
                'SUM(
                    CASE
                        WHEN cc.so_gio_lam >= 8 THEN 1
                        WHEN cc.so_gio_lam >= 4 THEN 0.5
                        ELSE 0
                    END
                ) AS so_ngay_cham_cong'
            )
            ->selectRaw(
                'SUM(
                    CASE
                        WHEN cc.vao_muon = 1 THEN 1
                        ELSE 0
                    END
                ) AS so_lan_vao_muon'
            )
            ->selectRaw(
                'SUM(
                    CASE
                        WHEN cc.ve_som = 1 THEN 1
                        ELSE 0
                    END
                ) AS so_lan_ve_som'
            )
            ->whereDate('cc.ngay_lam', '>=', $fromDate)
            ->whereDate('cc.ngay_lam', '<', $toDate)
            ->groupBy('cc.ma_nv');

        /*
         * Main query:
         * nhan_vien -> phong_ban -> chuc_vu -> attendance aggregate
         */
        $query = DB::table('nhan_vien as nv')
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
            ->leftJoinSub(
                $attendanceSubQuery,
                'cc',
                function ($join) {
                    $join->on(
                        'cc.ma_nv',
                        '=',
                        'nv.ma_nv'
                    );
                }
            )
            ->select([
                'nv.ma_nv',
                'nv.ho_ten',
                'nv.ngay_sinh',
                'nv.gioi_tinh',
                'nv.sdt',
                'nv.email',
                'nv.ma_pb',
                'pb.ten_pb',
                'nv.ma_cv',
                'cv.ten_cv',
            ])
            ->selectRaw(
                'COALESCE(cc.so_ngay_cham_cong, 0) AS so_ngay_cham_cong'
            )
            ->selectRaw(
                'COALESCE(cc.so_lan_vao_muon, 0) AS so_lan_vao_muon'
            )
            ->selectRaw(
                'COALESCE(cc.so_lan_ve_som, 0) AS so_lan_ve_som'
            );

        if ($department !== null) {
            $query->where('nv.ma_pb', $department);
        }

        if ($keyword !== null) {
            $query->where(function ($q) use ($keyword) {
                $like = '%' . $keyword . '%';

                $q->where('nv.ma_nv', 'like', $like)
                    ->orWhere('nv.ho_ten', 'like', $like)
                    ->orWhere('pb.ten_pb', 'like', $like)
                    ->orWhere('cv.ten_cv', 'like', $like);
            });
        }

        return $query
            ->orderBy('nv.ma_nv', 'asc')
            ->paginate(
                $perPage,
                ['*'],
                'page',
                $page
            );
    }

    /**
     * Save nhiều ngày chấm công trong 1 request + 1 transaction.
     *
     * Payload:
     * [
     *   'ma_nv' => 'NV001',
     *   'thang' => 7,
     *   'nam' => 2026,
     *   'rows' => [
     *      [
     *          'ma_cc' => null|int,
     *          'ngay_lam' => '2026-07-01',
     *          'so_gio_lam' => -1..24,
     *          'vao_muon' => 0|1,
     *          've_som' => 0|1,
     *      ],
     *   ],
     * ]
     *
     * Quy ước:
     * - so_gio_lam = -1:
     *      + record chưa tồn tại -> bỏ qua
     *      + record đã tồn tại  -> delete
     * - record chưa tồn tại + hours >= 0 -> insert
     * - record đã tồn tại + hours >= 0    -> update
     */
    public function saveBatchAttendance(
        array $payload
    ): array {
        try {
            return DB::transaction(
                function () use ($payload) {
                    $maNv =
                        (string) $payload['ma_nv'];

                    $month =
                        (int) $payload['thang'];

                    $year =
                        (int) $payload['nam'];

                    $rows =
                        collect(
                            $payload['rows'] ?? []
                        );

                    if ($rows->isEmpty()) {
                        throw new \DomainException(
                            'Không có dữ liệu chấm công cần lưu.'
                        );
                    }

                    /*
                     * Employee phải tồn tại.
                     */
                    $employeeExists =
                        DB::table('nhan_vien')
                            ->where(
                                'ma_nv',
                                $maNv
                            )
                            ->exists();

                    if (! $employeeExists) {
                        throw new \DomainException(
                            'Mã nhân viên không tồn tại.'
                        );
                    }

                    /*
                     * Chỉ cho thao tác ngày thuộc đúng kỳ
                     * mà frontend đang mở.
                     */
                    $periodStart =
                        Carbon::create(
                            $year,
                            $month,
                            1
                        )
                            ->startOfMonth();

                    $periodEnd =
                        $periodStart
                            ->copy()
                            ->endOfMonth();

                    $normalizedRows =
                        $rows->map(
                            function (
                                array $row
                            ) use (
                                $maNv,
                                $periodStart,
                                $periodEnd
                            ) {
                                $date =
                                    Carbon::createFromFormat(
                                        'Y-m-d',
                                        $row['ngay_lam']
                                    )
                                        ->startOfDay();

                                if (
                                    $date->lt($periodStart) ||
                                    $date->gt($periodEnd)
                                ) {
                                    throw new \DomainException(
                                        sprintf(
                                            'Ngày %s không thuộc kỳ %s.',
                                            $date->format('d/m/Y'),
                                            $periodStart->format('m/Y')
                                        )
                                    );
                                }

                                $hours =
                                    (float) $row['so_gio_lam'];

                                if (
                                    $hours < -1 ||
                                    $hours > 24
                                ) {
                                    throw new \DomainException(
                                        sprintf(
                                            'Số giờ làm ngày %s phải nằm trong khoảng -1 đến 24.',
                                            $date->format('d/m/Y')
                                        )
                                    );
                                }

                                return [
                                    'ma_nv' =>
                                        $maNv,

                                    'ngay_lam' =>
                                        $date->toDateString(),

                                    'so_gio_lam' =>
                                        $hours,

                                    'vao_muon' =>
                                        (int) (
                                            $row['vao_muon']
                                            ?? 0
                                        ),

                                    've_som' =>
                                        (int) (
                                            $row['ve_som']
                                            ?? 0
                                        ),
                                ];
                            }
                        )
                            ->values();

                    /*
                     * Không cho gửi trùng ngày trong cùng batch.
                     */
                    if (
                        $normalizedRows
                            ->pluck('ngay_lam')
                            ->duplicates()
                            ->isNotEmpty()
                    ) {
                        throw new \DomainException(
                            'Danh sách chấm công có ngày bị trùng.'
                        );
                    }

                    $dates =
                        $normalizedRows
                            ->pluck('ngay_lam')
                            ->all();

                    /*
                     * Giữ nghiệp vụ cũ:
                     * ngày nghỉ phép đã duyệt chỉ được chấm 0 giờ.
                     *
                     * Query một lần cho toàn khoảng batch.
                     */
                    $approvedLeaves =
                        DB::table('nghi_phep')
                            ->where(
                                'ma_nv',
                                $maNv
                            )
                            ->where(
                                'trang_thai_duyet',
                                1
                            )
                            ->whereDate(
                                'tu_ngay',
                                '<=',
                                max($dates)
                            )
                            ->whereDate(
                                'den_ngay',
                                '>=',
                                min($dates)
                            )
                            ->get([
                                'tu_ngay',
                                'den_ngay',
                            ]);

                    foreach (
                        $normalizedRows as $row
                    ) {
                        /*
                         * -1 nghĩa là delete/unset,
                         * không cần check leave.
                         */
                        if (
                            (float) $row['so_gio_lam'] === -1.0
                        ) {
                            continue;
                        }

                        $isApprovedLeave =
                            $approvedLeaves->contains(
                                function (
                                    object $leave
                                ) use ($row) {
                                    $date =
                                        $row['ngay_lam'];

                                    return (
                                        $date >=
                                        substr(
                                            (string) $leave->tu_ngay,
                                            0,
                                            10
                                        )
                                        &&
                                        $date <=
                                        substr(
                                            (string) $leave->den_ngay,
                                            0,
                                            10
                                        )
                                    );
                                }
                            );

                        if (
                            $isApprovedLeave &&
                            (float) $row['so_gio_lam'] !== 0.0
                        ) {
                            throw new \DomainException(
                                sprintf(
                                    'Nhân viên %s có nghỉ phép đã duyệt ngày %s, chỉ được chấm công 0 giờ.',
                                    $maNv,
                                    Carbon::parse(
                                        $row['ngay_lam']
                                    )->format('d/m/Y')
                                )
                            );
                        }
                    }

                    /*
                     * Lock các record hiện tại của những ngày cần sửa
                     * để tránh concurrent update.
                     */
                    $existing =
                        DB::table('cham_cong')
                            ->where(
                                'ma_nv',
                                $maNv
                            )
                            ->whereIn(
                                'ngay_lam',
                                $dates
                            )
                            ->lockForUpdate()
                            ->get([
                                'ma_cc',
                                'ma_nv',
                                'ngay_lam',
                            ])
                            ->keyBy(
                                fn (object $item) =>
                                substr(
                                    (string) $item->ngay_lam,
                                    0,
                                    10
                                )
                            );

                    $insertRows = [];
                    $updateRows = [];
                    $deleteIds = [];

                    foreach (
                        $normalizedRows as $row
                    ) {
                        $date =
                            $row['ngay_lam'];

                        $current =
                            $existing->get(
                                $date
                            );

                        /*
                         * -1:
                         * record có sẵn => delete
                         * record chưa có => không làm gì
                         */
                        if (
                            (float) $row['so_gio_lam'] === -1.0
                        ) {
                            if ($current) {
                                $deleteIds[] =
                                    (int) $current->ma_cc;
                            }

                            continue;
                        }

                        $writeRow = [
                            'ma_nv' =>
                                $maNv,

                            'ngay_lam' =>
                                $date,

                            'so_gio_lam' =>
                                $row['so_gio_lam'],

                            'vao_muon' =>
                                $row['vao_muon'],

                            've_som' =>
                                $row['ve_som'],
                        ];

                        if ($current) {
                            $updateRows[] = [
                                'ma_cc' =>
                                    (int) $current->ma_cc,

                                ...$writeRow,
                            ];
                        } else {
                            $insertRows[] =
                                $writeRow;
                        }
                    }

                    /*
                     * Tối đa 3 write statements cho cả batch:
                     * 1 DELETE
                     * 1 UPSERT existing
                     * 1 INSERT new
                     */
                    if (! empty($deleteIds)) {
                        DB::table('cham_cong')
                            ->whereIn(
                                'ma_cc',
                                $deleteIds
                            )
                            ->delete();
                    }

                    if (! empty($updateRows)) {
                        DB::table('cham_cong')
                            ->upsert(
                                $updateRows,
                                ['ma_cc'],
                                [
                                    'ma_nv',
                                    'ngay_lam',
                                    'so_gio_lam',
                                    'vao_muon',
                                    've_som',
                                ]
                            );
                    }

                    if (! empty($insertRows)) {
                        DB::table('cham_cong')
                            ->insert(
                                $insertRows
                            );
                    }

                    return [
                        'success' =>
                            true,

                        'message' =>
                            'Lưu bảng chấm công thành công.',

                        'data' => [
                            'received' =>
                                $normalizedRows->count(),

                            'inserted' =>
                                count($insertRows),

                            'updated' =>
                                count($updateRows),

                            'deleted' =>
                                count($deleteIds),

                            'ignored' =>
                                $normalizedRows->count()
                                - count($insertRows)
                                - count($updateRows)
                                - count($deleteIds),
                        ],
                    ];
                }
            );
        } catch (\Throwable $exception) {
            report($exception);

            return [
                'success' =>
                    false,

                'message' =>
                    $exception->getMessage(),
            ];
        }
    }

}
