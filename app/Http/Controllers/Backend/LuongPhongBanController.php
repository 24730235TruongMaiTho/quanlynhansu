<?php

namespace App\Http\Controllers\Backend;

use Illuminate\Routing\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class LuongPhongBanController extends Controller
{
    /**
     * Trả về danh sách tất cả phòng ban (dùng cho filter trên UI)
     */
    public function index(): JsonResponse
    {
        $items = DB::table('phong_ban')->select('ma_pb', 'ten_pb')->orderBy('ten_pb')->get();

        return response()->json([
            'success' => true,
            'data' => $items,
        ]);
    }
}
