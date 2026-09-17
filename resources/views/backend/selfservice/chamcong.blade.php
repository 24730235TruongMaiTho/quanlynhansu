@extends('backend.layouts.app')

@section('title', 'Chấm công của tôi')

@section('content')
    <main class="container-fluid container-xxl py-4" aria-labelledby="page-title">
        <x-backend.page-header
            title="Chấm công của tôi"
            title-id="page-title"
            icon="bi-calendar-check"
            description="Chỉ hiển thị dữ liệu chấm công thuộc tài khoản đang đăng nhập."
            :breadcrumbs="[
                ['label' => 'Tổng quan', 'url' => route('backend.tongquan.index')],
                ['label' => 'Chấm công của tôi'],
            ]"
        />

        <section class="card shadow-sm mb-4" aria-labelledby="attendance-filter-title">
            <div class="card-header bg-white py-3">
                <h2 class="h6 fw-semibold mb-0" id="attendance-filter-title">Lọc kỳ chấm công</h2>
            </div>
            <form method="get" action="{{ route('backend.selfservice.chamcong.index') }}" class="card-body row g-3 align-items-end">
                <div class="col-12 col-md-3">
                    <label class="form-label fw-semibold" for="own-attendance-month">Tháng</label>
                    <select class="form-select" id="own-attendance-month" name="thang">
                        @for ($month = 1; $month <= 12; $month++)
                            <option value="{{ $month }}" @selected((int) request('thang', now()->month) === $month)>{{ $month }}</option>
                        @endfor
                    </select>
                </div>
                <div class="col-12 col-md-3">
                    <label class="form-label fw-semibold" for="own-attendance-year">Năm</label>
                    <input class="form-control" id="own-attendance-year" type="number" name="nam" min="2000" max="2100" value="{{ request('nam', now()->year) }}">
                </div>
                <div class="col-12 col-md-3">
                    <label class="form-label fw-semibold" for="own-attendance-page-size">Số dòng</label>
                    <select class="form-select" id="own-attendance-page-size" name="per_page">
                        @foreach ([10, 20, 50] as $size)
                            <option value="{{ $size }}" @selected((int) request('per_page', 10) === $size)>{{ $size }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-12 col-md-auto d-flex gap-2">
                    <button class="btn btn-primary btn-icon-text" type="submit"><i class="bi bi-funnel" aria-hidden="true"></i><span>Áp dụng</span></button>
                    <a class="btn btn-outline-secondary btn-icon-text" href="{{ route('backend.selfservice.chamcong.index') }}"><i class="bi bi-arrow-counterclockwise" aria-hidden="true"></i><span>Đặt lại</span></a>
                </div>
            </form>
        </section>

        @if ($error)
            <div class="alert alert-danger" role="alert">{{ $error }}</div>
        @endif

        <section class="row g-3 mb-4" aria-label="Tổng hợp chấm công">
            @foreach ([
                ['label' => 'Tổng giờ làm', 'value' => number_format((float) ($summary['tong_gio_lam'] ?? 0), 1, ',', '.')],
                ['label' => 'Ngày công', 'value' => number_format((float) ($summary['so_ngay_cham_cong'] ?? 0), 1, ',', '.')],
                ['label' => 'Lần vào muộn', 'value' => (int) ($summary['so_lan_vao_muon'] ?? 0)],
                ['label' => 'Lần về sớm', 'value' => (int) ($summary['so_lan_ve_som'] ?? 0)],
            ] as $card)
                <div class="col-12 col-sm-6 col-xl-3">
                    <div class="card shadow-sm h-100">
                        <div class="card-body">
                            <p class="small text-secondary mb-1">{{ $card['label'] }}</p>
                            <p class="h4 mb-0">{{ $card['value'] }}</p>
                        </div>
                    </div>
                </div>
            @endforeach
        </section>

        <section class="card shadow-sm" aria-labelledby="attendance-table-title">
            <div class="card-header bg-white py-3">
                <h2 class="h6 fw-semibold mb-1" id="attendance-table-title">Nhật ký chấm công</h2>
                <p class="small text-secondary mb-0">Chỉ hiển thị dữ liệu của tài khoản hiện tại.</p>
            </div>
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <caption class="visually-hidden">Nhật ký chấm công của tôi</caption>
                    <thead class="table-light">
                        <tr>
                            <th scope="col">Ngày làm</th>
                            <th scope="col" class="text-end">Số giờ làm</th>
                            <th scope="col">Vào muộn</th>
                            <th scope="col">Về sớm</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($attendanceRows as $row)
                            <tr>
                                <td>{{ \Carbon\Carbon::parse($row->ngay_lam)->format('d/m/Y') }}</td>
                                <td class="text-end">{{ number_format((float) $row->so_gio_lam, 1, ',', '.') }}</td>
                                <td>{{ (int) $row->vao_muon === 1 ? 'Có' : 'Không' }}</td>
                                <td>{{ (int) $row->ve_som === 1 ? 'Có' : 'Không' }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="4" class="text-center text-secondary py-5">Bạn chưa có dữ liệu chấm công.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <div class="card-footer pagination-footer bg-white d-flex flex-column flex-sm-row justify-content-between align-items-sm-center gap-2 py-3">
                @include('backend.partials.pagination-summary', ['paginator' => $attendanceRows, 'summaryLabel' => 'bản ghi chấm công'])
                @include('backend.partials.pagination', ['paginator' => $attendanceRows, 'label' => 'bản ghi chấm công'])
            </div>
        </section>
    </main>
@endsection
