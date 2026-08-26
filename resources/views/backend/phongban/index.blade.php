@extends('backend.layouts.app')

@section('title', 'Quản lý phòng ban')

@section('content')
@php
    $canCreate = \Illuminate\Support\Facades\Gate::allows(\App\Enums\PhongBanPermission::Tao->value);
    $canEdit = \Illuminate\Support\Facades\Gate::allows(\App\Enums\PhongBanPermission::Sua->value);
    $canDelete = \Illuminate\Support\Facades\Gate::allows(\App\Enums\PhongBanPermission::Xoa->value);
@endphp
<main class="container-fluid container-xxl py-4">
    <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3 mb-4">
        <div>
            <h1 class="h3 fw-semibold mb-1">Danh sách phòng ban</h1>
            <p class="text-secondary mb-0">Quản lý tên phòng ban và theo dõi số nhân viên.</p>
        </div>
        @if ($canCreate)
            <a class="btn btn-primary" href="{{ route('backend.phongban.create') }}">
                <i class="bi bi-plus-lg"></i> Thêm phòng ban
            </a>
        @endif
    </div>

    @if(session('success'))
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            <i class="bi bi-check-circle-fill me-2"></i>
            {{ session('success') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    @endif

    @if($errors->any())
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <i class="bi bi-exclamation-triangle-fill me-2"></i>
            @foreach ($errors->all() as $error)
                <div>{{ $error }}</div>
            @endforeach
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    @endif

    @if(isset($departmentError))
        <div class="alert alert-warning" role="alert">
            <i class="bi bi-exclamation-triangle-fill me-2"></i>
            {{ $departmentError }}
        </div>
    @endif

    <div class="card shadow-sm">
        <div class="card-body">
            @if(isset($departments) && count($departments) > 0)
                <div class="table-responsive">
                    <table class="table table-hover align-middle">
                        <thead class="table-light">
                            <tr>
                                <th>#</th>
                                <th>Mã phòng ban</th>
                                <th>Tên phòng ban</th>
                                <th>Số nhân viên</th>
                                <th>Thao tác</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($departments as $index => $department)
                            <tr>
                                <td>{{ $index + 1 }}</td>
                                <td><span class="badge bg-primary">{{ $department->ma_pb }}</span></td>
                                <td>{{ $department->ten_pb }}</td>
                                <td>
                                    <span class="badge bg-info">
                                        {{ $department->so_nhan_vien ?? 0 }}
                                    </span>
                                </td>
                                <td>
                                    @if ($canEdit)
                                        <a href="{{ route('backend.phongban.edit', $department->ma_pb) }}" class="btn btn-sm btn-outline-primary">
                                            <i class="bi bi-pencil"></i> Sửa
                                        </a>
                                    @endif
                                    @if ($canDelete && (int) ($department->so_nhan_vien ?? 0) === 0)
                                        <form action="{{ route('backend.phongban.destroy', $department->ma_pb) }}" method="POST" class="d-inline" onsubmit="return confirm('Bạn có chắc muốn xóa phòng ban này?')">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="btn btn-sm btn-outline-danger">
                                                <i class="bi bi-trash"></i> Xóa
                                            </button>
                                        </form>
                                    @elseif ($canDelete)
                                        <button type="button" class="btn btn-sm btn-outline-secondary" disabled title="Không thể xóa phòng ban đang có nhân viên">
                                            <i class="bi bi-trash"></i> Xóa
                                        </button>
                                    @endif
                                </td>
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @else
                <div class="text-center py-5">
                    <i class="bi bi-building fs-1 text-muted"></i>
                    <p class="mt-3 text-muted">Chưa có phòng ban nào.</p>
                    <a href="{{ route('backend.phongban.create') }}" class="btn btn-primary">
                        <i class="bi bi-plus-lg"></i> Thêm phòng ban đầu tiên
                    </a>
                </div>
            @endif
        </div>
    </div>
</main>
@endsection
