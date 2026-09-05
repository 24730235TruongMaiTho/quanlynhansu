@extends('backend.layouts.app')

@section('title', 'Quản lý phòng ban')

@section('content')
    @php
        $canCreate = \Illuminate\Support\Facades\Gate::allows(\App\Enums\PhongBanPermission::Tao->value);
        $canEdit = \Illuminate\Support\Facades\Gate::allows(\App\Enums\PhongBanPermission::Sua->value);
        $canDelete = \Illuminate\Support\Facades\Gate::allows(\App\Enums\PhongBanPermission::Xoa->value);
        $hasFilters = filled($filters['ten_pb']);
        $listQuery = array_filter([
            'ten_pb' => $filters['ten_pb'],
            'page' => $filters['page'] > 1 ? $filters['page'] : null,
            'so_dong' => $filters['so_dong'] !== 20 ? $filters['so_dong'] : null,
        ], static fn (mixed $value): bool => $value !== null && $value !== '');
    @endphp

    <main class="container-fluid container-xxl py-4" aria-labelledby="department-page-title">
        <x-backend.page-header
            title="Danh sách phòng ban"
            title-id="department-page-title"
            icon="bi-building"
            description="Quản lý tên phòng ban và theo dõi số nhân viên."
            :breadcrumbs="[
                ['label' => 'Nhân sự', 'url' => route('backend.tongquan.index')],
                ['label' => 'Quản lý phòng ban'],
            ]"
        >
            <x-slot:actions>
            @if ($canCreate)
                <a class="btn btn-primary d-inline-flex align-items-center gap-2" aria-label="Thêm phòng ban" title="Thêm phòng ban" href="{{ route('backend.phongban.create') }}">
                    <i class="bi bi-plus-circle" aria-hidden="true"></i>Thêm phòng ban
                </a>
            @endif
            </x-slot:actions>
        </x-backend.page-header>

        @if (session('success'))
            <div class="alert alert-success" role="status">
                <i class="bi bi-check-circle-fill me-2" aria-hidden="true"></i>{{ session('success') }}
            </div>
        @endif
        @if ($errors->any())
            <div class="alert alert-danger" role="alert">
                @foreach ($errors->all() as $error)
                    <div>{{ $error }}</div>
                @endforeach
            </div>
        @endif
        @if ($departmentError)
            <div class="alert alert-warning" role="alert">
                <i class="bi bi-exclamation-triangle-fill me-2" aria-hidden="true"></i>{{ $departmentError }}
            </div>
        @endif

        <section class="card shadow-sm mb-3 filter-card" aria-labelledby="department-filter-title">
            <div class="card-header bg-white py-3">
                <h2 class="h6 fw-semibold mb-0" id="department-filter-title">Bộ lọc phòng ban</h2>
            </div>
            <div class="card-body">
                <form method="GET" action="{{ route('backend.phongban.index') }}" aria-busy="false" data-department-filter class="filter-bar">
                    <div class="filter-bar__fields">
                        <div class="filter-bar__field">
                            <label class="form-label" for="ten_pb">Tìm theo tên phòng ban</label>
                            <input class="form-control" id="ten_pb" name="ten_pb" type="search" maxlength="100" value="{{ $filters['ten_pb'] ?? '' }}" placeholder="Nhập tên phòng ban">
                        </div>
                        <div class="filter-bar__field">
                            <label class="form-label" for="so_dong">Số dòng</label>
                            <select class="form-select" id="so_dong" name="so_dong">
                                @foreach ([5, 10, 20, 50, 100] as $pageSize)
                                    <option value="{{ $pageSize }}" @selected($filters['so_dong'] === $pageSize)>{{ $pageSize }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>
                    <div class="filter-bar__actions">
                            <button class="btn btn-primary d-inline-flex align-items-center gap-2" type="submit" data-disable-on-submit data-submitting-text="Đang lọc..."><i class="bi bi-funnel" aria-hidden="true"></i>Áp dụng bộ lọc</button>
                            @if ($hasFilters)
                                <a class="btn btn-outline-secondary d-inline-flex align-items-center gap-2" href="{{ route('backend.phongban.index') }}"><i class="bi bi-x-lg" aria-hidden="true"></i>Xóa</a>
                            @endif
                    </div>
                </form>
            </div>
        </section>

        <section class="card shadow-sm overflow-hidden" aria-labelledby="department-table-title">
            <div class="card-header bg-white d-flex flex-column flex-sm-row align-items-sm-center justify-content-between gap-2 py-3">
                <div>
                    <h2 class="h6 fw-semibold mb-1" id="department-table-title">Kết quả tra cứu</h2>
                    @include('backend.partials.pagination-summary', ['paginator' => $departments, 'summaryLabel' => 'phòng ban'])
                </div>
            </div>

            @if (! $departmentError && $departments->count() > 0)
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <caption class="visually-hidden">Danh sách phòng ban theo bộ lọc hiện tại</caption>
                        <thead class="table-light">
                            <tr>
                                <th scope="col">#</th>
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
                                @php
                                    $hasEmployees = (int) ($department->so_nhan_vien ?? 0) > 0;
                                    $deleteId = 'phongban-delete-' . (int) $department->ma_pb;
                                @endphp
                                <tr>
                                    <td>{{ ($departments->firstItem() ?? 0) + $loop->index }}</td>
                                    <th scope="row"><span class="identifier-text">{{ $department->ma_pb }}</span></th>
                                    <td>{{ $department->ten_pb }}</td>
                                    <td><span class="text-secondary">{{ $department->so_nhan_vien ?? 0 }}</span></td>
                                    @if ($canEdit || $canDelete)
                                        <td>
                                            <div class="table-actions">
                                                @if ($canEdit)
                                                    @php($editUrl = route('backend.phongban.edit', ['ma_pb' => $department->ma_pb] + $listQuery))
                                                    <a class="btn btn-outline-primary btn-icon-action" href="{{ $editUrl }}" data-action="modal" data-modal-url="{{ $editUrl }}" aria-label="Sửa {{ $department->ten_pb }}" title="Sửa {{ $department->ten_pb }}"><i class="bi bi-pencil" aria-hidden="true"></i></a>
                                                @endif
                                                @if ($canDelete && ! $hasEmployees)
                                                    <form id="{{ $deleteId }}" method="POST" action="{{ route('backend.phongban.destroy', $department->ma_pb) }}" onsubmit="return confirm('Bạn có chắc muốn xóa phòng ban này?')">
                                                        @csrf
                                                        @method('DELETE')
                                                        <button class="btn btn-outline-danger btn-icon-action" type="submit" aria-label="Xóa {{ $department->ten_pb }}" title="Xóa {{ $department->ten_pb }}"><i class="bi bi-trash" aria-hidden="true"></i></button>
                                                    </form>
                                                @elseif ($canDelete)
                                                    <button class="btn btn-outline-danger btn-icon-action" type="button" disabled aria-disabled="true" aria-label="Xóa {{ $department->ten_pb }}" title="Không thể xóa phòng ban đang có nhân viên"><i class="bi bi-trash" aria-hidden="true"></i></button>
                                                @endif
                                            </div>
                                        </td>
                                    @endif
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @elseif ($departments->total() > 0)
                <div class="card-body text-center py-5" role="status">
                    <h3 class="h6">Trang hiện tại không có dữ liệu</h3>
                    <p class="text-secondary mb-3">Danh sách có {{ number_format($departments->total(), 0, ',', '.') }} phòng ban, nhưng trang {{ $departments->currentPage() }} không chứa dòng nào.</p>
                    <a class="btn btn-outline-secondary d-inline-flex align-items-center gap-2" href="{{ $departments->url(1) }}"><i class="bi bi-arrow-left" aria-hidden="true"></i>Về trang đầu tiên</a>
                </div>
            @elseif (! $departmentError)
                <div class="card-body text-center py-5" role="status">
                    <i class="bi bi-building fs-1 text-secondary" aria-hidden="true"></i>
                    @if ($hasFilters)
                        <h3 class="h6 mt-3 mb-1">Không tìm thấy phòng ban phù hợp</h3>
                        <p class="text-secondary mb-0">Hãy điều chỉnh hoặc xóa bộ lọc để xem thêm kết quả.</p>
                    @else
                        <h3 class="h6 mt-3 mb-1">Chưa có phòng ban nào</h3>
                        <p class="text-secondary mb-0">Danh sách sẽ hiển thị khi dữ liệu phòng ban được bổ sung.</p>
                    @endif
                </div>
            @endif

            @if ($departments->hasPages())
                <div class="card-footer pagination-footer bg-white d-flex justify-content-center py-3">
                    @include('backend.partials.pagination', ['paginator' => $departments, 'label' => 'phòng ban'])
                </div>
            @endif
        </section>

        @if ($canEdit)
            @include('backend.partials.simple-edit-modal', [
                'modalId' => 'phong-ban-edit-modal',
                'title' => 'Chỉnh sửa phòng ban',
            ])
        @endif
    </main>
@endsection

@push('scripts')
    @vite('resources/js/frontend/phongban/phongban.js')
@endpush
