<?php

namespace App\Http\Controllers\Backend;

use App\Http\Requests\StoreNghiPhepRequest;
use App\Http\Requests\UpdateNghiPhepRequest;
use App\Services\NghiPhepService;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;
use Illuminate\Pagination\LengthAwarePaginator;

class NghiPhepController extends Controller
{
    protected $service;

    public function __construct(NghiPhepService $service)
    {
        $this->service = $service;
    }

    public function index(Request $request)
    {
        $filters = $request->only(['ma_nv', 'trang_thai_duyet', 'tu_ngay', 'den_ngay']);
        $result = $this->service->getAll($filters);

        if (!$result['success']) {
            return response()->json($result, 500);
        }

        return response()->json($result);
    }

    public function show($id)
    {
        $result = $this->service->getById($id);

        if (!$result['success']) {
            return response()->json($result, 404);
        }

        return response()->json($result);
    }

    public function store(StoreNghiPhepRequest $request)
    {
        $result = $this->service->create($request->validated());

        if (!$result['success']) {
            return response()->json($result, 400);
        }

        return response()->json($result, 201);
    }

    public function update(UpdateNghiPhepRequest $request, $id)
    {
        $result = $this->service->update($id, $request->validated());

        if (!$result['success']) {
            return response()->json($result, 400);
        }

        return response()->json($result);
    }

    public function destroy($id)
    {
        $result = $this->service->delete($id);

        if (!$result['success']) {
            return response()->json($result, 404);
        }

        return response()->json($result);
    }

    /**
     * Danh sách nhân viên (paging) dùng stored procedure sp_luong_tim_kiem_phan_trang
     */
    public function employees(Request $request)
    {
        $page = max((int) ($request->query('page', 1)), 1);
        $perPage = min(max((int) ($request->query('per_page', 15)), 1), 100);

        $tu_khoa = $request->query('tu_khoa', null);
        $ma_pb = $request->query('ma_pb', null);
        $ma_cv = $request->query('ma_cv', null);

        $tu_khoa = $tu_khoa === '' ? null : $tu_khoa;
        $ma_pb = is_numeric($ma_pb) ? (int) $ma_pb : null;
        $ma_cv = is_numeric($ma_cv) ? (int) $ma_cv : null;

        try {
            $rows = collect(DB::select(
                'CALL sp_luong_tim_kiem_phan_trang(?, ?, ?, ?, ?, ?)',
                [
                    $tu_khoa,
                    null, // ky_luong not used for employee lookup
                    $ma_pb,
                    $ma_cv,
                    $page,
                    $perPage,
                ]
            ));

            $total = (int) ($rows->first()->total_count ?? 0);

            $items = $rows->map(function ($row) {
                $obj = (array) $row;
                unset($obj['total_count']);
                return (object) $obj;
            })->values();

            $paginator = new LengthAwarePaginator(
                $items,
                $total,
                $perPage,
                $page,
                [
                    'path' => LengthAwarePaginator::resolveCurrentPath(),
                    'query' => request()->query(),
                    'pageName' => 'page',
                ]
            );

            return response()->json(['success' => true, 'data' => $paginator]);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    /**
     * Thêm nhân viên bằng stored procedure sp_nhan_vien_them
     */
    public function storeEmployee(Request $request)
    {
        $data = $request->all();

        $params = [
            $data['ma_nv'] ?? '',
            $data['ho_ten'] ?? '',
            $data['ngay_sinh'] ?? null,
            isset($data['gioi_tinh']) ? (int) $data['gioi_tinh'] : 1,
            $data['sdt'] ?? '',
            $data['email'] ?? '',
            $data['ngay_vao_lam'] ?? null,
            isset($data['ma_pb']) ? (int) $data['ma_pb'] : null,
            isset($data['ma_cv']) ? (int) $data['ma_cv'] : null,
            $data['dan_toc'] ?? '',
            $data['cccd'] ?? '',
            $data['noi_cap_cccd'] ?? '',
            $data['hoc_van'] ?? '',
            isset($data['ma_tt']) ? (int) $data['ma_tt'] : null,
            $data['mat_khau'] ?? null,
            isset($data['ma_vt']) ? (int) $data['ma_vt'] : null,
        ];

        try {
            DB::statement('CALL sp_nhan_vien_them(?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)', $params);
            return response()->json(['success' => true, 'message' => 'Tạo nhân viên thành công']);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 400);
        }
    }

    /**
     * Cập nhật nhân viên bằng stored procedure sp_nhan_vien_sua
     */
    public function updateEmployee(Request $request, $ma_nv)
    {
        $data = $request->all();

        $params = [
            $ma_nv,
            $data['ho_ten'] ?? '',
            $data['ngay_sinh'] ?? null,
            isset($data['gioi_tinh']) ? (int) $data['gioi_tinh'] : 1,
            $data['sdt'] ?? '',
            $data['email'] ?? '',
            $data['ngay_vao_lam'] ?? null,
            isset($data['ma_pb']) ? (int) $data['ma_pb'] : null,
            isset($data['ma_cv']) ? (int) $data['ma_cv'] : null,
            $data['dan_toc'] ?? '',
            $data['cccd'] ?? '',
            $data['noi_cap_cccd'] ?? '',
            $data['hoc_van'] ?? '',
            isset($data['ma_tt']) ? (int) $data['ma_tt'] : null,
            $data['mat_khau'] ?? null,
            isset($data['ma_vt']) ? (int) $data['ma_vt'] : null,
        ];

        try {
            DB::statement('CALL sp_nhan_vien_sua(?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)', $params);
            return response()->json(['success' => true, 'message' => 'Cập nhật nhân viên thành công']);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 400);
        }
    }

    public function phongBan()
    {
        try {
            $rows = DB::select('SELECT ma_pb, ten_pb FROM phong_ban ORDER BY ma_pb');
            return response()->json(['success' => true, 'data' => $rows]);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    public function chucVu()
    {
        try {
            $rows = DB::select('SELECT ma_cv, ten_cv FROM chuc_vu ORDER BY ma_cv');
            return response()->json(['success' => true, 'data' => $rows]);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    public function loaiPhep()
    {
        try {
            $rows = DB::select('SELECT ma_lp, ten_lp FROM loai_phep ORDER BY ma_lp');
            return response()->json(['success' => true, 'data' => $rows]);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }
}
