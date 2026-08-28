<?php

namespace App\Http\Controllers\Backend;

use App\Contracts\NhanVienServiceContract;
use App\Http\Requests\StoreNghiPhepRequest;
use App\Http\Requests\UpdateNghiPhepRequest;
use App\Services\NghiPhepService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;

class NghiPhepController extends Controller
{
    protected $service;

    public function __construct(
        NghiPhepService $service,
        private NhanVienServiceContract $nhanVienService,
    ) {
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

    public function employees(Request $request)
    {
        $validated = $request->validate([
            'tu_khoa' => ['nullable', 'string', 'max:255'],
            'ma_pb' => ['nullable', 'integer', 'min:1'],
            'ma_cv' => ['nullable', 'integer', 'min:1'],
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        $keyword = isset($validated['tu_khoa'])
            ? trim($validated['tu_khoa'])
            : null;

        $filters = [
            'tu_khoa' => $keyword === '' ? null : $keyword,
            'ma_pb' => isset($validated['ma_pb']) ? (int) $validated['ma_pb'] : null,
            'ma_cv' => isset($validated['ma_cv']) ? (int) $validated['ma_cv'] : null,
            'ma_tt' => null,
            'page' => (int) ($validated['page'] ?? 1),
            'so_dong' => (int) ($validated['per_page'] ?? 15),
        ];

        try {
            $paginator = $this->nhanVienService->paginate($filters);
            $paginator->withPath($request->url())->appends($request->query());

            return response()->json(['success' => true, 'data' => $paginator]);
        } catch (\Throwable) {
            return response()->json([
                'success' => false,
                'message' => 'Không thể tải danh sách nhân viên.',
            ], 500);
        }
    }

    public function employeesV2(
        Request $request
    ): JsonResponse {
        $page = max(
            (int) $request->query(
                'page',
                1
            ),
            1
        );

        $perPage = min(
            max(
                (int) $request->query(
                    'per_page',
                    15
                ),
                1
            ),
            100
        );

        $tuKhoa =
            $request->query(
                'tu_khoa'
            );

        $maPb =
            $request->query(
                'ma_pb'
            );

        $maCv =
            $request->query(
                'ma_cv'
            );

        $tuKhoa =
            $tuKhoa === ''
                ? null
                : $tuKhoa;

        $maPb =
            is_numeric($maPb)
                ? (int) $maPb
                : null;

        $maCv =
            is_numeric($maCv)
                ? (int) $maCv
                : null;

        try {
            $paginator =
                $this->service
                    ->getEmployeesPaginated(
                        $tuKhoa,
                        $maPb,
                        $maCv,
                        $page,
                        $perPage
                    );

            return response()->json([
                'success' => true,
                'data' => $paginator,
            ]);

        } catch (\Throwable $exception) {
            report($exception);

            return response()->json([
                'success' => false,
                'message' =>
                    $exception->getMessage(),
            ], 500);
        }
    }


    public function approvalList(
        Request $request
    ) {
        $validated =
            $request->validate([
                'tu_khoa' =>
                    'nullable|string|max:100',

                'ma_lp' =>
                    'nullable|integer',

                'tu_ngay' =>
                    'nullable|date_format:Y-m-d',

                'den_ngay' => [
                    'nullable',
                    'date_format:Y-m-d',
                    'after_or_equal:tu_ngay',
                ],

                'tab' =>
                    'nullable|in:pending,processed,all',

                'page' =>
                    'nullable|integer|min:1',

                'per_page' =>
                    'nullable|integer|min:1|max:100',
            ]);

        /*
         * QUAN TRỌNG:
         * Không lấy ma_pb từ FE.
         *
         * Trưởng phòng chỉ được xem
         * phòng ban của chính mình.
         */
        $validated['ma_pb'] =
            auth()->user()->ma_pb;

        $data =
            $this->service
                ->getApprovalList(
                    $validated
                );

        return response()->json([
            'success' => true,
            'data' => $data,
        ]);
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
