@extends('backend.layouts.app')

@section('title', 'Hợp đồng của tôi')

@section('content')
    <main class="container-fluid container-xxl py-4" aria-labelledby="page-title">
        <x-backend.page-header
            title="Hợp đồng của tôi"
            title-id="page-title"
            icon="bi-file-earmark-text"
            description="Danh sách hợp đồng thuộc tài khoản đang đăng nhập."
            :breadcrumbs="[
                ['label' => 'Tổng quan', 'url' => route('backend.tongquan.index')],
                ['label' => 'Hợp đồng của tôi'],
            ]"
        />

        <section class="card shadow-sm" aria-labelledby="contracts-heading">
            <div class="card-header bg-white py-3">
                <h2 class="h6 fw-semibold mb-1" id="contracts-heading">Danh sách hợp đồng</h2>
                <p class="small text-secondary mb-0">Chỉ hiển thị dữ liệu của tài khoản hiện tại.</p>
            </div>

            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <caption class="visually-hidden">Danh sách hợp đồng của tôi</caption>
                    <thead class="table-light">
                        <tr>
                            <th scope="col">Loại hợp đồng</th>
                            <th scope="col">Ngày ký</th>
                            <th scope="col">Ngày hết hạn</th>
                            <th scope="col" class="text-end">Lương cơ bản</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($contracts as $contract)
                            <tr>
                                <td>{{ $contract->ten_lhd }}</td>
                                <td>{{ \Carbon\Carbon::parse($contract->ngay_ky)->format('d/m/Y') }}</td>
                                <td>{{ $contract->ngay_het_han ? \Carbon\Carbon::parse($contract->ngay_het_han)->format('d/m/Y') : 'Không thời hạn' }}</td>
                                <td class="text-end">{{ number_format((float) $contract->luong_co_ban, 0, ',', '.') }} đ</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="4" class="text-center text-secondary py-5">Bạn chưa có hợp đồng.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <div class="card-footer pagination-footer bg-white d-flex flex-column flex-sm-row justify-content-between align-items-sm-center gap-2 py-3">
                @include('backend.partials.pagination-summary', ['paginator' => $contracts, 'summaryLabel' => 'hợp đồng'])
                @include('backend.partials.pagination', ['paginator' => $contracts, 'label' => 'hợp đồng'])
            </div>
        </section>
    </main>
@endsection
