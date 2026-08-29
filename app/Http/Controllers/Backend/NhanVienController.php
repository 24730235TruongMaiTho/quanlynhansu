<?php

namespace App\Http\Controllers\Backend;

use App\Contracts\NhanVienServiceContract;
use App\Enums\NhanVienRemovalAction;
use App\Enums\NhanVienStatus;
use App\Exceptions\NhanVienDomainException;
use App\Http\Requests\ListNhanVienRequest;
use App\Http\Requests\StoreNhanVienRequest;
use App\Http\Requests\UpdateNhanVienRequest;
use App\Support\NhanVienAvatarPath;
use App\Support\NhanVienScope;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\RedirectResponse;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Throwable;

class NhanVienController extends Controller
{
    public function __construct(
        private NhanVienServiceContract $employees,
        private NhanVienScope $scope,
    ) {}

    public function index(ListNhanVienRequest $request): View
    {
        $filters = $request->filters();
        $scopedFilters = $this->scope->filtersFor(auth()->user(), $filters);
        $employeeError = null;

        if ($scopedFilters === null) {
            $employees = new LengthAwarePaginator(
                collect(),
                0,
                $filters['so_dong'],
                $filters['page'],
                ['pageName' => 'page'],
            );
            $lookups = $this->emptyLookups();
            $employeeError = 'Không thể xác định phạm vi phòng ban. Vui lòng liên hệ quản trị viên.';
        } else {
            try {
                $employees = $this->employees->paginate($scopedFilters);
                $lookups = $this->scope->lookupsFor(auth()->user(), $this->employees->lookups());
            } catch (NhanVienDomainException) {
                $employees = new LengthAwarePaginator(
                    collect(),
                    0,
                    $filters['so_dong'],
                    $filters['page'],
                    ['pageName' => 'page'],
                );
                $lookups = $this->emptyLookups();
                $employeeError = 'Không thể tải danh sách nhân viên lúc này. Vui lòng thử lại sau.';
            }
        }

        $employees
            ->withPath(route('backend.nhanvien.index'))
            ->appends($scopedFilters ?? $filters);

        return view('backend.nhanvien.index', [
            'employees' => $employees,
            'lookups' => $lookups,
            'filters' => $scopedFilters ?? $filters,
            'employeeError' => $employeeError,
        ]);
    }

    public function show(string $ma_nv): View
    {
        $employee = $this->findForCurrentActor($ma_nv);

        return view('backend.nhanvien.show', [
            'employee' => $employee,
        ]);
    }

    public function create(): View
    {
        $emptyLookups = [
            'phong_ban' => [],
            'chuc_vu' => [],
            'trang_thai' => [],
        ];
        $lookups = $emptyLookups;
        $lookupError = null;

        try {
            $lookups = array_replace($lookups, $this->employees->lookups());
            $lookups['trang_thai'] = array_values(array_filter(
                $lookups['trang_thai'],
                fn (mixed $status): bool => ! NhanVienStatus::isTerminalValue((int) data_get($status, 'ma_tt', 0)),
            ));
        } catch (Throwable) {
            $lookups = $emptyLookups;
            $lookupError = 'Không thể tải dữ liệu danh mục lúc này. Vui lòng thử lại sau.';
        }

        $lookupLabels = [
            'phong_ban' => 'Phòng ban',
            'chuc_vu' => 'Chức vụ',
            'trang_thai' => 'Trạng thái làm việc',
        ];
        $missingLookups = [];

        foreach ($lookupLabels as $key => $label) {
            if ($lookups[$key] === []) {
                $missingLookups[] = $label;
            }
        }

        $errorBag = view()->shared('errors');
        $errorFields = $errorBag?->getBag('default')->keys() ?? [];
        $firstErrorField = $errorFields[0] ?? null;
        $stepTwoFields = ['ngay_vao_lam', 'ma_pb', 'ma_cv', 'ma_tt'];
        $firstErrorStep = $firstErrorField === null
            ? 1
            : (in_array($firstErrorField, $stepTwoFields, true) ? 2 : 1);

        if ($firstErrorField === 'nhan_vien') {
            $firstErrorStep = 3;
        }

        return view('backend.nhanvien.create', [
            'lookups' => $lookups,
            'lookupError' => $lookupError,
            'missingLookups' => $missingLookups,
            'firstErrorField' => $firstErrorField,
            'firstErrorStep' => $firstErrorStep,
        ]);
    }

    public function store(StoreNhanVienRequest $request): RedirectResponse
    {
        try {
            $maNv = $this->employees->create($request->validated());
        } catch (NhanVienDomainException $exception) {
            return back()
                ->withInput($request->safe()->except('anh_dai_dien'))
                ->withErrors([
                    $exception->field ?? 'nhan_vien' => $exception->getMessage(),
                ]);
        } catch (Throwable) {
            return back()
                ->withInput($request->safe()->except('anh_dai_dien'))
                ->withErrors([
                    'nhan_vien' => 'Không thể tạo nhân viên lúc này. Vui lòng thử lại sau.',
                ]);
        }

        return redirect()
            ->route('backend.nhanvien.show', ['ma_nv' => $maNv])
            ->with([
                'success' => 'Đã tạo nhân viên; có thể bổ sung hợp đồng sau.',
                'created_employee_code' => $maNv,
            ]);
    }

    public function edit(Request $request, string $ma_nv): View
    {
        $employee = $this->findForCurrentActor($ma_nv);

        $emptyLookups = [
            'phong_ban' => [],
            'chuc_vu' => [],
            'trang_thai' => [],
        ];
        $lookups = $emptyLookups;
        $lookupError = null;

        try {
            $lookups = array_replace($lookups, $this->employees->lookups());
            if (! NhanVienStatus::isTerminalValue((int) ($employee->ma_tt ?? 0))) {
                $lookups['trang_thai'] = array_values(array_filter(
                    $lookups['trang_thai'],
                    fn (mixed $status): bool => ! NhanVienStatus::isTerminalValue((int) data_get($status, 'ma_tt', 0)),
                ));
            }
        } catch (Throwable) {
            $lookups = $emptyLookups;
            $lookupError = 'Không thể tải dữ liệu danh mục lúc này. Vui lòng thử lại sau.';
        }

        $lookupLabels = [
            'phong_ban' => 'Phòng ban',
            'chuc_vu' => 'Chức vụ',
            'trang_thai' => 'Trạng thái làm việc',
        ];
        $missingLookups = [];
        foreach ($lookupLabels as $key => $label) {
            if ($key === 'trang_thai' && NhanVienStatus::isTerminalValue((int) ($employee->ma_tt ?? 0))) {
                continue;
            }

            if ($lookups[$key] === []) {
                $missingLookups[] = $label;
            }
        }

        $errorBag = view()->shared('errors');
        $errorFields = $errorBag?->getBag('default')->keys() ?? [];
        $firstErrorField = $errorFields[0] ?? null;
        $stepTwoFields = ['ngay_vao_lam', 'ma_pb', 'ma_cv', 'ma_tt'];
        $firstErrorStep = $firstErrorField === null
            ? 1
            : (in_array($firstErrorField, $stepTwoFields, true) ? 2 : 1);
        if ($firstErrorField === 'nhan_vien') {
            $firstErrorStep = 3;
        }

        $avatarUrl = null;
        try {
            $ownedAvatar = (new NhanVienAvatarPath)->assertOwnedFile($employee->anh_dai_dien ?? null);
            if ($ownedAvatar !== null) {
                $avatarUrl = Storage::disk('public')->url($ownedAvatar);
            }
        } catch (Throwable) {
            // Legacy or malformed paths are never rendered as an image source.
        }

        $viewData = [
            'employee' => $employee,
            'lookups' => $lookups,
            'lookupError' => $lookupError,
            'missingLookups' => $missingLookups,
            'firstErrorField' => $firstErrorField,
            'firstErrorStep' => $firstErrorStep,
            'avatarUrl' => $avatarUrl,
        ];

        if ($request->header('X-Employee-Edit-Modal') === '1' || $request->ajax()) {
            return view('backend.nhanvien.partials.edit-modal-content', $viewData);
        }

        return view('backend.nhanvien.edit', $viewData);
    }

    public function update(UpdateNhanVienRequest $request, string $ma_nv): JsonResponse|RedirectResponse
    {
        try {
            $this->employees->update($ma_nv, $request->validated());
        } catch (NhanVienDomainException $exception) {
            if ($request->expectsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => $exception->getMessage(),
                    'errors' => [
                        $exception->field ?? 'nhan_vien' => [$exception->getMessage()],
                    ],
                ], 422);
            }

            return back()
                ->withInput($request->safe()->except('anh_dai_dien'))
                ->withErrors([
                    $exception->field ?? 'nhan_vien' => $exception->getMessage(),
                ]);
        } catch (Throwable) {
            if ($request->expectsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Không thể cập nhật nhân viên lúc này. Vui lòng thử lại sau.',
                ], 500);
            }

            return back()
                ->withInput($request->safe()->except('anh_dai_dien'))
                ->withErrors([
                    'nhan_vien' => 'Không thể cập nhật nhân viên lúc này. Vui lòng thử lại sau.',
                ]);
        }

        if ($request->expectsJson()) {
            return response()->json([
                'success' => true,
                'message' => 'Đã cập nhật hồ sơ nhân viên.',
            ]);
        }

        return redirect()
            ->route('backend.nhanvien.show', ['ma_nv' => $ma_nv])
            ->with('success', 'Đã cập nhật hồ sơ nhân viên.');
    }

    public function destroy(string $ma_nv): RedirectResponse
    {
        if ((string) auth()->id() === $ma_nv) {
            return back()->withErrors([
                'nhan_vien' => 'Không thể tự xóa tài khoản đang đăng nhập.',
            ]);
        }

        if ($this->scope->isDepartmentManager(auth()->user())) {
            $this->findForCurrentActor($ma_nv);
        }

        try {
            $action = $this->employees->removeOrTerminate($ma_nv);
        } catch (NotFoundHttpException) {
            abort(404);
        } catch (NhanVienDomainException $exception) {
            if ($exception->domainCode === 'NV_NOT_FOUND') {
                abort(404);
            }

            return back()->withErrors([
                'nhan_vien' => 'Không thể xử lý nhân viên lúc này. Vui lòng thử lại sau.',
            ]);
        } catch (Throwable) {
            return back()->withErrors([
                'nhan_vien' => 'Không thể xử lý nhân viên lúc này. Vui lòng thử lại sau.',
            ]);
        }

        return redirect()
            ->route('backend.nhanvien.index')
            ->with('success', $action === NhanVienRemovalAction::Deleted
                ? 'Đã xóa hồ sơ nhân viên.'
                : 'Đã ghi nhận nhân viên nghỉ việc theo lịch sử.');
    }

    private function findForCurrentActor(string $maNv): object
    {
        if (
            $this->scope->isDepartmentManager(auth()->user())
            && $this->scope->departmentId(auth()->user()) === null
        ) {
            abort(404);
        }

        $employee = $this->employees->findOrFail($maNv);
        abort_unless($this->scope->canAccess(auth()->user(), $employee), 404);

        return $employee;
    }

    /** @return array{phong_ban: array, chuc_vu: array, trang_thai: array} */
    private function emptyLookups(): array
    {
        return [
            'phong_ban' => [],
            'chuc_vu' => [],
            'trang_thai' => [],
        ];
    }

}
