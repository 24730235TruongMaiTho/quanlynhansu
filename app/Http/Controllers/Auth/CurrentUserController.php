<?php

namespace App\Http\Controllers\Auth;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class CurrentUserController extends Controller
{
    /**
     * GET /api/v1/auth/me
     *
     * Trả authenticated user + toàn bộ quyền của vai trò hiện tại.
     * Không filter theo module.
     */
    public function me(Request $request): JsonResponse
    {
        $user = Auth::user();

        if (! $user) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthenticated',
            ], 401);
        }

        $vaiTro = DB::table('vai_tro')
            ->where('ma_vt', $user->ma_vt)
            ->first();

        $permissions = DB::table('vai_tro_quyen as vtq')
            ->join(
                'quyen as q',
                'q.ma_quyen',
                '=',
                'vtq.ma_quyen'
            )
            ->where('vtq.ma_vt', $user->ma_vt)
            ->orderBy('q.ma_quyen')
            ->pluck('q.ky_hieu_quyen')
            ->filter()
            ->unique()
            ->values()
            ->all();

        return response()->json([
            'success' => true,
            'data' => [
                'user' => [
                    'ma_nv' => $user->ma_nv,
                    'ho_ten' => $user->ho_ten,
                    'email' => $user->email,
                    'ma_vt' => $user->ma_vt,
                    'ma_pb' => $user->ma_pb,
                    'vai_tro' => $vaiTro
                        ? [
                            'ma_vt' => $vaiTro->ma_vt,
                            'ten_vt' => $vaiTro->ten_vt,
                            'mo_ta' => $vaiTro->mo_ta,
                        ]
                        : null,
                ],

                'permissions' => $permissions,
            ],
        ]);
    }
}
