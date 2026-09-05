<?php

namespace App\Http\Controllers\Backend;

use App\Http\Requests\StoreLuongHeSoLuongRequest;
use App\Http\Requests\UpdateLuongHeSoLuongRequest;
use App\Support\JsonPaginator;
use Illuminate\Routing\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

class LuongHeSoLuongController extends Controller
{
    /**
     * Trả về lịch sử hệ số lương của một nhân viên (dùng cho UI)
     * Phương thức GET /api/v1/luong/he-so-luong với mã nhân viên 00001.
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $maNv = $request->query('ma_nv');
            $page = max((int) $request->query('page', 1), 1);
            $requestedPerPage = (int) $request->query('per_page', 10);
            $perPage = in_array($requestedPerPage, [10, 20, 50], true)
                ? $requestedPerPage
                : 10;

            if (empty($maNv)) {
                $paginator = new LengthAwarePaginator([], 0, $perPage, $page);
            } else {
                $paginator = DB::table('lich_su_he_so_luong')
                    ->select('ma_ls', 'ma_nv', 'he_so_luong', 'tu_ngay', 'den_ngay')
                    ->where('ma_nv', $maNv)
                    ->orderByDesc('tu_ngay')
                    ->paginate($perPage, ['*'], 'page', $page)
                    ->withQueryString();
            }

            return response()->json([
                'success' => true,
                'data' => JsonPaginator::from($paginator),
            ]);
        } catch (\Throwable $exception) {
            report($exception);

            return response()->json([
                'success' => false,
                'message' => 'Không thể tải danh sách hệ số lương.',
            ], 500);
        }
    }

    /**
     * Thêm một bản ghi lịch sử hệ số lương
     * POST /api/v1/luong/he-so-luong
     */
    public function store(StoreLuongHeSoLuongRequest $request): JsonResponse
    {
        $data = $request->validated();

        if ($this->hasOverlappingPeriod($data['ma_nv'], $data['tu_ngay'], $data['den_ngay'])) {
            return response()->json([
                'message' => 'Khoảng thời gian hệ số lương bị trùng.',
                'errors' => ['tu_ngay' => ['Khoảng thời gian bị trùng với bản ghi hiện có.']],
            ], 422);
        }

        $insertId = DB::table('lich_su_he_so_luong')->insertGetId([
            'ma_nv' => $data['ma_nv'],
            'he_so_luong' => $data['he_so_luong'],
            'tu_ngay' => $data['tu_ngay'] ?? null,
            'den_ngay' => $data['den_ngay'] ?? null,
        ]);

        $item = DB::table('lich_su_he_so_luong')
            ->where('ma_ls', $insertId)
            ->select('ma_ls', 'ma_nv', 'he_so_luong', 'tu_ngay', 'den_ngay')
            ->first();

        return response()->json([
            'success' => true,
            'data' => $item,
        ], 201);
    }

    /**
     * Cập nhật (PUT/PATCH) một bản ghi lịch sử hệ số lương
     * PUT|PATCH /api/v1/luong/he-so-luong/{ma_ls}
     */
    public function update(UpdateLuongHeSoLuongRequest $request, $ma_ls): JsonResponse
    {
        $exists = DB::table('lich_su_he_so_luong')
            ->where('ma_ls', $ma_ls)
            ->exists();

        if (! $exists) {
            return response()->json([
                'success' => false,
                'message' => 'Không tìm thấy bản ghi hệ số lương.',
            ], 404);
        }

        $data = $request->validated();

        if ($this->hasOverlappingPeriod($data['ma_nv'], $data['tu_ngay'], $data['den_ngay'], (int) $ma_ls)) {
            return response()->json([
                'message' => 'Khoảng thời gian hệ số lương bị trùng.',
                'errors' => ['tu_ngay' => ['Khoảng thời gian bị trùng với bản ghi hiện có.']],
            ], 422);
        }

        // perform update
        DB::table('lich_su_he_so_luong')
            ->where('ma_ls', $ma_ls)
            ->update($data);

        $item = DB::table('lich_su_he_so_luong')
            ->where('ma_ls', $ma_ls)
            ->select('ma_ls', 'ma_nv', 'he_so_luong', 'tu_ngay', 'den_ngay')
            ->first();

        return response()->json([
            'success' => true,
            'data' => $item,
        ]);
    }

    /**
     * Lấy chi tiết một bản ghi hệ số lương theo ma_ls
     */
    public function show($ma_ls): JsonResponse
    {
        $item = DB::table('lich_su_he_so_luong')
            ->where('ma_ls', $ma_ls)
            ->select('ma_ls', 'ma_nv', 'he_so_luong', 'tu_ngay', 'den_ngay')
            ->first();

        if (! $item) {
            return response()->json([
                'success' => false,
                'message' => 'Không tìm thấy bản ghi hệ số lương.',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => $item,
        ]);
    }

    public function destroy($ma_ls): JsonResponse
    {
        try {
            $deleted = DB::table('lich_su_he_so_luong')
                ->where('ma_ls', $ma_ls)
                ->delete();

            if ($deleted === 0) {
                return response()->json([
                    'success' => false,
                    'message' => 'Không tìm thấy bản ghi hệ số lương.',
                ], 404);
            }

            return response()->json([
                'success' => true,
                'message' => 'Xóa bản ghi hệ số lương thành công.',
            ]);
        } catch (\Throwable $exception) {
            report($exception);

            return response()->json([
                'success' => false,
                'message' => 'Không thể xóa bản ghi hệ số lương.',
            ], 500);
        }
    }

    private function hasOverlappingPeriod(
        string $maNv,
        string $fromDate,
        string $toDate,
        ?int $exceptId = null,
    ): bool {
        return DB::table('lich_su_he_so_luong')
            ->where('ma_nv', $maNv)
            ->when($exceptId !== null, fn ($query) => $query->where('ma_ls', '<>', $exceptId))
            ->where('tu_ngay', '<=', $toDate)
            ->where('den_ngay', '>=', $fromDate)
            ->exists();
    }
}
