@extends('backend.layouts.app')

@section('title', isset($contract) ? 'Sửa hợp đồng' : 'Thêm hợp đồng')

@section('content')
    @php($isEdit = isset($contract))
    <main class="container-fluid container-xxl py-4" aria-labelledby="contract-form-title">
        <x-backend.page-header
            title="{{ $isEdit ? 'Chỉnh sửa hợp đồng' : 'Thêm hợp đồng' }}"
            title-id="contract-form-title"
            icon="bi-file-earmark-text"
            description="Cập nhật thông tin nhân viên, loại hợp đồng và thời hạn hiệu lực."
            :breadcrumbs="[
                ['label' => 'Nhân sự', 'url' => route('backend.tongquan.index')],
                ['label' => 'Quản lý hợp đồng', 'url' => route('backend.hopdong.index')],
                ['label' => $isEdit ? 'Chỉnh sửa' : 'Thêm mới'],
            ]"
        />

        <form class="card shadow-sm overflow-hidden" method="post" action="{{ $isEdit ? route('backend.hopdong.update', $contract->ma_hd) : route('backend.hopdong.store') }}">
            @csrf
            @if ($isEdit) @method('PUT') @endif
            <div class="card-header bg-white py-3"><h2 class="h6 fw-semibold mb-0">Thông tin hợp đồng</h2></div>
            <div class="card-body p-4">
                @if ($errors->any())<div class="alert alert-danger" role="alert">Vui lòng kiểm tra lại dữ liệu.</div>@endif
                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label" for="ma_nv">Nhân viên <span class="text-danger">*</span></label>
                        <select class="form-select" id="ma_nv" name="ma_nv" required>
                            @foreach ($employees as $employee)
                                <option value="{{ $employee->ma_nv }}" @selected(old('ma_nv', $contract->ma_nv ?? '') === $employee->ma_nv)>{{ $employee->ma_nv }} — {{ $employee->ho_ten }}</option>
                            @endforeach
                        </select>
                        @error('ma_nv')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-md-6">
                        <label class="form-label" for="ma_lhd">Loại hợp đồng <span class="text-danger">*</span></label>
                        <select class="form-select" id="ma_lhd" name="ma_lhd" required>
                            @foreach ($types as $type)
                                <option value="{{ $type->ma_lhd }}" @selected((string) old('ma_lhd', $contract->ma_lhd ?? '') === (string) $type->ma_lhd)>{{ $type->ten_lhd }}</option>
                            @endforeach
                        </select>
                        @error('ma_lhd')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-md-4">
                        <label class="form-label" for="ngay_ky">Ngày ký <span class="text-danger">*</span></label>
                        <input class="form-control" type="date" id="ngay_ky" name="ngay_ky" value="{{ old('ngay_ky', $contract->ngay_ky ?? '') }}" required>
                        @error('ngay_ky')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-md-4">
                        <label class="form-label" for="ngay_het_han">Ngày hết hạn</label>
                        <input class="form-control" type="date" id="ngay_het_han" name="ngay_het_han" value="{{ old('ngay_het_han', $contract->ngay_het_han ?? '') }}">
                        <div class="form-text">Để trống đối với hợp đồng không xác định thời hạn.</div>
                        @error('ngay_het_han')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-md-4">
                        <label class="form-label" for="luong_co_ban">Lương cơ bản <span class="text-danger">*</span></label>
                        <div class="input-group">
                            <input class="form-control" type="number" min="0" id="luong_co_ban" name="luong_co_ban" value="{{ old('luong_co_ban', $contract->luong_co_ban ?? '') }}" required>
                            <span class="input-group-text">VNĐ</span>
                        </div>
                        @error('luong_co_ban')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                    </div>
                </div>
            </div>
            <div class="card-footer bg-white d-flex flex-wrap justify-content-end gap-2 py-3">
                <a class="btn btn-outline-secondary d-inline-flex align-items-center gap-2" href="{{ route('backend.hopdong.index') }}"><i class="bi bi-x-lg" aria-hidden="true"></i>Hủy</a>
                <button class="btn btn-primary d-inline-flex align-items-center gap-2" type="submit"><i class="bi bi-check2" aria-hidden="true"></i>{{ $isEdit ? 'Cập nhật hợp đồng' : 'Lưu hợp đồng' }}</button>
            </div>
        </form>
    </main>
@endsection
