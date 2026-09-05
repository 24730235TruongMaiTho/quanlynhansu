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
        <x-backend.page-header
            title="Cập nhật hồ sơ nhân viên"
            title-id="page-title"
            icon="bi-person-gear"
            description="Chỉnh sửa hồ sơ và địa chỉ; mã, vai trò và mật khẩu được hệ thống giữ nguyên."
            description-id="edit-form-help"
            :breadcrumbs="[
                ['label' => 'Nhân sự', 'url' => route('backend.tongquan.index')],
                ['label' => 'Danh sách nhân viên', 'url' => route('backend.nhanvien.index', $backQuery)],
                ['label' => $employee->ma_nv, 'url' => $backUrl],
                ['label' => 'Cập nhật'],
            ]"
        >
            <x-slot:actions>
            <a class="btn btn-outline-secondary d-inline-flex align-items-center gap-2" href="{{ $backUrl }}">
                <i class="bi bi-arrow-left" aria-hidden="true"></i>
                Quay lại hồ sơ
            </a>
            </x-slot:actions>
        </x-backend.page-header>

        @include('backend.nhanvien.partials.flash')
        @include('backend.nhanvien.partials.action-dialogs', ['employee' => $employee])
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
