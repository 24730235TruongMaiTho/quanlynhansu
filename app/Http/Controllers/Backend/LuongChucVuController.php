<?php

namespace App\Http\Controllers\Backend;

use Illuminate\Routing\Controller;
use App\Models\ChucVu;
use Illuminate\Http\JsonResponse;

class LuongChucVuController extends Controller
{
    /**
     * Trả về danh sách tất cả chức vụ (dùng cho filter trên UI)
     */
    public function index(): JsonResponse
    {
        $items = ChucVu::orderBy('ten_cv')->get(['ma_cv', 'ten_cv']);

        return response()->json([
            'success' => true,
            'data' => $items,
        ]);
    }
}
