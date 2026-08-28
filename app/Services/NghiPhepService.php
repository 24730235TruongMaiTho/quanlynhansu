<?php

namespace App\Services;

use App\Repositories\NghiPhepRepository;
use Exception;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

class NghiPhepService
{
    protected $repository;

    public function __construct(NghiPhepRepository $repository)
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
            if (!$record) {
                return ['success' => false, 'message' => 'Không tìm thấy bản ghi'];
            }
            return ['success' => true, 'data' => $record];
        } catch (Exception $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    public function create(array $data)
    {
        try {
            $rows = DB::select(
                'CALL sp_nghi_phep_them(?, ?, ?, ?, ?, ?)',
                [
                    $data['ma_nv'],
                    $data['tu_ngay'],
                    $data['den_ngay'],
                    $data['ma_lp'],
                    $data['ly_do'],
                    $data['trang_thai_duyet'] ?? 0,
                ]
            );

            return [
                'success' => true,
                'message' => 'Tạo nghỉ phép thành công',
                'data' => $rows[0] ?? null,
            ];

        } catch (Exception $e) {
            report($e);

            return [
                'success' => false,
                'message' => $e->getMessage(),
            ];
        }
    }

    public function update($id, array $data)
    {
        try {
            DB::statement(
                'CALL sp_nghi_phep_sua(?, ?, ?, ?, ?, ?, ?)',
                [
                    $data['ma_nv'],
                    $id,
                    $data['tu_ngay'],
                    $data['den_ngay'],
                    $data['ly_do'],
                    $data['ma_lp'],
                    $data['trang_thai_duyet'] ?? 0,
                ]
            );

            /*
             * Lấy lại record sau khi Stored Procedure update.
             */
            $record = $this->repository->find($id);

            if (! $record) {
                return [
                    'success' => false,
                    'message' => 'Không tìm thấy bản ghi',
                ];
            }

            return [
                'success' => true,
                'message' => 'Cập nhật nghỉ phép thành công',
                'data' => $record,
            ];

        } catch (Exception $e) {
            report($e);

            return [
                'success' => false,
                'message' => $e->getMessage(),
            ];
        }
    }

    public function delete($id)
    {
        try {
            /*
             * Có thể check trước để trả message rõ hơn.
             */
            $record = $this->repository->find($id);

            if (! $record) {
                return [
                    'success' => false,
                    'message' => 'Không tìm thấy bản ghi',
                ];
            }

            DB::statement(
                'CALL sp_nghi_phep_xoa(?)',
                [
                    $id,
                ]
            );

            return [
                'success' => true,
                'message' => 'Xóa nghỉ phép thành công',
            ];

        } catch (Exception $e) {
            report($e);

            return [
                'success' => false,
                'message' => $e->getMessage(),
            ];
        }
    }

    /**
     * Lấy danh sách nhân viên có filter + phân trang.
     */
    public function getEmployeesPaginated(
        ?string $tuKhoa,
        ?int $maPb,
        ?int $maCv,
        int $page = 1,
        int $perPage = 15
    ): LengthAwarePaginator {
        $rows = collect(
            DB::select(
                'CALL sp_nhan_vien_danh_sach_phan_trang(?, ?, ?, ?, ?)',
                [
                    $tuKhoa,
                    $maPb,
                    $maCv,
                    $page,
                    $perPage,
                ]
            )
        );

        $total = (int) (
            $rows->first()->total_count
            ?? 0
        );

        $items = $rows
            ->map(function ($row) {
                $item = (array) $row;

                unset(
                    $item['total_count']
                );

                return (object) $item;
            })
            ->values();

        return new LengthAwarePaginator(
            $items,
            $total,
            $perPage,
            $page,
            [
                'path' =>
                    LengthAwarePaginator::resolveCurrentPath(),

                'query' =>
                    request()->query(),

                'pageName' =>
                    'page',
            ]
        );
    }

    public function getApprovalList(
        array $filters = []
    ): LengthAwarePaginator {

        $page = max(
            1,
            (int) ($filters['page'] ?? 1)
        );

        $perPage = max(
            1,
            min(
                100,
                (int) ($filters['per_page'] ?? 10)
            )
        );

        /*
         * Từ khóa
         */
        $tuKhoa = isset(
            $filters['tu_khoa']
        )
            ? trim(
                (string) $filters['tu_khoa']
            )
            : null;

        if ($tuKhoa === '') {
            $tuKhoa = null;
        }

        /*
         * Phòng ban.
         */
        $maPb = isset(
            $filters['ma_pb']
        )
        && $filters['ma_pb'] !== ''
            ? (int) $filters['ma_pb']
            : null;

        /*
         * Loại phép.
         */
        $maLp = isset(
            $filters['ma_lp']
        )
        && $filters['ma_lp'] !== ''
            ? (int) $filters['ma_lp']
            : null;

        $tuNgay =
            $filters['tu_ngay']
            ?? null;

        $denNgay =
            $filters['den_ngay']
            ?? null;

        /*
         * pending
         * processed
         * all
         */
        $tab =
            $filters['tab']
            ?? 'pending';

        if (! in_array(
            $tab,
            [
                'pending',
                'processed',
                'all',
            ],
            true
        )) {
            $tab = 'pending';
        }

        $rows = collect(
            DB::select(
                'CALL sp_nghi_phep_danh_sach_phan_trang(
                ?, ?, ?, ?, ?, ?, ?, ?
            )',
                [
                    $tuKhoa,
                    $maPb,
                    $maLp,
                    $tuNgay,
                    $denNgay,
                    $tab,
                    $page,
                    $perPage,
                ]
            )
        );

        /*
         * SP trả total_count trên mỗi row.
         */
        $total = (int) (
            $rows->first()
                ->total_count
            ?? 0
        );

        /*
         * Không cần gửi total_count lặp lại
         * trên từng item.
         */
        $items = $rows
            ->map(function ($row) {

                $item =
                    (array) $row;

                unset(
                    $item['total_count']
                );

                return $item;
            })
            ->values();

        return new LengthAwarePaginator(
            $items,
            $total,
            $perPage,
            $page,
            [
                'path' =>
                    LengthAwarePaginator
                        ::resolveCurrentPath(),

                'pageName' =>
                    'page',

                'query' =>
                    request()->query(),
            ]
        );
    }
}
