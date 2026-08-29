@extends('backend.layouts.app')

@section('title', 'Cập nhật ' . e($employee->ho_ten))

@section('content')
    @php
        $submitDisabled = $lookupError !== null || $missingLookups !== [];
        $backQuery = request()->only(['tu_khoa', 'ma_pb', 'ma_cv', 'ma_tt', 'page', 'so_dong']);
        $backUrl = route('backend.nhanvien.show', ['ma_nv' => $employee->ma_nv] + $backQuery);
        $selectedLookup = function (string $key, string $valueKey, string $labelKey) use ($lookups, $employee): string {
            $value = old($valueKey, data_get($employee, $valueKey));
            $selected = collect($lookups[$key])->first(
                fn (mixed $item): bool => (string) data_get($item, $valueKey) === (string) $value,
            );

            return data_get($selected, $labelKey, 'Chưa chọn');
        };
        $reviewValue = fn (string $field): string => filled(old($field, data_get($employee, $field)))
            ? old($field, data_get($employee, $field))
            : 'Chưa nhập';
    @endphp

    <main class="employee-page container container-xl py-4" aria-labelledby="page-title">
        <nav class="mb-3" aria-label="Đường dẫn trang">
            <ol class="breadcrumb mb-0">
                <li class="breadcrumb-item">Nhân sự</li>
                <li class="breadcrumb-item"><a href="{{ route('backend.nhanvien.index', $backQuery) }}">Danh sách nhân viên</a></li>
                <li class="breadcrumb-item"><a href="{{ $backUrl }}">{{ $employee->ma_nv }}</a></li>
                <li class="breadcrumb-item active" aria-current="page">Cập nhật</li>
            </ol>
        </nav>

        <header class="d-flex flex-column flex-sm-row align-items-sm-start justify-content-between gap-3 mb-4">
            <div>
                <h1 class="h3 fw-semibold mb-1" id="page-title">Cập nhật hồ sơ nhân viên</h1>
                <p class="text-secondary mb-0" id="edit-form-help">Chỉnh sửa hồ sơ và địa chỉ; mã, vai trò và mật khẩu được hệ thống giữ nguyên.</p>
            </div>
            <a class="btn btn-outline-secondary" href="{{ $backUrl }}">
                <i class="bi bi-arrow-left" aria-hidden="true"></i>
                Quay lại hồ sơ
            </a>
        </header>

        @include('backend.nhanvien.partials.flash')
        @php
            $dialogKey = preg_replace('/[^A-Za-z0-9_-]/', '-', (string) $employee->ma_nv);
            $destroyDialogId = 'employee-destroy-' . $dialogKey;
        @endphp
        @can(\App\Enums\NhanVienPermission::Xoa->value)
            @if ((string) auth()->id() !== (string) $employee->ma_nv)
            <div class="employee-action-dialogs d-inline-flex flex-wrap gap-2 mt-2" data-action-dialogs>
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
            </div>
            @endif
        @endcan
        @if ($lookupError)
            <div class="alert alert-danger" role="alert">
                <p class="fw-semibold mb-1">Không tải được dữ liệu danh mục</p>
                <p class="mb-0">{{ $lookupError }}</p>
            </div>
        @elseif ($missingLookups !== [])
            <div class="alert alert-warning" role="alert">
                <p class="fw-semibold mb-1">Thiếu dữ liệu danh mục bắt buộc</p>
                <p class="mb-2">Chưa thể cập nhật nhân viên cho tới khi có đủ:</p>
                <ul class="mb-0">
                    @foreach ($missingLookups as $missingLookup)
                        <li>{{ $missingLookup }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <div class="alert alert-info" role="note">
            <p class="mb-1"><strong>Mã nhân viên:</strong> {{ $employee->ma_nv }} (chỉ đọc).</p>
            <p class="mb-0"><strong>Vai trò:</strong> {{ $employee->ten_vt }} (chỉ đọc; thay đổi vai trò thuộc luồng quản trị riêng).</p>
        </div>

        <section class="card shadow-sm" aria-labelledby="wizard-title">
            <div class="card-header bg-white py-3">
                <h2 class="h6 fw-semibold mb-3" id="wizard-title">Quy trình cập nhật hồ sơ</h2>
                <ol class="employee-stepper mb-0" aria-label="Tiến trình cập nhật nhân viên">
                    <li data-step-indicator="1" aria-current="{{ $firstErrorStep === 1 ? 'step' : 'false' }}">
                        <span aria-hidden="true">1</span> Hồ sơ
                    </li>
                    <li data-step-indicator="2" aria-current="{{ $firstErrorStep === 2 ? 'step' : 'false' }}">
                        <span aria-hidden="true">2</span> Công việc
                    </li>
                    <li data-step-indicator="3" aria-current="{{ $firstErrorStep === 3 ? 'step' : 'false' }}">
                        <span aria-hidden="true">3</span> Kiểm tra
                    </li>
                </ol>
            </div>

            @can(\App\Enums\NhanVienPermission::Sua->value)
                @include('backend.nhanvien.partials.edit-form')
            @else
                <div class="card-body" role="alert">Bạn không có quyền sửa nhân viên.</div>
            @endcan
        </section>
    </main>
@endsection

@push('scripts')
    @vite('resources/js/frontend/nhanvien/nhanvien.js')
@endpush
