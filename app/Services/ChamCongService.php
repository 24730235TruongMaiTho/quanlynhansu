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
}
