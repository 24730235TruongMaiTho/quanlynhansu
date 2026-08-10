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
            // The stored procedure sp_luong_tim_kiem_phan_trang may return multiple rows per
            // employee when ky_luong is NULL (one row per lương record), causing duplicates.
            // Use vw_danh_sach_nhan_vien_chi_tiet and group by ma_nv to return unique employees.

            $whereClauses = [];
            $bindings = [];

            if ($tu_khoa !== null && $tu_khoa !== '') {
                $whereClauses[] = "(ma_nv LIKE ? OR ho_ten LIKE ? OR sdt LIKE ? OR email LIKE ? OR cccd LIKE ? OR ten_pb LIKE ? OR ten_cv LIKE ? )";
                $like = '%' . $tu_khoa . '%';
                $bindings = array_merge($bindings, array_fill(0, 7, $like));
            }

            if ($ma_pb !== null) {
                $whereClauses[] = "ma_pb = ?";
                $bindings[] = $ma_pb;
            }

            if ($ma_cv !== null) {
                $whereClauses[] = "ma_cv = ?";
                $bindings[] = $ma_cv;
            }

            $whereSql = $whereClauses ? 'WHERE ' . implode(' AND ', $whereClauses) : '';

            // total distinct employees matching filters
            $countSql = "SELECT COUNT(DISTINCT ma_nv) AS total FROM vw_danh_sach_nhan_vien_chi_tiet " . $whereSql;
            $countRow = collect(DB::select($countSql, $bindings))->first();
            $total = (int) ($countRow->total ?? 0);

            // fetch unique employees with pagination
            $offset = ($page - 1) * $perPage;

            $selectSql = "SELECT ma_nv, ho_ten, ngay_sinh, gioi_tinh_hien_thi AS gioi_tinh, sdt, email, ngay_vao_lam, ma_pb, ten_pb, ma_cv, ten_cv, hoc_van, ten_tt FROM vw_danh_sach_nhan_vien_chi_tiet "
                . $whereSql . " GROUP BY ma_nv ORDER BY ma_nv ASC LIMIT ? OFFSET ?";

            $rows = collect(DB::select($selectSql, array_merge($bindings, [$perPage, $offset])));

            $items = $rows->map(function ($row) {
                return (object) $row;
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

    /**
     * Duyệt (approve/deny) nghỉ phép dùng stored procedure sp_nghi_phep_duyet_phep
     * PATCH /api/v1/nghi-phep/{ma_np}/duyet
     * Body: { ma_nv: string, trang_thai_duyet: int }
     */
    public function duyet(Request $request, $ma_np)
    {
        $ma_nv = $request->input('ma_nv');
        $trang_thai = $request->input('trang_thai_duyet', 1);

        if (empty($ma_nv)) {
            return response()->json(['success' => false, 'message' => 'ma_nv is required'], 400);
        }

        try {
            DB::statement('CALL sp_nghi_phep_duyet_phep(?, ?, ?)', [
                (int) $ma_np,
                $ma_nv,
                (int) $trang_thai,
            ]);

            return response()->json(['success' => true, 'message' => 'Cập nhật trạng thái duyệt thành công']);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 400);
        }
    }
}
