<?php

namespace App\Services;

use App\Repositories\ChamCongRepository;
use Exception;

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
}
