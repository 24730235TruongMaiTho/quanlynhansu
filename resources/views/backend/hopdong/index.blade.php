@extends('backend.layouts.app')

@section('title', 'Quản lý hợp đồng')

@section('content')
    @php
        $canEdit = \Illuminate\Support\Facades\Gate::allows(\App\Enums\HopDongPermission::Sua->value);
        $canDelete = \Illuminate\Support\Facades\Gate::allows(\App\Enums\HopDongPermission::Xoa->value);
        $hasFilters = filled(request('keyword')) || filled(request('ma_lhd')) || request()->boolean('sap_het_han');
    @endphp

    <main class="container-fluid container-xxl py-4" aria-labelledby="contract-title">
        <nav class="mb-3" aria-label="Đường dẫn trang">
            <ol class="breadcrumb mb-0">
                <li class="breadcrumb-item">Nhân sự</li>
                <li class="breadcrumb-item active" aria-current="page">Quản lý hợp đồng</li>
            </ol>
        </nav>

        <header class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3 mb-4">
            <div>
                <h1 class="h3 fw-semibold mb-1" id="contract-title">Danh sách hợp đồng</h1>
                <p class="text-secondary mb-0">Theo dõi hợp đồng và cảnh báo hết hạn trong {{ config('hopdong.expiring_warning_days', 30) }} ngày.</p>
            </div>
            @can(\App\Enums\HopDongPermission::Tao->value)
                <a class="btn btn-primary" href="{{ route('backend.hopdong.create') }}">
                    <i class="bi bi-plus-lg" aria-hidden="true"></i> Thêm hợp đồng
                </a>
            @endcan
        </header>

        @if (session('success'))
            <div class="alert alert-success" role="status"><i class="bi bi-check-circle-fill me-2" aria-hidden="true"></i>{{ session('success') }}</div>
        @endif
        @if ($errors->any())
            <div class="alert alert-danger" role="alert">{{ $errors->first() }}</div>
        @endif

        <section class="card shadow-sm mb-3" aria-labelledby="contract-filter-title">
            <div class="card-header bg-white py-3">
                <h2 class="h6 fw-semibold mb-0" id="contract-filter-title">Bộ lọc hợp đồng</h2>
            </div>
            <div class="card-body">
                <form method="get" action="{{ route('backend.hopdong.index') }}">
                    <div class="row g-3 align-items-end">
                        <div class="col-12 col-lg-4">
                            <label class="form-label" for="keyword">Nhân viên</label>
                            <input class="form-control" id="keyword" name="keyword" type="search" maxlength="100" value="{{ request('keyword') }}" placeholder="Mã hoặc tên nhân viên">
                        </div>
                        <div class="col-12 col-sm-6 col-lg-3">
                            <label class="form-label" for="ma_lhd">Loại hợp đồng</label>
                            <select class="form-select" id="ma_lhd" name="ma_lhd">
                                <option value="">Tất cả loại hợp đồng</option>
                                @foreach ($types as $type)
                                    <option value="{{ $type->ma_lhd }}" @selected((string) request('ma_lhd') === (string) $type->ma_lhd)>{{ $type->ten_lhd }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-12 col-sm-6 col-lg-2">
                            <label class="form-label" for="per_page">Số dòng</label>
                            <select class="form-select" id="per_page" name="per_page">
                                @foreach ([5, 10, 20, 50, 100] as $pageSize)
                                    <option value="{{ $pageSize }}" @selected((int) request('per_page', 20) === $pageSize)>{{ $pageSize }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-12 col-lg-3">
                            <div class="form-check mb-2">
                                <input class="form-check-input" id="sap_het_han" name="sap_het_han" type="checkbox" value="1" @checked(request()->boolean('sap_het_han'))>
                                <label class="form-check-label" for="sap_het_han">Chỉ xem hợp đồng sắp hết hạn</label>
                            </div>
                            <div class="d-flex flex-wrap gap-2">
                                <button class="btn btn-success" type="submit">Lọc</button>
                                @if ($hasFilters)<a class="btn btn-outline-secondary" href="{{ route('backend.hopdong.index') }}">Xóa lọc</a>@endif
                            </div>
                        </div>
                    </div>
                </form>
            </div>
        </section>

        <section class="card shadow-sm overflow-hidden" aria-labelledby="contract-table-title">
            <div class="card-header bg-white py-3">
                <h2 class="h6 fw-semibold mb-1" id="contract-table-title">Kết quả tra cứu</h2>
                @include('backend.partials.pagination-summary', ['paginator' => $contracts, 'summaryLabel' => 'hợp đồng'])
            </div>

            @if ($contracts->count() > 0)
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <caption class="visually-hidden">Danh sách hợp đồng theo bộ lọc hiện tại</caption>
                        <thead class="table-light">
                            <tr>
                                <th scope="col">#</th>
                                <th scope="col">Mã</th>
                                <th scope="col">Nhân viên</th>
                                <th scope="col">Loại hợp đồng</th>
                                <th scope="col">Ngày ký</th>
                                <th scope="col">Ngày hết hạn</th>
                                @if ($canEdit || $canDelete)<th scope="col">Thao tác</th>@endif
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($contracts as $contract)
                                @php($deleteId = 'contract-delete-' . (int) $contract->ma_hd)
                                <tr class="{{ $contract->sap_het_han ? 'table-warning' : '' }}">
                                    <td>{{ ($contracts->firstItem() ?? 0) + $loop->index }}</td>
                                    <th scope="row"><span class="badge bg-primary">{{ $contract->ma_hd }}</span></th>
                                    <td><span class="fw-semibold">{{ $contract->ho_ten }}</span><small class="d-block text-secondary">{{ $contract->ma_nv }}</small></td>
                                    <td>{{ $contract->ten_lhd }}</td>
                                    <td>{{ $contract->ngay_ky }}</td>
                                    <td>
                                        {{ $contract->ngay_het_han ?? 'Không thời hạn' }}
                                        @if ($contract->sap_het_han)<span class="badge text-bg-warning ms-1">Sắp hết hạn</span>@endif
                                    </td>
                                    @if ($canEdit || $canDelete)
                                        <td>
                                            <label class="visually-hidden" for="contract-action-{{ $contract->ma_hd }}">Thao tác với hợp đồng {{ $contract->ma_hd }}</label>
                                            <select class="form-select form-select-sm" id="contract-action-{{ $contract->ma_hd }}" data-row-action-select>
                                                <option value="">Chọn thao tác</option>
                                                @if ($canEdit)<option value="{{ route('backend.hopdong.edit', $contract->ma_hd) }}" data-action="navigate">Sửa</option>@endif
                                                @if ($canDelete)<option value="delete" data-action="delete" data-form-id="{{ $deleteId }}" data-confirm-message="Bạn có chắc muốn xóa hợp đồng này?">Xóa</option>@endif
                                            </select>
                                            @if ($canDelete)
                                                <form class="d-none" id="{{ $deleteId }}" method="post" action="{{ route('backend.hopdong.destroy', $contract->ma_hd) }}">@csrf @method('DELETE')</form>
                                            @endif
                                        </td>
                                    @endif
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @else
                <div class="card-body text-center py-5" role="status">
                    <i class="bi bi-file-earmark-text fs-1 text-secondary" aria-hidden="true"></i>
                    <h3 class="h6 mt-3 mb-1">{{ $hasFilters ? 'Không tìm thấy hợp đồng phù hợp' : 'Chưa có hợp đồng nào' }}</h3>
                    <p class="text-secondary mb-0">{{ $hasFilters ? 'Hãy điều chỉnh hoặc xóa bộ lọc để xem thêm kết quả.' : 'Danh sách sẽ hiển thị khi dữ liệu hợp đồng được bổ sung.' }}</p>
                </div>
            @endif

            @if ($contracts->hasPages())
                <div class="card-footer bg-white d-flex justify-content-center py-3">
                    @include('backend.partials.pagination', ['paginator' => $contracts, 'label' => 'hợp đồng'])
                </div>
            @endif
        </section>
    </main>
@endsection

@push('scripts')
    @vite('resources/js/frontend/hopdong/hopdong.js')
@endpush
