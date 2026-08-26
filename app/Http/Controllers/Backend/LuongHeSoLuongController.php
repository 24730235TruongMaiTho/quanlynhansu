<?php

namespace App\Http\Controllers\Backend;

use Illuminate\Routing\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class LuongHeSoLuongController extends Controller
{
    /**
     * Trả về lịch sử hệ số lương của một nhân viên (dùng cho UI)
     * Phương thức GET /api/v1/luong/he-so-luong với mã nhân viên 00001.
     */
    public function index(Request $request): JsonResponse
    {
        $maNv = $request->query('ma_nv');

        if (empty($maNv)) {
            return response()->json([
                'success' => true,
                'data' => [],
            ]);
        }

        $items = DB::table('lich_su_he_so_luong')
            ->select('ma_ls', 'ma_nv', 'he_so_luong', 'tu_ngay', 'den_ngay')
            ->where('ma_nv', $maNv)
            ->orderByDesc('tu_ngay')
            ->get();

        return response()->json([
            'success' => true,
            'data' => $items,
        ]);
    }

    /**
     * Thêm một bản ghi lịch sử hệ số lương
     * POST /api/v1/luong/he-so-luong
     */
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'ma_nv' => ['required', 'string', 'max:50'],
            'he_so_luong' => ['required', 'numeric'],
            'tu_ngay' => ['nullable', 'date'],
            'den_ngay' => ['nullable', 'date', 'after_or_equal:tu_ngay'],
        ]);

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
    public function update(Request $request, $ma_ls): JsonResponse
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

        // For PUT require fields; for PATCH allow partial
        if ($request->isMethod('put')) {
            $rules = [
                'ma_nv' => ['required', 'string', 'max:50'],
                'he_so_luong' => ['required', 'numeric'],
                'tu_ngay' => ['nullable', 'date'],
                'den_ngay' => ['nullable', 'date', 'after_or_equal:tu_ngay'],
            ];

            $data = $request->validate($rules);
        } else {
            $rules = [
                'ma_nv' => ['sometimes', 'string', 'max:50'],
                'he_so_luong' => ['sometimes', 'numeric'],
                'tu_ngay' => ['sometimes', 'nullable', 'date'],
                'den_ngay' => ['sometimes', 'nullable', 'date', 'after_or_equal:tu_ngay'],
            ];

            $data = $request->validate($rules);
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
}
