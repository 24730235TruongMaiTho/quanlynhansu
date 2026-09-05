@extends('backend.layouts.app')

@section('title', 'Quản lý chức vụ')

@section('content')
    @php
        $canCreate = \Illuminate\Support\Facades\Gate::allows(\App\Enums\ChucVuPermission::Tao->value);
        $canEdit = \Illuminate\Support\Facades\Gate::allows(\App\Enums\ChucVuPermission::Sua->value);
        $canDelete = \Illuminate\Support\Facades\Gate::allows(\App\Enums\ChucVuPermission::Xoa->value);
        $hasFilters = filled($filters['ten_cv']);
        $listQuery = array_filter([
            'ten_cv' => $filters['ten_cv'],
            'page' => $filters['page'] > 1 ? $filters['page'] : null,
            'so_dong' => $filters['so_dong'] !== 20 ? $filters['so_dong'] : null,
        ], static fn (mixed $value): bool => $value !== null && $value !== '');
    @endphp

    <main class="container-fluid container-xxl py-4" aria-labelledby="position-page-title">
        <x-backend.page-header
            title="Danh sách chức vụ"
            title-id="position-page-title"
            icon="bi-person-badge"
            description="Quản lý thông tin chức vụ và hệ số phụ cấp."
            :breadcrumbs="[
                ['label' => 'Nhân sự', 'url' => route('backend.tongquan.index')],
                ['label' => 'Quản lý chức vụ'],
            ]"
        >
            <x-slot:actions>
            @if ($canCreate)
                <a class="btn btn-primary d-inline-flex align-items-center gap-2" aria-label="Thêm chức vụ" title="Thêm chức vụ" href="{{ route('backend.chucvu.create') }}">
                    <i class="bi bi-plus-circle" aria-hidden="true"></i>Thêm chức vụ
                </a>
            @endif
            </x-slot:actions>
        </x-backend.page-header>

        @if (session('success'))
            <div class="alert alert-success" role="status">{{ session('success') }}</div>
        @endif
        @if ($errors->any())
            <div class="alert alert-danger" role="alert">
                @foreach ($errors->all() as $error)
                    <div>{{ $error }}</div>
                @endforeach
            </div>
        @endif
        @if ($positionError)
            <div class="alert alert-warning" role="alert">
                <i class="bi bi-exclamation-triangle-fill me-2" aria-hidden="true"></i>{{ $positionError }}
            </div>
        @endif

        <section class="card shadow-sm mb-3 filter-card" aria-labelledby="position-filter-title">
            <div class="card-header bg-white py-3">
                <h2 class="h6 fw-semibold mb-0" id="position-filter-title">Bộ lọc chức vụ</h2>
            </div>
            <div class="card-body">
                <form method="GET" action="{{ route('backend.chucvu.index') }}" aria-busy="false" data-position-filter class="filter-bar">
                    <div class="filter-bar__fields">
                        <div class="filter-bar__field">
                            <label class="form-label" for="ten_cv">Tìm theo tên chức vụ</label>
                            <input class="form-control" id="ten_cv" name="ten_cv" type="search" maxlength="100" value="{{ $filters['ten_cv'] ?? '' }}" placeholder="Nhập tên chức vụ">
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
                                <a class="btn btn-outline-secondary d-inline-flex align-items-center gap-2" href="{{ route('backend.chucvu.index') }}"><i class="bi bi-arrow-counterclockwise" aria-hidden="true"></i>Xóa</a>
                            @endif
                    </div>
                </form>
            </div>
        </section>

        <section class="card shadow-sm overflow-hidden" aria-labelledby="position-table-title">
            <div class="card-header bg-white d-flex flex-column flex-sm-row align-items-sm-center justify-content-between gap-2 py-3">
                <div>
                    <h2 class="h6 fw-semibold mb-1" id="position-table-title">Kết quả tra cứu</h2>
                    @include('backend.partials.pagination-summary', ['paginator' => $positions, 'summaryLabel' => 'chức vụ'])
                </div>
            </div>

            @if (! $positionError && $positions->count() > 0)
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <caption class="visually-hidden">Danh sách chức vụ theo bộ lọc hiện tại</caption>
                        <thead class="table-light">
                            <tr>
                                <th scope="col">#</th>
                                <th scope="col">Mã chức vụ</th>
                                <th scope="col">Tên chức vụ</th>
                                <th scope="col">Hệ số phụ cấp</th>
                                <th scope="col">Số nhân viên</th>
                                @if ($canEdit || $canDelete)
                                    <th scope="col">Thao tác</th>
                                @endif
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($positions as $position)
                                @php
                                    $hasEmployees = (int) ($position->so_nhan_vien ?? 0) > 0;
                                    $deleteId = 'chucvu-delete-' . (int) $position->ma_cv;
                                @endphp
                                <tr>
                                    <td>{{ ($positions->firstItem() ?? 0) + $loop->index }}</td>
                                    <th scope="row"><span class="identifier-text">{{ $position->ma_cv }}</span></th>
                                    <td>{{ $position->ten_cv }}</td>
                                    <td>{{ number_format((float) $position->he_so_phu_cap, 2, '.', ',') }}</td>
                                    <td>{{ $hasEmployees ? $position->so_nhan_vien : 'Chưa có nhân viên' }}</td>
                                    @if ($canEdit || $canDelete)
                                        <td>
                                            <div class="table-actions">
                                                @if ($canEdit)
                                                    @php($editUrl = route('backend.chucvu.edit', ['ma_cv' => $position->ma_cv] + $listQuery))
                                                    <a class="btn btn-outline-primary btn-icon-action" href="{{ $editUrl }}" data-action="modal" data-modal-url="{{ $editUrl }}" aria-label="Sửa {{ $position->ten_cv }}" title="Sửa {{ $position->ten_cv }}"><i class="bi bi-pencil-square" aria-hidden="true"></i></a>
                                                @endif
                                                @if ($canDelete && ! $hasEmployees)
                                                    <form id="{{ $deleteId }}" method="POST" action="{{ route('backend.chucvu.destroy', $position->ma_cv) }}" onsubmit="return confirm('Bạn có chắc muốn xóa chức vụ này?')">
                                                        @csrf
                                                        @method('DELETE')
                                                        <button class="btn btn-outline-danger btn-icon-action" type="submit" aria-label="Xóa {{ $position->ten_cv }}" title="Xóa {{ $position->ten_cv }}"><i class="bi bi-trash" aria-hidden="true"></i></button>
                                                    </form>
                                                @elseif ($canDelete)
                                                    <button class="btn btn-outline-danger btn-icon-action" type="button" disabled aria-disabled="true" aria-label="Xóa {{ $position->ten_cv }}" title="Không thể xóa chức vụ đang có nhân viên"><i class="bi bi-trash" aria-hidden="true"></i></button>
                                                @endif
                                            </div>
                                        </td>
                                    @endif
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @elseif ($positions->total() > 0)
                <div class="card-body text-center py-5" role="status">
                    <h3 class="h6">Trang hiện tại không có dữ liệu</h3>
                    <p class="text-secondary mb-3">Danh sách có {{ number_format($positions->total(), 0, ',', '.') }} chức vụ, nhưng trang {{ $positions->currentPage() }} không chứa dòng nào.</p>
                    <a class="btn btn-outline-secondary d-inline-flex align-items-center gap-2" href="{{ $positions->url(1) }}"><i class="bi bi-arrow-left" aria-hidden="true"></i>Về trang đầu tiên</a>
                </div>
            @elseif (! $positionError)
                <div class="card-body text-center py-5" role="status">
                    <i class="bi bi-briefcase fs-1 text-secondary" aria-hidden="true"></i>
                    @if ($hasFilters)
                        <h3 class="h6 mt-3 mb-1">Không tìm thấy chức vụ phù hợp</h3>
                        <p class="text-secondary mb-0">Hãy điều chỉnh hoặc xóa bộ lọc để xem thêm kết quả.</p>
                    @else
                        <h3 class="h6 mt-3 mb-1">Chưa có chức vụ nào</h3>
                        <p class="text-secondary mb-0">Danh sách sẽ hiển thị khi dữ liệu chức vụ được bổ sung.</p>
                    @endif
                </div>
            @endif

            @if ($positions->hasPages())
                <div class="card-footer pagination-footer bg-white d-flex justify-content-center py-3">
                    @include('backend.partials.pagination', ['paginator' => $positions, 'label' => 'chức vụ'])
                </div>
            @endif
        </section>

        @if ($canEdit)
            @include('backend.partials.simple-edit-modal', [
                'modalId' => 'chuc-vu-edit-modal',
                'title' => 'Chỉnh sửa chức vụ',
            ])
        @endif
    </main>
@endsection

@push('scripts')
    @vite('resources/js/frontend/chucvu/chucvu.js')
@endpush
