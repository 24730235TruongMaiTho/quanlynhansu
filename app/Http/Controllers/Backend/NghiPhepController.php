<?php

namespace App\Http\Controllers\Backend;

use App\Contracts\NhanVienServiceContract;
use App\Http\Requests\StoreNghiPhepRequest;
use App\Http\Requests\UpdateNghiPhepRequest;
use App\Services\NghiPhepService;
use App\Support\JsonPaginator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Routing\Controller;

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
        $validated = $request->validate([
            'ma_nv' => ['nullable', 'string', 'max:50'],
            'trang_thai_duyet' => ['nullable', 'integer', 'in:0,1,2'],
            'tu_ngay' => ['nullable', 'date_format:Y-m-d'],
            'den_ngay' => ['nullable', 'date_format:Y-m-d'],
            'tab' => ['nullable', 'in:pending,history'],
        ]);
        $filters = [
            'ma_nv' => $validated['ma_nv'] ?? null,
            'trang_thai_duyet' => $validated['trang_thai_duyet'] ?? null,
            'tu_ngay' => $validated['tu_ngay'] ?? null,
            'den_ngay' => $validated['den_ngay'] ?? null,
            'tab' => $validated['tab'] ?? null,
        ];
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
            'data' => JsonPaginator::from($data),
        ]);
    }
    public function phongBan()
    {
        try {
            $rows = $this->service->getPhongBan();
            return response()->json(['success' => true, 'data' => $rows]);
        } catch (\Throwable) {
            return response()->json(['success' => false, 'message' => 'Không thể tải danh sách phòng ban.'], 500);
        }
    }

    public function chucVu()
    {
        try {
            $rows = $this->service->getChucVu();
            return response()->json(['success' => true, 'data' => $rows]);
        } catch (\Throwable) {
            return response()->json(['success' => false, 'message' => 'Không thể tải danh sách chức vụ.'], 500);
        }
    }

    public function loaiPhep()
    {
        try {
            $rows = $this->service->getLoaiPhep();
            return response()->json(['success' => true, 'data' => $rows]);
        } catch (\Throwable) {
            return response()->json(['success' => false, 'message' => 'Không thể tải danh sách loại phép.'], 500);
        }
    }

    /**
     * Duyệt hoặc từ chối một đơn trong phòng ban của Trưởng phòng.
     */
    public function duyet(Request $request, $ma_np): JsonResponse
    {
        $validated = $request->validate([
            'trang_thai_duyet' => ['required', 'integer', 'in:1,2'],
        ]);
        $department = auth()->user()->ma_pb;

        if ($department === null) {
            return response()->json([
                'success' => false,
                'message' => 'Tài khoản chưa được phân công phòng ban phụ trách.',
            ], 403);
        }

        $data = $this->service->duyet((int) $ma_np, (int) $validated['trang_thai_duyet'], $department);

        if (! $data['success']) {
            return response()->json($data, isset($data['code']) ? 409 : 404);
        }

        return response()->json($data);
    }
}
