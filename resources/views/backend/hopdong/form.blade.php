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

        <form class="card shadow-sm overflow-hidden" method="post" action="{{ $isEdit ? route('backend.hopdong.update', $contract->ma_hd) : route('backend.hopdong.store') }}" data-contract-form>
            @csrf
            @if ($isEdit) @method('PUT') @endif
            <div class="card-header bg-white py-3"><h2 class="h6 fw-semibold mb-0">Thông tin hợp đồng</h2></div>
            <div class="card-body p-4">
                @if ($errors->any())<div class="alert alert-danger" role="alert">Vui lòng kiểm tra lại dữ liệu.</div>@endif
                @include('backend.hopdong.partials.form-fields')
            </div>
            <div class="card-footer bg-white d-flex flex-wrap justify-content-end gap-2 py-3">
                <a class="btn btn-outline-secondary btn-icon-text" href="{{ route('backend.hopdong.index') }}"><i class="bi bi-x-lg" aria-hidden="true"></i><span>Hủy</span></a>
                <button class="btn btn-primary btn-icon-text" type="submit"><i class="bi bi-check2" aria-hidden="true"></i><span data-button-label>{{ $isEdit ? 'Cập nhật hợp đồng' : 'Lưu hợp đồng' }}</span></button>
            </div>
        </form>
    </main>
@endsection

@push('scripts')
    @vite('resources/js/frontend/hopdong/hopdong.js')
@endpush
