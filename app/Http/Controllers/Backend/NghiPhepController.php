<?php

namespace App\Http\Controllers\Backend;

use App\Contracts\NhanVienServiceContract;
use App\Enums\NhanVienRole;
use App\Models\NhanVien;
use App\Http\Requests\ListNghiPhepEmployeeRequest;
use App\Http\Requests\ListOwnNghiPhepRequest;
use App\Http\Requests\StoreNghiPhepRequest;
use App\Http\Requests\StoreOwnNghiPhepRequest;
use App\Http\Requests\UpdateNghiPhepRequest;
use App\Services\NghiPhepService;
use App\Support\CurrentEmployee;
use App\Support\JsonPaginator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

class NghiPhepController extends Controller
{
    protected $service;

    public function __construct(
        NghiPhepService $service,
        private NhanVienServiceContract $nhanVienService,
        private CurrentEmployee $currentEmployee,
    ) {
        $this->service = $service;
    }

    public function index(Request $request)
    {
        $validated = $request->validate([
            'ma_nv' => ['nullable', 'string', 'max:50'],
            'trang_thai_duyet' => ['nullable', 'integer', 'in:0,1,2'],
            'tu_ngay' => ['nullable', 'date_format:Y-m-d'],
            'den_ngay' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:tu_ngay'],
            'tab' => ['nullable', 'in:pending,history'],
            'tu_khoa' => ['nullable', 'string', 'max:100'],
            'ma_pb' => ['nullable', 'integer', 'min:1'],
            'ma_cv' => ['nullable', 'integer', 'min:1'],
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'in:10,20,50'],
            'sort' => ['nullable', 'string', 'in:ma_np,ma_nv,ho_ten,tu_ngay,den_ngay,ten_lp,ly_do,trang_thai_duyet,so_ngay'],
            'direction' => ['nullable', 'string', 'in:asc,desc'],
        ]);
        $filters = [
            'ma_nv' => $validated['ma_nv'] ?? null,
            'trang_thai_duyet' => $validated['trang_thai_duyet'] ?? null,
            'tu_ngay' => $validated['tu_ngay'] ?? null,
            'den_ngay' => $validated['den_ngay'] ?? null,
            'tab' => $validated['tab'] ?? null,
            'tu_khoa' => $validated['tu_khoa'] ?? null,
            'ma_pb' => isset($validated['ma_pb']) ? (int) $validated['ma_pb'] : null,
            'ma_cv' => isset($validated['ma_cv']) ? (int) $validated['ma_cv'] : null,
            'page' => (int) ($validated['page'] ?? 1),
            'per_page' => (int) ($validated['per_page'] ?? 10),
            'sort' => $validated['sort'] ?? 'ma_np',
            'direction' => $validated['direction'] ?? 'desc',
        ];
        $owner = $this->employeeOwner();
        if ($owner !== null) {
            $filters['ma_nv'] = $owner;
            $filters['tu_khoa'] = null;
            $filters['ma_pb'] = null;
            $filters['ma_cv'] = null;
        }
        $result = $this->service->getAll($filters);

        if (!$result['success']) {
            return response()->json($result, 500);
        }

        return response()->json($result);
    }

    /**
     * Return only the authenticated employee's own leave history.
     *
     * This endpoint is authentication-only; the company-wide index remains
     * protected by NghiPhep.Read.
     */
    public function own(ListOwnNghiPhepRequest $request): JsonResponse
    {
        $filters = $request->filters();
        $filters['ma_nv'] = $this->currentEmployee->id($request->user());

        $result = $this->service->getAll($filters);

        if (! $result['success']) {
            return response()->json($result, 500);
        }

        return response()->json($result);
    }

    public function storeOwn(StoreOwnNghiPhepRequest $request): JsonResponse
    {
        $data = $request->validated();
        $data['ma_nv'] = $this->currentEmployee->id($request->user());
        $data['trang_thai_duyet'] = 0;
        $result = $this->service->create($data);

        if (! $result['success']) {
            return response()->json($result, 400);
        }

        return response()->json($result, 201);
    }

    public function show($id)
    {
        $result = $this->service->getById($id, $this->employeeOwner());

        if (!$result['success']) {
            return response()->json($result, 404);
        }

        return response()->json($result);
    }

    public function store(StoreNghiPhepRequest $request)
    {
        $data = $request->validated();
        $owner = $this->employeeOwner();
        if ($owner !== null) {
            $data['ma_nv'] = $owner;
            $data['trang_thai_duyet'] = 0;
        }
        $result = $this->service->create($data);

        if (!$result['success']) {
            return response()->json($result, 400);
        }

        return response()->json($result, 201);
    }

    public function update(UpdateNghiPhepRequest $request, $id)
    {
        $data = $request->validated();
        $owner = $this->employeeOwner();
        if ($owner !== null) {
            $data['ma_nv'] = $owner;
        }
        $result = $this->service->update($id, $data, $owner);

        if (!$result['success']) {
            return response()->json($result, 400);
        }

        return response()->json($result);
    }

    public function destroy($id)
    {
        $result = $this->service->delete($id, $this->employeeOwner());

        if (!$result['success']) {
            return response()->json($result, 404);
        }

        return response()->json($result);
    }

    public function employees(ListNghiPhepEmployeeRequest $request)
    {
        $filters = $request->filters();
        if (! $request->sortWasProvided()) {
            unset($filters['sort'], $filters['direction']);
        }
        $owner = $this->employeeOwner();
        if ($owner !== null) {
            $filters['tu_khoa'] = null;
            $filters['ma_pb'] = null;
            $filters['ma_cv'] = null;
            $filters['ma_nv'] = $owner;
        }

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

        $owner = $this->employeeOwner();

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
                        $perPage,
                        $owner,
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

    /** Duyệt hoặc từ chối một đơn trong phạm vi toàn công ty. */
    public function duyet(Request $request, $ma_np): JsonResponse
    {
        $validated = $request->validate([
            'trang_thai_duyet' => ['required', 'integer', 'in:1,2'],
        ]);
        $data = $this->service->duyet((int) $ma_np, (int) $validated['trang_thai_duyet']);

        if (! $data['success']) {
            return response()->json($data, isset($data['code']) ? 409 : 404);
        }

        return response()->json($data);
    }

    private function employeeOwner(): ?string
    {
        $actor = request()->user();
        if (! $actor instanceof NhanVien || (int) $actor->ma_vt !== NhanVienRole::Employee->value) {
            return null;
        }

        $maNv = $actor->getAuthIdentifier();
        abort_unless(is_string($maNv) && preg_match('/\A[0-9]{5}\z/', $maNv) === 1, 403);

        return $maNv;
    }
}
