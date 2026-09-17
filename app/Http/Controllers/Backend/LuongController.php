<?php

namespace App\Http\Controllers\Backend;

use App\Http\Requests\StoreLuongRequest;
use App\Http\Requests\ListOwnLuongRequest;
use App\Http\Requests\UpdateLuongRequest;
use App\Services\LuongService;
use App\Enums\NhanVienRole;
use App\Models\NhanVien;
use App\Support\CurrentEmployee;
use App\Support\JsonPaginator;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\View\View;

class LuongController extends Controller
{
    protected $service;

    public function __construct(LuongService $service, private CurrentEmployee $currentEmployee)
    {
        $this->service = $service;
    }

    public function ownPage(ListOwnLuongRequest $request): View
    {
        $result = $this->service->getForEmployee(
            $this->currentEmployee->id($request->user()),
            $request->filters(),
        );

        return view('backend.selfservice.luong', [
            'salaryRows' => $result['success'] ? $result['data'] : collect(),
            'error' => $result['success'] ? null : $result['message'],
        ]);
    }

    public function own(ListOwnLuongRequest $request)
    {
        $result = $this->service->getForEmployee(
            $this->currentEmployee->id($request->user()),
            $request->filters(),
        );

        if (! $result['success']) {
            return response()->json($result, 500);
        }

        $result['data'] = JsonPaginator::from($result['data']);

        return response()->json($result);
    }

    public function index(Request $request)
    {
        $validated = $request->validate([
            'ma_nv' => ['nullable', 'string', 'max:50'],
            'ky_luong' => ['nullable', 'date_format:Y-m-d'],
            'ma_pb' => ['nullable', 'integer', 'min:1'],
            'ma_cv' => ['nullable', 'integer', 'min:1'],
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer'],
            'sort' => ['nullable', 'string', 'in:ma_nv,ho_ten,ky_luong,thuong,phat,bao_hiem,thue,phu_cap,thuc_nhan,so_ngay_cham_cong,so_lan_vao_muon,so_lan_ve_som'],
            'direction' => ['nullable', 'string', 'in:asc,desc'],
        ]);

        $filters = [
            'ma_nv' => $validated['ma_nv'] ?? null,
            'ky_luong' => $validated['ky_luong'] ?? null,
            'ma_pb' => isset($validated['ma_pb']) ? (int) $validated['ma_pb'] : null,
            'ma_cv' => isset($validated['ma_cv']) ? (int) $validated['ma_cv'] : null,
            'page' => max((int) ($validated['page'] ?? 1), 1),
            'per_page' => $this->pageSize($validated['per_page'] ?? null),
            'sort' => $validated['sort'] ?? 'ky_luong',
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

    private function pageSize(mixed $value): int
    {
        return in_array((int) $value, [10, 20, 50], true)
            ? (int) $value
            : 10;
    }

    public function show($id)
    {
        $result = $this->service->getById($id, $this->employeeOwner());

        if (!$result['success']) {
            return response()->json($result, 404);
        }

        return response()->json($result);
    }

    public function store(StoreLuongRequest $request)
    {
        $data = $request->validated();
        $owner = $this->employeeOwner();
        if ($owner !== null && (string) $data['ma_nv'] !== $owner) {
            return response()->json([
                'success' => false,
                'message' => 'Không được thao tác dữ liệu lương của nhân viên khác.',
            ], 403);
        }
        $result = $this->service->create($data);

        if (!$result['success']) {
            return response()->json($result, 400);
        }

        return response()->json($result, 201);
    }

    public function update(UpdateLuongRequest $request, $id)
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

    public function export(Request $request)
    {
        $validated = $request->validate([
            'ky_luong' => [
                'required',
                'string',
            ],

            'tu_khoa' => [
                'nullable',
                'string',
            ],

            'ma_pb' => [
                'nullable',
                'integer',
            ],

            'ma_cv' => [
                'nullable',
                'integer',
            ],
            'ma_nv' => [
                'nullable',
                'string',
                'regex:/\A[0-9]{5}\z/',
            ],
        ]);

        $owner = $this->employeeOwner();
        if ($owner !== null) {
            $validated['ma_nv'] = $owner;
            $validated['tu_khoa'] = null;
            $validated['ma_pb'] = null;
            $validated['ma_cv'] = null;
        }

        $result = $this->service->exportByKyLuong(
            $validated['ky_luong'],
            [
                'tu_khoa' =>
                    $validated['tu_khoa']
                    ?? null,

                'ma_nv' => $validated['ma_nv'] ?? null,

                'ma_pb' =>
                    $validated['ma_pb']
                    ?? null,

                'ma_cv' =>
                    $validated['ma_cv']
                    ?? null,
            ]
        );

        if (! $result['success']) {
            return response()->json(
                $result,
                400
            );
        }

        return response()
            ->download(
                $result['data']['file_path'],
                $result['data']['filename']
            )
            ->deleteFileAfterSend(true);
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
