@extends('backend.layouts.app')

@section('title', 'Quản lý phòng ban')

@section('content')
    @php
        $canCreate = \Illuminate\Support\Facades\Gate::allows(\App\Enums\PhongBanPermission::Tao->value);
        $canEdit = \Illuminate\Support\Facades\Gate::allows(\App\Enums\PhongBanPermission::Sua->value);
        $canDelete = \Illuminate\Support\Facades\Gate::allows(\App\Enums\PhongBanPermission::Xoa->value);
    @endphp

    <main class="container-fluid container-xxl py-4" aria-labelledby="department-page-title">
        <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3 mb-4">
            <div>
                <div class="small text-secondary mb-1">Nhân sự / Phòng ban</div>
                <h1 class="h3 fw-semibold mb-1" id="department-page-title">Danh sách phòng ban</h1>
                <p class="text-secondary mb-0">Quản lý tên phòng ban và theo dõi số nhân viên đang thuộc từng phòng ban.</p>
            </div>
            @if ($canCreate)
                <a class="btn btn-primary" href="{{ route('backend.phongban.create') }}">
                    <i class="bi bi-plus-lg" aria-hidden="true"></i>
                    Thêm phòng ban
                </a>
            @endif
        </div>

        @if (session('success'))
            <div class="alert alert-success" role="status">{{ session('success') }}</div>
        @endif

        @if ($errors->any())
            <div class="alert alert-danger" role="alert">
                <p class="fw-semibold mb-1">Không thể hoàn tất thao tác.</p>
                <ul class="mb-0">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        @if ($departmentError)
            <div class="alert alert-danger" role="alert">
                <p class="fw-semibold mb-1">Không tải được dữ liệu</p>
                <p class="mb-0">{{ $departmentError }}</p>
            </div>
        @elseif (count($departments) === 0)
            <section class="card shadow-sm" aria-live="polite">
                <div class="card-body text-center py-5">
                    <i class="bi bi-building fs-1 text-secondary" aria-hidden="true"></i>
                    <h2 class="h5 mt-3 mb-1">Chưa có phòng ban nào</h2>
                    <p class="text-secondary mb-0">Thêm phòng ban đầu tiên để bắt đầu quản lý nhân sự.</p>
                </div>
            </section>
        @else
            <section class="card shadow-sm overflow-hidden" aria-labelledby="department-table-title">
                <div class="card-header bg-white py-3">
                    <h2 class="h6 fw-semibold mb-0" id="department-table-title">Phòng ban hiện có</h2>
                </div>
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <caption class="visually-hidden">Danh sách phòng ban và số nhân viên</caption>
                        <thead class="table-light">
                            <tr>
                                <th scope="col">Mã phòng ban</th>
                                <th scope="col">Tên phòng ban</th>
                                <th scope="col">Số nhân viên</th>
                                @if ($canEdit || $canDelete)
                                    <th scope="col">Thao tác</th>
                                @endif
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($departments as $department)
                                @php($hasEmployees = (int) $department->so_nhan_vien > 0)
                                <tr>
                                    <th scope="row">{{ $department->ma_pb }}</th>
                                    <td class="fw-medium">{{ $department->ten_pb }}</td>
                                    <td>
                                        @if ($hasEmployees)
                                            {{ $department->so_nhan_vien }}
                                        @else
                                            <span class="text-secondary">Chưa có nhân viên</span>
                                        @endif
                                    </td>
                                    @if ($canEdit || $canDelete)
                                        <td>
                                            <div class="d-flex flex-wrap gap-2">
                                                @if ($canEdit)
                                                    <a class="btn btn-sm btn-outline-primary" href="{{ route('backend.phongban.edit', ['ma_pb' => $department->ma_pb]) }}">
                                                        Chỉnh sửa
                                                    </a>
                                                @endif
                                                @if ($canDelete && ! $hasEmployees)
                                                    <form method="POST" action="{{ route('backend.phongban.destroy', ['ma_pb' => $department->ma_pb]) }}" data-confirm-delete="Xác nhận xóa phòng ban này?">
                                                        @csrf
                                                        @method('DELETE')
                                                        <button class="btn btn-sm btn-outline-danger" type="submit" data-submit> Xóa </button>
                                                    </form>
                                                @elseif ($canDelete)
                                                    <button class="btn btn-sm btn-outline-secondary" type="button" disabled title="Không thể xóa phòng ban đang có nhân viên">Xóa</button>
                                                @endif
                                            </div>
                                        </td>
                                    @endif
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </section>
        @endif
    </main>
@endsection

@push('scripts')
    @vite('resources/js/frontend/phongban/phongban.js')
@endpush
