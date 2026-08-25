@extends('backend.layouts.app')

@section('title', 'Danh sách nhân viên')

@section('content')
    @php
        $hasFilters = filled($filters['tu_khoa'])
            || filled($filters['ma_pb'])
            || filled($filters['ma_cv'])
            || filled($filters['ma_tt']);
        $hasEmptyCurrentPage = ! $employeeError
            && $employees->total() > 0
            && $employees->count() === 0;
        $listQuery = request()->only([
            'tu_khoa',
            'ma_pb',
            'ma_cv',
            'ma_tt',
            'page',
            'so_dong',
        ]);
        if (array_key_exists('ma_pb', $listQuery) || ($departmentScopeNotice ?? null) !== null) {
            $listQuery['ma_pb'] = $filters['ma_pb'];
        }
        $canEdit = \Illuminate\Support\Facades\Gate::allows(\App\Enums\NhanVienPermission::Sua->value);
        $canResetPassword = \Illuminate\Support\Facades\Gate::allows(\App\Enums\NhanVienPermission::DatLaiMatKhau->value);
        $canDestroy = \Illuminate\Support\Facades\Gate::allows(\App\Enums\NhanVienPermission::Xoa->value);
    @endphp

    <main class="container-fluid container-xxl py-4" aria-labelledby="page-title">
        <section class="mb-4">
            <div class="d-flex align-items-center gap-2 mb-1 small text-secondary" aria-label="Đường dẫn trang">
                <span>Nhân sự</span>
                <span aria-hidden="true">/</span>
                <span>Danh sách nhân viên</span>
            </div>
            <h1 class="h3 fw-semibold mb-1" id="page-title">Danh sách nhân viên</h1>
            <p class="text-secondary mb-0">Tra cứu thông tin nhân viên theo phòng ban, chức vụ và trạng thái làm việc.</p>
            @if ($departmentScopeNotice ?? null)
                <div class="alert alert-info mt-3 mb-0" role="status">
                    {{ $departmentScopeNotice }}
                </div>
            @endif
            @can(\App\Enums\NhanVienPermission::Tao->value)
                <a class="btn btn-primary mt-3" href="{{ route('backend.nhanvien.create') }}">
                    <i class="bi bi-person-plus" aria-hidden="true"></i>
                    Thêm nhân viên
                </a>
            @endcan
        </section>

        @if (session('success'))
            <div class="alert alert-success" role="status">
                {{ session('success') }}
            </div>
        @endif

        @if ($errors->any())
            <div class="alert alert-danger" role="alert">
                <p class="fw-semibold mb-1">Bộ lọc chưa hợp lệ.</p>
                <ul class="mb-0">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        @if ($employeeError)
            <div class="alert alert-danger" role="alert">
                <p class="fw-semibold mb-1">Không tải được dữ liệu</p>
                <p class="mb-0">{{ $employeeError }}</p>
            </div>
        @endif

        <section class="card shadow-sm mb-3" aria-labelledby="employee-filter-title">
            <div class="card-header bg-white py-3">
                <h2 class="h6 fw-semibold mb-0" id="employee-filter-title">Bộ lọc nhân viên</h2>
            </div>
            <div class="card-body">
                <form method="GET" action="{{ route('backend.nhanvien.index') }}" aria-busy="false" data-employee-filter>
                    <div class="row g-3 align-items-end">
                        <div class="col-12 col-lg-4">
                            <label class="form-label" for="tu_khoa">Từ khóa</label>
                            <input
                                class="form-control @error('tu_khoa') is-invalid @enderror"
                                id="tu_khoa"
                                name="tu_khoa"
                                type="search"
                                maxlength="100"
                                value="{{ $filters['tu_khoa'] }}"
                                placeholder="Mã, tên, email hoặc số điện thoại"
                            >
                            @error('tu_khoa')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="col-12 col-sm-6 col-lg-2">
                            <label class="form-label" for="ma_pb">Phòng ban</label>
                            <select class="form-select @error('ma_pb') is-invalid @enderror" id="ma_pb" name="ma_pb">
                                <option value="">Tất cả phòng ban</option>
                                @foreach ($lookups['phong_ban'] as $department)
                                    <option value="{{ $department->ma_pb }}" @selected($filters['ma_pb'] === (int) $department->ma_pb)>
                                        {{ $department->ten_pb }}
                                    </option>
                                @endforeach
                            </select>
                            @error('ma_pb')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="col-12 col-sm-6 col-lg-2">
                            <label class="form-label" for="ma_cv">Chức vụ</label>
                            <select class="form-select @error('ma_cv') is-invalid @enderror" id="ma_cv" name="ma_cv">
                                <option value="">Tất cả chức vụ</option>
                                @foreach ($lookups['chuc_vu'] as $position)
                                    <option value="{{ $position->ma_cv }}" @selected($filters['ma_cv'] === (int) $position->ma_cv)>
                                        {{ $position->ten_cv }}
                                    </option>
                                @endforeach
                            </select>
                            @error('ma_cv')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="col-12 col-sm-6 col-lg-2">
                            <label class="form-label" for="ma_tt">Trạng thái</label>
                            <select class="form-select @error('ma_tt') is-invalid @enderror" id="ma_tt" name="ma_tt">
                                <option value="">Tất cả trạng thái</option>
                                @foreach ($lookups['trang_thai'] as $status)
                                    <option value="{{ $status->ma_tt }}" @selected($filters['ma_tt'] === (int) $status->ma_tt)>
                                        {{ $status->ten_tt }}
                                    </option>
                                @endforeach
                            </select>
                            @error('ma_tt')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="col-12 col-sm-6 col-lg-2">
                            <label class="form-label" for="so_dong">Số dòng</label>
                            <select class="form-select" id="so_dong" name="so_dong">
                                @foreach ([5, 10, 20, 50, 100] as $pageSize)
                                    <option value="{{ $pageSize }}" @selected($filters['so_dong'] === $pageSize)>{{ $pageSize }}</option>
                                @endforeach
                            </select>
                        </div>

                        <div class="col-12 d-flex flex-wrap gap-2">
                            <button
                                class="btn btn-success"
                                type="submit"
                                aria-disabled="false"
                                data-disable-on-submit
                                data-submitting-text="Đang lọc..."
                            >
                                Áp dụng bộ lọc
                            </button>
                            @if ($hasFilters)
                                <a class="btn btn-outline-secondary" href="{{ route('backend.nhanvien.index') }}">Xóa bộ lọc</a>
                            @endif
                        </div>
                    </div>
                </form>
            </div>
        </section>

        <section class="card shadow-sm overflow-hidden" aria-labelledby="employee-table-title">
            <div class="card-header bg-white d-flex flex-column flex-sm-row align-items-sm-center justify-content-between gap-2 py-3">
                <div>
                    <h2 class="h6 fw-semibold mb-1" id="employee-table-title">Kết quả tra cứu</h2>
                    <p class="small text-secondary mb-0">
                        Có {{ number_format($employees->total(), 0, ',', '.') }} nhân viên phù hợp.
                    </p>
                </div>
            </div>

            @if (! $employeeError && $employees->count() > 0)
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <caption class="visually-hidden">Danh sách nhân viên theo bộ lọc hiện tại</caption>
                        <thead class="table-light">
                            <tr>
                                <th scope="col">Ảnh đại diện</th>
                                <th scope="col">Mã nhân viên</th>
                                <th scope="col">Họ tên</th>
                                <th scope="col">Liên hệ</th>
                                <th scope="col">Phòng ban</th>
                                <th scope="col">Chức vụ</th>
                                <th scope="col">Trạng thái</th>
                                <th scope="col">Thao tác</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($employees as $employee)
                                <tr>
                                    <td>
                                        @if (filled($employee->anh_dai_dien))
                                            <img
                                                class="rounded-circle object-fit-cover"
                                                src="{{ \Illuminate\Support\Facades\Storage::disk('public')->url($employee->anh_dai_dien) }}"
                                                alt="Ảnh đại diện của {{ $employee->ho_ten }}"
                                                width="40"
                                                height="40"
                                            >
                                        @else
                                            <span class="d-inline-flex align-items-center justify-content-center rounded-circle bg-light border text-secondary p-2 lh-1" aria-hidden="true">
                                                <i class="bi bi-person"></i>
                                            </span>
                                            <span class="visually-hidden">Chưa có ảnh đại diện</span>
                                        @endif
                                    </td>
                                    <th scope="row" class="text-nowrap">{{ $employee->ma_nv }}</th>
                                    <td class="fw-medium">{{ $employee->ho_ten }}</td>
                                    <td>
                                        <div>{{ $employee->sdt ?: 'Chưa cập nhật' }}</div>
                                        <div class="small text-secondary">{{ $employee->email ?: 'Chưa cập nhật email' }}</div>
                                    </td>
                                    <td>{{ $employee->ten_pb }}</td>
                                    <td>{{ $employee->ten_cv }}</td>
                                    <td>
                                        <span class="badge text-bg-light border fw-normal">{{ $employee->ten_tt }}</span>
                                    </td>
                                    <td>
                                        <a
                                            class="btn btn-sm btn-outline-primary"
                                            href="{{ route('backend.nhanvien.show', ['ma_nv' => $employee->ma_nv] + $listQuery) }}"
                                            aria-label="Xem hồ sơ {{ $employee->ho_ten }}"
                                        >
                                            Xem
                                        </a>
                                        @if ($canEdit || ((int) ($employee->ma_vt ?? 0) === \App\Enums\NhanVienRole::Employee->value
                                            && ($canResetPassword || $canDestroy)))
                                            @php
                                                $dialogKey = preg_replace('/[^A-Za-z0-9_-]/', '-', (string) $employee->ma_nv);
                                                $resetDialogId = 'employee-reset-password-' . $dialogKey;
                                                $destroyDialogId = 'employee-destroy-' . $dialogKey;
                                            @endphp
                                            <div class="employee-action-dialogs d-inline-flex flex-wrap gap-2 mt-2" data-action-dialogs>
                                                @can(\App\Enums\NhanVienPermission::Sua->value)
                                                    <a class="btn btn-sm btn-outline-primary" href="{{ route('backend.nhanvien.edit', ['ma_nv' => $employee->ma_nv] + $listQuery) }}">
                                                        Chỉnh sửa
                                                    </a>
                                                @endcan
                                                @if ((int) ($employee->ma_vt ?? 0) === \App\Enums\NhanVienRole::Employee->value)
                                                @can(\App\Enums\NhanVienPermission::DatLaiMatKhau->value)
                                                    <button class="btn btn-sm btn-outline-secondary" type="button" data-dialog-open="{{ $resetDialogId }}" aria-controls="{{ $resetDialogId }}">Đặt lại mật khẩu</button>
                                                    <dialog class="employee-action-dialog" id="{{ $resetDialogId }}" data-action-dialog aria-labelledby="{{ $resetDialogId }}-title">
                                                        <form method="POST" action="{{ route('backend.nhanvien.reset-password', ['ma_nv' => $employee->ma_nv]) }}" data-dialog-form>
                                                            @csrf
                                                            @method('PATCH')
                                                            <h2 class="h5" id="{{ $resetDialogId }}-title">Đặt lại mật khẩu nhân viên</h2>
                                                            <p>Mật khẩu sẽ được thay bằng quy ước tĩnh <code>nhom3@{năm thao tác}</code>; mật khẩu thực không hiển thị trên trang.</p>
                                                            <div class="d-flex justify-content-end gap-2">
                                                                <button type="button" class="btn btn-outline-secondary" data-dialog-cancel>Hủy</button>
                                                                <button type="submit" class="btn btn-primary" data-dialog-submit>Đặt lại mật khẩu</button>
                                                            </div>
                                                        </form>
                                                    </dialog>
                                                @endcan
                                                @can(\App\Enums\NhanVienPermission::Xoa->value)
                                                    <button class="btn btn-sm btn-outline-danger" type="button" data-dialog-open="{{ $destroyDialogId }}" aria-controls="{{ $destroyDialogId }}">Xóa hoặc kết thúc</button>
                                                    <dialog class="employee-action-dialog" id="{{ $destroyDialogId }}" data-action-dialog aria-labelledby="{{ $destroyDialogId }}-title">
                                                        <form method="POST" action="{{ route('backend.nhanvien.destroy', ['ma_nv' => $employee->ma_nv]) }}" data-dialog-form data-confirm-message="Xác nhận xóa cứng nếu chưa có lịch sử; nếu đã có lịch sử, hồ sơ sẽ được kết thúc theo lịch sử.">
                                                            @csrf
                                                            @method('DELETE')
                                                            <h2 class="h5" id="{{ $destroyDialogId }}-title">Xóa hoặc kết thúc hồ sơ</h2>
                                                            <p>Xóa cứng nếu chưa có lịch sử; nếu đã có lịch sử, hệ thống chỉ kết thúc hồ sơ và giữ lại lịch sử liên quan.</p>
                                                            <div class="d-flex justify-content-end gap-2">
                                                                <button type="button" class="btn btn-outline-secondary" data-dialog-cancel>Hủy</button>
                                                                <button type="submit" class="btn btn-danger" data-dialog-submit>Xác nhận thao tác</button>
                                                            </div>
                                                        </form>
                                                    </dialog>
                                                @endcan
                                                @endif
                                            </div>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @elseif ($hasEmptyCurrentPage)
                <div class="card-body text-center py-5" role="status">
                    <i class="bi bi-file-earmark-x fs-1 text-secondary" aria-hidden="true"></i>
                    <h3 class="h6 mt-3 mb-1">Trang kết quả hiện tại không có dữ liệu</h3>
                    <p class="text-secondary mb-3">
                        @if ($hasFilters)
                            Bộ lọc hiện tại có {{ number_format($employees->total(), 0, ',', '.') }} nhân viên,
                            nhưng trang {{ $employees->currentPage() }} không chứa dòng nào.
                        @else
                            Danh sách có {{ number_format($employees->total(), 0, ',', '.') }} nhân viên,
                            nhưng trang {{ $employees->currentPage() }} không chứa dòng nào.
                        @endif
                    </p>
                    <a class="btn btn-outline-secondary" href="{{ $employees->url(1) }}">Về trang đầu tiên</a>
                </div>
            @elseif (! $employeeError)
                <div class="card-body text-center py-5" role="status">
                    <i class="bi bi-people fs-1 text-secondary" aria-hidden="true"></i>
                    @if ($hasFilters)
                        <h3 class="h6 mt-3 mb-1">Không tìm thấy nhân viên phù hợp</h3>
                        <p class="text-secondary mb-0">Hãy điều chỉnh hoặc xóa bộ lọc để xem thêm kết quả.</p>
                    @else
                        <h3 class="h6 mt-3 mb-1">Chưa có nhân viên trong hệ thống</h3>
                        <p class="text-secondary mb-0">Danh sách sẽ hiển thị khi dữ liệu nhân viên được bổ sung.</p>
                    @endif
                </div>
            @endif

            @if ($employees->hasPages())
                <div class="card-footer bg-white d-flex justify-content-center py-3">
                    {{ $employees->links('pagination::bootstrap-5') }}
                </div>
            @endif
        </section>
    </main>
@endsection

@push('scripts')
    @vite('resources/js/frontend/nhanvien/nhanvien.js')
@endpush
