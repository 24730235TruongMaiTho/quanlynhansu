@extends('backend.layouts.app')

@section('title', 'Phân quyền vai trò')

@section('content')
    <main class="container-fluid container-xxl py-4" aria-labelledby="permissions-title">
        <nav class="mb-3" aria-label="Đường dẫn trang">
            <ol class="breadcrumb mb-0">
                <li class="breadcrumb-item">Hệ thống</li>
                <li class="breadcrumb-item"><a href="{{ route('backend.vaitro.index') }}">Quản lý vai trò</a></li>
                <li class="breadcrumb-item active" aria-current="page">Phân quyền</li>
            </ol>
        </nav>

        <header class="d-flex flex-column flex-lg-row justify-content-between align-items-lg-center gap-3 mb-4">
            <div>
                <h1 class="h3 fw-semibold mb-1" id="permissions-title">Phân quyền vai trò</h1>
                <p class="text-secondary mb-0">Vai trò <strong>{{ $role->ten_vt }}</strong> được làm gì trên từng module của hệ thống.</p>
            </div>
            <span class="badge rounded-pill text-bg-light border px-3 py-2">Mã vai trò: {{ $role->ma_vt }}</span>
        </header>

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
                                                    <small class="text-secondary">{{ $permission->ky_hieu_quyen }}</small>
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
                    <a class="btn btn-outline-secondary" href="{{ route('backend.vaitro.index') }}">Quay lại danh sách vai trò</a>
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
