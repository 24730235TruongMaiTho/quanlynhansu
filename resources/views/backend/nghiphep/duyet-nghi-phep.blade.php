@extends('backend.layouts.app')

@section('title', 'Duyệt nghỉ phép')

@section('content')
    <main class="container-fluid container-xxl py-4 leave-approval-page" aria-labelledby="leave-approval-title">
        {{-- HEADER + DEPARTMENT --}}
        <x-backend.page-header
            title="Duyệt nghỉ phép"
            title-id="leave-approval-title"
            icon="bi-clipboard-check"
            description="Xem, rà soát và phê duyệt đơn nghỉ phép của nhân viên thuộc phòng ban phụ trách."
            :breadcrumbs="[
                ['label' => 'Nghỉ phép', 'url' => route('backend.nghiphep.index')],
                ['label' => 'Duyệt nghỉ phép'],
            ]"
        >
            <x-slot:actions>
                <div class="leave-scope-box d-inline-flex align-items-center gap-2">
                    <i class="bi bi-building button-icon" aria-hidden="true"></i>
                    <span class="small text-secondary">Phòng ban phụ trách:</span>
                    <span class="badge text-bg-primary-subtle text-primary border border-primary-subtle" id="leave-approval-department">Đang tải...</span>
                </div>
            </x-slot:actions>
        </x-backend.page-header>

        <section class="alert alert-light border shadow-sm mb-3" id="leave-approval-loading">
            <div class="d-flex align-items-center gap-2">
                <span class="spinner-border spinner-border-sm" aria-hidden="true"></span>
                <span>Đang tải danh sách nghỉ phép...</span>
            </div>
        </section>

        <section class="alert alert-danger border shadow-sm mb-3" id="leave-approval-error" hidden></section>

        {{-- FILTER --}}
        <section class="card shadow-sm mb-3 filter-card" id="leave-approval-filter" aria-labelledby="leave-approval-filter-title" hidden>
            <div class="card-header bg-white py-3">
                <h2 class="h6 fw-semibold mb-0" id="leave-approval-filter-title">Bộ lọc duyệt nghỉ phép</h2>
            </div>
            <div class="card-body">
                <div class="filter-bar filter-bar--embedded">
                    <div class="filter-bar__fields">
                    <div class="filter-bar__field filter-bar__field--wide">
                        <label class="form-label fw-semibold" for="leave-filter-keyword">
                            Tìm kiếm nhân viên (Tên hoặc Mã NV)
                        </label>
                        <div class="input-group">
                            <input class="form-control" id="leave-filter-keyword" type="search"
                                   placeholder="Nhập tên hoặc mã nhân viên..." autocomplete="off">
                            <span class="input-group-text bg-white"><i class="bi bi-search" aria-hidden="true"></i></span>
                        </div>
                    </div>

                    <div class="filter-bar__field filter-bar__field--compact">
                        <label class="form-label fw-semibold" for="leave-filter-type">Loại nghỉ</label>
                        <select class="form-select" id="leave-filter-type">
                            <option value="">Tất cả</option>
                        </select>
                    </div>

                    <div class="filter-bar__field filter-bar__field--compact">
                        <label class="form-label fw-semibold" for="leave-filter-from">Từ ngày</label>
                        <input class="form-control" id="leave-filter-from" type="text"
                               placeholder="dd/mm/yyyy" inputmode="numeric" maxlength="10"
                               aria-describedby="leave-filter-from-error">
                        <div class="invalid-feedback" id="leave-filter-from-error" role="alert"></div>
                    </div>

                    <div class="filter-bar__field filter-bar__field--compact">
                        <label class="form-label fw-semibold" for="leave-filter-to">Đến ngày</label>
                        <input class="form-control" id="leave-filter-to" type="text"
                               placeholder="dd/mm/yyyy" inputmode="numeric" maxlength="10"
                               aria-describedby="leave-filter-to-error">
                        <div class="invalid-feedback" id="leave-filter-to-error" role="alert"></div>
                    </div>

                    </div>
                    <div class="filter-bar__actions">
                        <button class="btn btn-primary" id="leave-filter-apply" type="button">
                            <i class="bi bi-funnel button-icon" aria-hidden="true"></i>Áp dụng bộ lọc
                        </button>
                        <button class="btn btn-outline-secondary" id="leave-filter-reset" type="button">
                            <i class="bi bi-arrow-counterclockwise button-icon" aria-hidden="true"></i>Đặt lại
                        </button>
                    </div>
                </div>
            </div>
        </section>

        {{-- LIST + DETAIL --}}
        <section class="row g-3" id="leave-approval-main" hidden>
            <div class="col-12 col-xl-7">
                <div class="card shadow-sm h-100">
                    <div class="card-header bg-white px-3 pt-3 pb-0">
                        <h2 class="h6 fw-semibold mb-2">Danh sách nghỉ phép</h2>
                        <ul class="nav nav-tabs border-0 leave-approval-tabs" role="tablist">
                            <li class="nav-item">
                                <button class="nav-link active" id="leave-tab-pending" type="button"
                                        data-tab="pending" aria-selected="true">Chờ duyệt</button>
                            </li>
                            <li class="nav-item">
                                <button class="nav-link" id="leave-tab-processed" type="button"
                                        data-tab="processed" aria-selected="false">Đã xử lý</button>
                            </li>
                        </ul>
                    </div>

                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0 leave-approval-table">
                            <thead class="table-light">
                            <tr>
                                <th style="width:38px"></th>
                                <th>Mã đơn</th>
                                <th>Nhân viên</th>
                                <th>Loại nghỉ</th>
                                <th>Từ ngày</th>
                                <th>Đến ngày</th>
                                <th class="text-center">Số ngày</th>
                                <th>Trạng thái</th>
                            </tr>
                            </thead>
                            <tbody id="leave-approval-tbody">
                            <tr>
                                <td colspan="8" class="text-center text-secondary py-5">Đang tải dữ liệu...</td>
                            </tr>
                            </tbody>
                        </table>
                    </div>

                    <div class="card-footer pagination-footer bg-white d-flex flex-column flex-lg-row
                            align-items-lg-center justify-content-between gap-3 py-3">
                    <span class="pagination-footer__meta small text-secondary flex-shrink-0"
                          id="leave-pagination-info">
                        Hiển thị 0 đơn
                    </span>

                        <nav aria-label="Phân trang nghỉ phép"
                             class="backend-pagination"
                             id="leave-pagination"></nav>

                        <select class="form-select form-select-sm leave-page-size pagination-footer__size"
                                id="leave-page-size"
                                aria-label="Số dòng mỗi trang">
                                <option value="10" selected>10 / trang</option>
                                <option value="20">20 / trang</option>
                                <option value="50">50 / trang</option>
                        </select>
                    </div>
                </div>
            </div>

            <div class="col-12 col-xl-5">
                <div class="card shadow-sm h-100">
                    <div class="card-body p-3">
                        <h2 class="h6 fw-semibold mb-3">Chi tiết đơn nghỉ phép</h2>

                        <div id="leave-detail-empty" class="text-center text-secondary py-5">
                            Chọn một đơn nghỉ phép để xem chi tiết.
                        </div>

                        <div id="leave-detail-content" hidden>
                            <dl class="row g-2 mb-3 leave-detail-list">
                                <dt class="col-5">Mã nhân viên</dt><dd class="col-7" id="detail-ma-nv">—</dd>
                                <dt class="col-5">Họ tên</dt><dd class="col-7" id="detail-ho-ten">—</dd>
                                <dt class="col-5">Phòng ban</dt><dd class="col-7" id="detail-phong-ban">—</dd>
                                <dt class="col-5">Chức vụ</dt><dd class="col-7" id="detail-chuc-vu">—</dd>
                                <dt class="col-5">Loại nghỉ</dt><dd class="col-7" id="detail-loai-nghi">—</dd>
                                <dt class="col-5">Thời gian nghỉ</dt><dd class="col-7" id="detail-thoi-gian">—</dd>
                                <dt class="col-5">Lý do nghỉ</dt><dd class="col-7" id="detail-ly-do">—</dd>
                                <dt class="col-5">Người tạo</dt><dd class="col-7" id="detail-nguoi-tao">—</dd>
                                <dt class="col-5">Trạng thái hiện tại</dt><dd class="col-7" id="detail-trang-thai">—</dd>
                            </dl>

                            <div class="mb-3">
                                <label class="form-label fw-semibold small" for="leave-approval-note">Ghi chú phê duyệt</label>
                                <textarea class="form-control form-control-sm" id="leave-approval-note"
                                          rows="4" maxlength="500" placeholder="Nhập ghi chú (nếu có)..."></textarea>
                                <div class="form-text text-end" id="leave-approval-note-counter">0 / 500</div>
                            </div>

                            <div class="d-flex flex-wrap gap-2">
                                <button class="btn btn-primary btn-sm flex-fill" id="leave-action-approve" type="button"><i class="bi bi-check-circle button-icon" aria-hidden="true"></i>Phê duyệt</button>
                                <button class="btn btn-outline-danger btn-sm flex-fill" id="leave-action-reject" type="button"><i class="bi bi-x-circle button-icon" aria-hidden="true"></i>Từ chối</button>
                            </div>

                            <div class="alert alert-primary-subtle border border-primary-subtle small mt-3 mb-0 py-2">
                                Trưởng phòng chỉ có thể thực hiện thao tác trên các đơn ở tab <strong>“Chờ duyệt”</strong>.
                            </div>

                            <div class="alert alert-success small mt-3 mb-0 py-2" id="leave-action-success" hidden></div>
                            <div class="alert alert-danger small mt-3 mb-0 py-2" id="leave-action-error" hidden></div>
                        </div>
                    </div>
                </div>
            </div>
        </section>
    </main>

    <style>
        .leave-approval-page { color:#24292f; }
        .leave-approval-page .leave-scope-box {
            padding:10px 12px; border:1px solid #d8dee4; border-radius:8px; background:#fff; white-space:nowrap;
        }
        .leave-approval-page .card { border-color:#d8dee4; border-radius:10px; overflow:hidden; }
        .leave-approval-page .form-control,
        .leave-approval-page .form-select,
        .leave-approval-page .input-group-text,
        .leave-approval-page .btn { min-height:36px; border-color:#d0d7de; font-size:.8125rem; }
        .leave-approval-page .filter-card .form-control,
        .leave-approval-page .filter-card .form-select,
        .leave-approval-page .filter-card .input-group-text,
        .leave-approval-page .filter-card .btn {
            min-height: 2.375rem;
            font-size: 1rem;
        }
        .leave-approval-page .leave-approval-tabs .nav-link {
            padding:10px 14px; color:#57606a; border:0; border-bottom:2px solid transparent;
            border-radius:0; background:transparent; font-size:.8125rem; font-weight:600;
        }
        .leave-approval-page .leave-approval-tabs .nav-link.active { color:#0969da; border-bottom-color:#0969da; }
        .leave-approval-page .leave-approval-table { min-width:900px; font-size:.78rem; }
        .leave-approval-page .leave-approval-table thead th {
            padding:10px 8px; color:#57606a; font-size:.72rem; font-weight:700; white-space:nowrap;
        }
        .leave-approval-page .leave-approval-table tbody td { padding:10px 8px; border-bottom-color:#eaeef2; }
        .leave-approval-page .leave-approval-row { cursor:pointer; }
        .leave-approval-page .leave-approval-row.is-selected > * { background:#ddf4ff !important; }
        .leave-approval-page .leave-detail-list { font-size:.8125rem; }
        .leave-approval-page .leave-detail-list dt { color:#57606a; font-weight:600; }
        .leave-approval-page .leave-detail-list dd { margin-bottom:0; overflow-wrap:anywhere; }
        .leave-approval-page #leave-approval-note { min-height:100px; resize:vertical; }
        .leave-approval-page .leave-pagination-controls {
            flex: 0 0 auto;
            flex-wrap: wrap;
            justify-content: flex-end;
            min-width: 0;
        }

        .leave-approval-page .leave-pagination-controls .pagination {
            flex: 0 0 auto;
            flex-wrap: nowrap;
        }

        .leave-approval-page .leave-page-size {
            width: 122px;
            min-width: 122px;
            flex: 0 0 122px;
            padding-right: 2rem;
            white-space: nowrap;
        }

        @media (max-width: 991.98px) {
            .leave-approval-page .leave-pagination-controls {
                width: 100%;
                justify-content: flex-start;
            }
        }

        @media (max-width: 575.98px) {
            .leave-approval-page .leave-pagination-controls {
                align-items: flex-start !important;
            }

            .leave-approval-page .leave-page-size {
                width: 116px;
                min-width: 116px;
                flex-basis: 116px;
            }
        }
    </style>
@endsection

@push('scripts')
    @vite('resources/js/frontend/nghiphep/duyet-nghi-phep.js')
@endpush
