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
use App\Support\NhanVienTargetGuard;
use Illuminate\Http\RedirectResponse;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use Illuminate\Auth\Access\AuthorizationException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Throwable;

class NhanVienController extends Controller
{
    public function __construct(private NhanVienServiceContract $employees) {}

    public function index(ListNhanVienRequest $request): View
    {
        $filters = $request->filters();
        $employeeError = null;

        try {
            $employees = $this->employees->paginate($filters);
            $lookups = $this->employees->lookups();
        } catch (NhanVienDomainException) {
            $employees = new LengthAwarePaginator(
                collect(),
                0,
                $filters['so_dong'],
                $filters['page'],
                ['pageName' => 'page'],
            );
            $lookups = [
                'phong_ban' => [],
                'chuc_vu' => [],
                'trang_thai' => [],
            ];
            $employeeError = 'Không thể tải danh sách nhân viên lúc này. Vui lòng thử lại sau.';
        }

        $employees
            ->withPath(route('backend.nhanvien.index'))
            ->appends($filters);

        return view('backend.nhanvien.index', [
            'employees' => $employees,
            'lookups' => $lookups,
            'filters' => $filters,
            'employeeError' => $employeeError,
        ]);
    }

    public function show(string $ma_nv): View
    {
        return view('backend.nhanvien.show', [
            'employee' => $this->employees->findOrFail($ma_nv),
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
                fn (mixed $status): bool => (int) data_get($status, 'ma_tt', 0) !== NhanVienStatus::Terminated->value,
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
                'password_convention' => 'Tài khoản dùng quy ước mật khẩu demo nhom3@{năm tạo}.',
            ]);
    }

    public function edit(string $ma_nv, NhanVienTargetGuard $guard): View
    {
        $employee = $this->employees->findOrFail($ma_nv);
        $guard->assertManageable($employee);

        $emptyLookups = [
            'phong_ban' => [],
            'chuc_vu' => [],
            'trang_thai' => [],
        ];
        $lookups = $emptyLookups;
        $lookupError = null;

        try {
            $lookups = array_replace($lookups, $this->employees->lookups());
            if ((int) ($employee->ma_tt ?? 0) !== NhanVienStatus::Terminated->value) {
                $lookups['trang_thai'] = array_values(array_filter(
                    $lookups['trang_thai'],
                    fn (mixed $status): bool => (int) data_get($status, 'ma_tt', 0) !== NhanVienStatus::Terminated->value,
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
            if ($key === 'trang_thai' && (int) ($employee->ma_tt ?? 0) === NhanVienStatus::Terminated->value) {
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

        return view('backend.nhanvien.edit', [
            'employee' => $employee,
            'lookups' => $lookups,
            'lookupError' => $lookupError,
            'missingLookups' => $missingLookups,
            'firstErrorField' => $firstErrorField,
            'firstErrorStep' => $firstErrorStep,
            'avatarUrl' => $avatarUrl,
        ]);
    }

    public function update(UpdateNhanVienRequest $request, string $ma_nv): RedirectResponse
    {
        try {
            $this->employees->update($ma_nv, $request->validated());
        } catch (NhanVienDomainException $exception) {
            if ($exception->domainCode === 'NV_PRIVILEGED_TARGET') {
                abort(403);
            }

            return back()
                ->withInput($request->safe()->except('anh_dai_dien'))
                ->withErrors([
                    $exception->field ?? 'nhan_vien' => $exception->getMessage(),
                ]);
        } catch (Throwable) {
            return back()
                ->withInput($request->safe()->except('anh_dai_dien'))
                ->withErrors([
                    'nhan_vien' => 'Không thể cập nhật nhân viên lúc này. Vui lòng thử lại sau.',
                ]);
        }

        return redirect()
            ->route('backend.nhanvien.show', ['ma_nv' => $ma_nv])
            ->with('success', 'Đã cập nhật hồ sơ nhân viên.');
    }

    public function destroy(string $ma_nv, NhanVienTargetGuard $guard): RedirectResponse
    {
        try {
            $employee = $this->employees->findOrFail($ma_nv);
            $guard->assertManageable($employee);
            $action = $this->employees->removeOrTerminate($ma_nv);
        } catch (AuthorizationException) {
            abort(403);
        } catch (NotFoundHttpException) {
            abort(404);
        } catch (NhanVienDomainException $exception) {
            if ($exception->domainCode === 'NV_NOT_FOUND') {
                abort(404);
            }

            if ($exception->domainCode === 'NV_PRIVILEGED_TARGET') {
                abort(403);
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

    public function resetPassword(string $ma_nv, NhanVienTargetGuard $guard): RedirectResponse
    {
        try {
            $employee = $this->employees->findOrFail($ma_nv);
            $guard->assertManageable($employee);
            $this->employees->resetPassword($ma_nv);
        } catch (AuthorizationException) {
            abort(403);
        } catch (NotFoundHttpException) {
            abort(404);
        } catch (NhanVienDomainException $exception) {
            if ($exception->domainCode === 'NV_NOT_FOUND') {
                abort(404);
            }

            if ($exception->domainCode === 'NV_PRIVILEGED_TARGET') {
                abort(403);
            }

            return back()->withErrors([
                'nhan_vien' => 'Không thể đặt lại mật khẩu lúc này. Vui lòng thử lại sau.',
            ]);
        } catch (Throwable) {
            return back()->withErrors([
                'nhan_vien' => 'Không thể đặt lại mật khẩu lúc này. Vui lòng thử lại sau.',
            ]);
        }

        return redirect()
            ->route('backend.nhanvien.show', ['ma_nv' => $ma_nv])
            ->with('success', 'Đã đặt lại mật khẩu theo quy ước nhom3@{năm thao tác}.');
    }
}
