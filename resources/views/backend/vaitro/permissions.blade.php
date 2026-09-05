@extends('backend.layouts.app')

@section('title', 'Phân quyền vai trò')

@section('content')
    <main class="container-fluid container-xxl py-4" aria-labelledby="permissions-title">
        <x-backend.page-header
            title="Phân quyền vai trò"
            title-id="permissions-title"
            icon="bi-shield-lock"
            description="Vai trò {{ $role->ten_vt }} được làm gì trên từng module của hệ thống."
            :breadcrumbs="[
                ['label' => 'Hệ thống', 'url' => route('backend.tongquan.index')],
                ['label' => 'Quản lý vai trò', 'url' => route('backend.vaitro.index')],
                ['label' => 'Phân quyền'],
            ]"
        >
            <x-slot:titleSuffix>
                <span class="identifier-text">Mã vai trò: {{ $role->ma_vt }}</span>
            </x-slot:titleSuffix>
        </x-backend.page-header>

        @if (session('success'))<div class="alert alert-success" role="status"><i class="bi bi-check-circle-fill me-2" aria-hidden="true"></i>{{ session('success') }}</div>@endif
        @if ($errors->any())<div class="alert alert-danger" role="alert">{{ $errors->first() }}</div>@endif

        <div class="alert alert-info d-flex gap-3 align-items-start" role="note">
            <i class="bi bi-diagram-3-fill fs-5" aria-hidden="true"></i>
            <div><strong>Luồng phân quyền:</strong> Vai trò → Module → Hành động. Tài khoản chỉ nhận các quyền thông qua vai trò được gán.</div>
        </div>

        <form method="post" action="{{ route('backend.vaitro.permissions.update', $role->ma_vt) }}">
            @csrf @method('PUT')
            <section class="card shadow-sm overflow-hidden" aria-labelledby="module-permission-title">
                <div class="card-header bg-white py-3">
                    <h2 class="h6 fw-semibold mb-1" id="module-permission-title">Quyền theo module</h2>
                    <p class="small text-secondary mb-0">Chọn chính xác các hành động mà vai trò này được phép thực hiện.</p>
                </div>
                <div class="card-body p-4">
                    <div class="row g-3">
                        @foreach ($permissions as $module => $items)
                            <div class="col-12 col-lg-6">
                                <fieldset class="border rounded-3 h-100 p-3">
                                    <legend class="float-none w-auto h6 fw-semibold px-2 mb-2">{{ $module }}</legend>
                                    <div class="d-grid gap-2">
                                        @foreach ($items as $permission)
                                            <label class="permission-option d-flex align-items-start gap-3 rounded-3 border p-3" for="permission-{{ $permission->ma_quyen }}">
                                                <input class="form-check-input mt-1" type="checkbox" name="ma_quyen[]" value="{{ $permission->ma_quyen }}" id="permission-{{ $permission->ma_quyen }}" @checked(in_array((int) $permission->ma_quyen, $selected, true))>
                                                <span>
                                                    <span class="d-block fw-semibold">{{ $permission->ten_quyen }}</span>
                                                </span>
                                            </label>
                                        @endforeach
                                    </div>
                                </fieldset>
                            </div>
                        @endforeach
                    </div>
                </div>
                <div class="card-footer bg-white d-flex flex-wrap justify-content-end gap-2 py-3">
                    <a class="btn btn-outline-secondary d-inline-flex align-items-center gap-2" href="{{ route('backend.vaitro.index') }}"><i class="bi bi-arrow-left" aria-hidden="true"></i>Quay lại danh sách vai trò</a>
                    <button class="btn btn-primary" type="submit"><i class="bi bi-check2-circle me-1" aria-hidden="true"></i>Lưu phân quyền</button>
                </div>
            </section>
        </form>
    </main>
@endsection

@push('styles')
    <style>
        .permission-option { cursor: pointer; transition: border-color .15s ease, background-color .15s ease; }
        .permission-option:hover { border-color: #86b7fe !important; background: #f8fbff; }
        .permission-option:has(.form-check-input:checked) { border-color: #0d6efd !important; background: #f0f6ff; }
    </style>
@endpush
