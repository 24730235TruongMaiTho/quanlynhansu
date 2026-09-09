@extends('backend.layouts.app')

@section('title', 'Thêm nhân viên')

@section('content')
    @php
        $submitDisabled = $lookupError !== null || $missingLookups !== [];
        $selectedLookup = function (string $key, string $valueKey, string $labelKey) use ($lookups): string {
            $selected = collect($lookups[$key])->first(
                fn (mixed $item): bool => (string) data_get($item, $valueKey) === (string) old($valueKey),
            );

            return data_get($selected, $labelKey, 'Chưa chọn');
        };
        $reviewValue = fn (string $field): string => filled(old($field)) ? old($field) : 'Chưa nhập';
    @endphp

    <main class="employee-page container container-xl py-4" aria-labelledby="page-title">
        <x-backend.page-header
            title="Thêm nhân viên"
            title-id="page-title"
            icon="bi-person-plus"
            description="Nhập hồ sơ, thông tin công việc rồi kiểm tra trước khi lưu."
            :breadcrumbs="[
                ['label' => 'Nhân sự', 'url' => route('backend.tongquan.index')],
                ['label' => 'Danh sách nhân viên', 'url' => route('backend.nhanvien.index')],
                ['label' => 'Thêm nhân viên'],
            ]"
        >
            <x-slot:actions>
            <a class="btn btn-outline-secondary d-inline-flex align-items-center gap-2" href="{{ route('backend.nhanvien.index') }}">
                <i class="bi bi-arrow-left" aria-hidden="true"></i>
                Quay lại danh sách
            </a>
            </x-slot:actions>
        </x-backend.page-header>

        @include('backend.nhanvien.partials.flash')

        @if ($lookupError)
            <div class="alert alert-danger" role="alert">
                <p class="fw-semibold mb-1">Không tải được dữ liệu danh mục</p>
                <p class="mb-0">{{ $lookupError }}</p>
            </div>
        @elseif ($missingLookups !== [])
            <div class="alert alert-warning" role="alert">
                <p class="fw-semibold mb-1">Thiếu dữ liệu danh mục bắt buộc</p>
                <p class="mb-2">Chưa thể tạo nhân viên cho tới khi có đủ:</p>
                <ul class="mb-0">
                    @foreach ($missingLookups as $missingLookup)
                        <li>{{ $missingLookup }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <div class="alert alert-info" role="note">
            <p class="mb-1"><strong>Mã nhân viên được hệ thống tự cấp</strong> sau khi lưu thành công.</p>
        </div>

        <section class="card shadow-sm" aria-labelledby="wizard-title">
            <div class="card-header bg-white py-3">
                <h2 class="h6 fw-semibold mb-3" id="wizard-title">Quy trình tạo hồ sơ</h2>
                <ol class="employee-stepper mb-0" aria-label="Tiến trình tạo nhân viên">
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

            @can(\App\Enums\NhanVienPermission::Tao->value)
            @include('backend.nhanvien.partials.create-form', ['modalOnly' => false])
            @else
                <div class="card-body" role="alert">Bạn không có quyền tạo nhân viên.</div>
            @endcan
        </section>
    </main>
@endsection

@push('scripts')
    @vite('resources/js/frontend/nhanvien/nhanvien.js')
@endpush
