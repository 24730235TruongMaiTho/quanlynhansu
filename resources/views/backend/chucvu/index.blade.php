@extends('backend.layouts.app')

@section('title', 'Quản lý chức vụ')

@section('content')
@php
    $canCreate = \Illuminate\Support\Facades\Gate::allows(\App\Enums\ChucVuPermission::Tao->value);
    $canEdit = \Illuminate\Support\Facades\Gate::allows(\App\Enums\ChucVuPermission::Sua->value);
    $canDelete = \Illuminate\Support\Facades\Gate::allows(\App\Enums\ChucVuPermission::Xoa->value);
@endphp
<main class="container-fluid container-xxl py-4">
    <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3 mb-4">
        <div>
            <h1 class="h3 fw-semibold mb-1">Danh sách chức vụ</h1>
            <p class="text-secondary mb-0">Quản lý thông tin chức vụ và hệ số phụ cấp.</p>
        </div>
        @if ($canCreate)
            <a class="btn btn-primary" href="{{ route('backend.chucvu.create') }}">
                <i class="bi bi-plus-lg"></i> Thêm chức vụ
            </a>
        @endif
    </div>

    @if(session('success'))
        <div class="alert alert-success">{{ session('success') }}</div>
    @endif

    @if(isset($positions) && count($positions) > 0)
        <div class="card shadow-sm">
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-hover align-middle">
                        <thead class="table-light">
                            <tr>
                                <th>#</th>
                                <th>Mã chức vụ</th>
                                <th>Tên chức vụ</th>
                                <th>Hệ số phụ cấp</th>
                                <th>Số nhân viên</th>
                                @if ($canEdit || $canDelete)
                                    <th>Thao tác</th>
                                @endif
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($positions as $index => $position)
                            <tr>
                                <td>{{ $index + 1 }}</td>
                                <td><span class="badge bg-success">{{ $position->ma_cv }}</span></td>
                                <td>{{ $position->ten_cv }}</td>
                                <td>{{ number_format($position->he_so_phu_cap, 2) }}</td>
                                @php($hasEmployees = (int) ($position->so_nhan_vien ?? 0) > 0)
                                <td>{{ $hasEmployees ? $position->so_nhan_vien : 'Chưa có nhân viên' }}</td>
                                @if ($canEdit || $canDelete)
                                    <td>
                                        @if ($canEdit)
                                            <a href="{{ route('backend.chucvu.edit', $position->ma_cv) }}" class="btn btn-sm btn-outline-primary">
                                                <i class="bi bi-pencil"></i> Sửa
                                            </a>
                                        @endif
                                        @if ($canDelete && ! $hasEmployees)
                                            <form method="POST" action="{{ route('backend.chucvu.destroy', $position->ma_cv) }}" class="d-inline" onsubmit="return confirm('Bạn có chắc muốn xóa chức vụ này?')">
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit" class="btn btn-sm btn-outline-danger">Xóa</button>
                                            </form>
                                        @elseif ($canDelete)
                                            <button type="button" class="btn btn-sm btn-outline-secondary" disabled title="Không thể xóa chức vụ đang có nhân viên">Xóa</button>
                                        @endif
                                    </td>
                                @endif
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    @else
        <div class="text-center py-5">
            <i class="bi bi-briefcase fs-1 text-muted"></i>
            <p class="mt-3 text-muted">Chưa có chức vụ nào.</p>
        </div>
    @endif
</main>
@endsection
