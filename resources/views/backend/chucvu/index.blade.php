@extends('backend.layouts.app')

@section('title', 'Quản lý chức vụ')

@section('content')
<main class="container-fluid container-xxl py-4">
    <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3 mb-4">
        <div>
            <h1 class="h3 fw-semibold mb-1">Danh sách chức vụ</h1>
            <p class="text-secondary mb-0">Quản lý thông tin chức vụ và hệ số phụ cấp.</p>
        </div>
        <a class="btn btn-primary" href="{{ route('backend.chucvu.create') }}">
            <i class="bi bi-plus-lg"></i> Thêm chức vụ
        </a>
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
                                <th>Thao tác</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($positions as $index => $position)
                            <tr>
                                <td>{{ $index + 1 }}</td>
                                <td><span class="badge bg-success">{{ $position->ma_cv }}</span></td>
                                <td>{{ $position->ten_cv }}</td>
                                <td>{{ number_format($position->he_so_phu_cap, 2) }}</td>
                                <td>
                                    <a href="{{ route('backend.chucvu.edit', $position->ma_cv) }}" 
                                       class="btn btn-sm btn-outline-primary">
                                        <i class="bi bi-pencil"></i> Sửa
                                    </a>
                                </td>
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