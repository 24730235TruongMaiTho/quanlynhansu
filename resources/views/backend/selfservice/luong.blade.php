@extends('backend.layouts.app')

@section('title', 'Lương của tôi')

@section('content')
    <main class="container-fluid container-xxl py-4" aria-labelledby="page-title">
        <x-backend.page-header
            title="Lương của tôi"
            title-id="page-title"
            icon="bi-cash-stack"
            description="Chỉ hiển thị bảng lương thuộc tài khoản đang đăng nhập."
            :breadcrumbs="[
                ['label' => 'Tổng quan', 'url' => route('backend.tongquan.index')],
                ['label' => 'Lương của tôi'],
            ]"
        />

        <section class="card shadow-sm mb-4" aria-labelledby="salary-filter-title">
            <div class="card-header bg-white py-3">
                <h2 class="h6 fw-semibold mb-0" id="salary-filter-title">Lọc kỳ lương</h2>
            </div>
            <form method="get" action="{{ route('backend.selfservice.luong.index') }}" class="card-body row g-3 align-items-end">
                <div class="col-12 col-md-4">
                    <label class="form-label fw-semibold" for="own-salary-period">Kỳ lương</label>
                    <input class="form-control" id="own-salary-period" type="month" name="ky_luong" value="{{ request('ky_luong') }}">
                </div>
                <div class="col-12 col-md-3">
                    <label class="form-label fw-semibold" for="own-salary-page-size">Số dòng</label>
                    <select class="form-select" id="own-salary-page-size" name="per_page">
                        @foreach ([10, 20, 50] as $size)
                            <option value="{{ $size }}" @selected((int) request('per_page', 10) === $size)>{{ $size }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-12 col-md-auto d-flex gap-2">
                    <button class="btn btn-primary btn-icon-text" type="submit"><i class="bi bi-funnel" aria-hidden="true"></i><span>Áp dụng</span></button>
                    <a class="btn btn-outline-secondary btn-icon-text" href="{{ route('backend.selfservice.luong.index') }}"><i class="bi bi-arrow-counterclockwise" aria-hidden="true"></i><span>Đặt lại</span></a>
                </div>
            </form>
        </section>

        @if ($error)
            <div class="alert alert-danger" role="alert">{{ $error }}</div>
        @endif

        <section class="card shadow-sm" aria-labelledby="salary-table-title">
            <div class="card-header bg-white py-3">
                <h2 class="h6 fw-semibold mb-0" id="salary-table-title">Bảng lương</h2>
            </div>
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <caption class="visually-hidden">Bảng lương của tôi</caption>
                    <thead class="table-light">
                        <tr>
                            <th scope="col">Kỳ lương</th>
                            <th scope="col">Ngày công chuẩn</th>
                            <th scope="col">Ngày công thực tế</th>
                            <th scope="col" class="text-end">Thưởng</th>
                            <th scope="col" class="text-end">Phạt</th>
                            <th scope="col" class="text-end">Bảo hiểm</th>
                            <th scope="col" class="text-end">Thuế</th>
                            <th scope="col" class="text-end">Thực nhận</th>
                            <th scope="col">Trạng thái</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($salaryRows as $row)
                            <tr>
                                <td>{{ \Carbon\Carbon::parse($row->ky_luong)->format('m/Y') }}</td>
                                <td>{{ number_format((float) ($row->so_ngay_cong_chuan ?? 0), 0, ',', '.') }}</td>
                                <td>{{ number_format((float) ($row->so_ngay_cong_thuc_te ?? 0), 1, ',', '.') }}</td>
                                <td class="text-end">{{ number_format((float) ($row->thuong ?? 0), 0, ',', '.') }} đ</td>
                                <td class="text-end">{{ number_format((float) ($row->phat ?? 0), 0, ',', '.') }} đ</td>
                                <td class="text-end">{{ number_format((float) ($row->bao_hiem ?? 0), 0, ',', '.') }} đ</td>
                                <td class="text-end">{{ number_format((float) ($row->thue ?? 0), 0, ',', '.') }} đ</td>
                                <td class="text-end">{{ number_format((float) ($row->thuc_nhan ?? 0), 0, ',', '.') }} đ</td>
                                <td>{{ $row->thong_bao_tinh_luong ?? '—' }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="9" class="text-center text-secondary py-5">Bạn chưa có dữ liệu lương.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            @if ($salaryRows instanceof \Illuminate\Contracts\Pagination\LengthAwarePaginator)
                <div class="card-footer pagination-footer bg-white d-flex flex-column flex-sm-row justify-content-between align-items-sm-center gap-2 py-3">
                    @include('backend.partials.pagination-summary', ['paginator' => $salaryRows, 'summaryLabel' => 'bản ghi lương'])
                    @include('backend.partials.pagination', ['paginator' => $salaryRows, 'label' => 'bản ghi lương'])
                </div>
            @endif
        </section>
    </main>
@endsection
