<?php

namespace App\Services;

use App\Repositories\ChamCongRepository;
use Exception;
use Illuminate\Database\DatabaseManager;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

class ChamCongService
{
    protected $repository;
    private DatabaseManager $database;

    public function __construct(ChamCongRepository $repository, DatabaseManager $database)
    {
        $this->repository = $repository;
        $this->database = $database;
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
            if (!$record) {
                return ['success' => false, 'message' => 'Không tìm thấy bản ghi'];
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
            if (!$result) {
                return ['success' => false, 'message' => 'Không tìm thấy bản ghi'];
            }
            return ['success' => true, 'message' => 'Xóa thành công'];
        } catch (Exception $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    /**
     * Paginate employee attendance using sp_cham_cong_nhan_vien_phan_trang.
     *
     * @param array $filters {
     *     tu_khoa?: string|null,
     *     ma_pb?: int|null,
     *     thang?: int,
     *     nam?: int,
     *     page?: int,
     *     so_dong?: int
     * }
     * @return LengthAwarePaginator
     */
    public function paginateEmployeeAttendance(array $filters): LengthAwarePaginator
    {
        return $this->database->transaction(function () use ($filters): LengthAwarePaginator {
            $filters += [
                'tu_khoa' => null,
                'ma_pb' => null,
                'thang' => now()->month,
                'nam' => now()->year,
                'page' => 1,
                'so_dong' => 15,
            ];

            // Chuẩn bị tham số cho SP - chỉ 6 tham số
            $keyword = $filters['tu_khoa'] === '' ? null : $filters['tu_khoa'];
            $department = $filters['ma_pb'] ?? null;
            $month = (int) $filters['thang'];
            $year = (int) $filters['nam'];
            $page = (int) $filters['page'];
            $perPage = (int) $filters['so_dong'];

            // Gọi SP với 6 tham số
            $pdo = DB::connection()->getPdo();

            $statement = $pdo->prepare(
                'CALL sp_cham_cong_nhan_vien_phan_trang(?, ?, ?, ?, ?, ?)'
            );
            $statement->execute([$keyword, $department, $month, $year, $page, $perPage]);
            $rows = $statement->fetchAll(\PDO::FETCH_OBJ);
            $statement->closeCursor();

            // Lấy total từ query count riêng (cùng filter)
            $totalQuery = DB::table('nhan_vien as nv')
                ->join('phong_ban as pb', 'pb.ma_pb', '=', 'nv.ma_pb')
                ->join('chuc_vu as cv', 'cv.ma_cv', '=', 'nv.ma_cv');

            if ($keyword) {
                $totalQuery->where(function ($q) use ($keyword) {
                    $q->where('nv.ma_nv', 'like', "%{$keyword}%")
                      ->orWhere('nv.ho_ten', 'like', "%{$keyword}%")
                      ->orWhere('nv.sdt', 'like', "%{$keyword}%")
                      ->orWhere('nv.email', 'like', "%{$keyword}%")
                      ->orWhere('pb.ten_pb', 'like', "%{$keyword}%")
                      ->orWhere('cv.ten_cv', 'like', "%{$keyword}%");
                });
            }

            if ($department !== null) {
                $totalQuery->where('nv.ma_pb', $department);
            }

            $total = $totalQuery->count();

            // Tạo LengthAwarePaginator từ kết quả
            return new LengthAwarePaginator(
                $rows,
                $total,
                $perPage,
                $page,
                [
                    'path' => request()->url(),
                    'query' => request()->query(),
                ]
            );
        });
    }
}
