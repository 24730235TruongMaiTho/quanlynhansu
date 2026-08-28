@extends('backend.layouts.app')

@section('title', 'Duyệt nghỉ phép')

@section('content')
    <main class="container-fluid container-xxl py-4 leave-approval-page" aria-labelledby="leave-approval-title">
        {{-- HEADER + DEPARTMENT --}}
        <section class="d-flex flex-column flex-xl-row align-items-xl-start justify-content-between gap-3 mb-4">
            <div>
                <div class="d-flex align-items-center gap-2 mb-2 small">
                    <a href="{{ url('/user/nghi-phep') }}" class="text-primary text-decoration-none">Nghỉ phép</a>
                    <span class="text-secondary">/</span>
                    <span class="text-secondary">Duyệt nghỉ phép</span>
                </div>

                <h1 class="h3 fw-semibold mb-1" id="leave-approval-title">Duyệt nghỉ phép</h1>
                <p class="text-secondary mb-0">
                    Xem, rà soát và phê duyệt đơn nghỉ phép của nhân viên thuộc phòng ban phụ trách.
                </p>
            </div>

            <div class="leave-scope-box d-inline-flex align-items-center gap-2">
                <svg aria-hidden="true" width="18" height="18" viewBox="0 0 16 16"
                     fill="none" stroke="currentColor" stroke-width="1.4"
                     stroke-linecap="round" stroke-linejoin="round">
                    <path d="M2.5 13.5h11"/>
                    <path d="M4 13.5V3.5h5v10"/>
                    <path d="M9 6h3v7.5"/>
                    <path d="M5.5 5.5h1M5.5 8h1M5.5 10.5h1"/>
                </svg>
                <span class="small text-secondary">Phòng ban phụ trách:</span>
                <span class="badge text-bg-primary-subtle text-primary border border-primary-subtle"
                      id="leave-approval-department">Đang tải...</span>
            </div>
        </section>

        <section class="alert alert-light border shadow-sm mb-3" id="leave-approval-loading">
            <div class="d-flex align-items-center gap-2">
                <span class="spinner-border spinner-border-sm" aria-hidden="true"></span>
                <span>Đang tải danh sách nghỉ phép...</span>
            </div>
        </section>

        <section class="alert alert-danger border shadow-sm mb-3" id="leave-approval-error" hidden></section>

        {{-- FILTER --}}
        <section class="card shadow-sm mb-3" id="leave-approval-filter" hidden>
            <div class="card-body p-3">
                <div class="row g-3 align-items-end">
                    <div class="col-12 col-xl-3">
                        <label class="form-label fw-semibold small" for="leave-filter-keyword">
                            Tìm kiếm nhân viên (Tên hoặc Mã NV)
                        </label>
                        <div class="input-group input-group-sm">
                            <input class="form-control" id="leave-filter-keyword" type="search"
                                   placeholder="Nhập tên hoặc mã nhân viên..." autocomplete="off">
                            <span class="input-group-text bg-white">⌕</span>
                        </div>
                    </div>

                    <div class="col-12 col-md-4 col-xl-2">
                        <label class="form-label fw-semibold small" for="leave-filter-type">Loại nghỉ</label>
                        <select class="form-select form-select-sm" id="leave-filter-type">
                            <option value="">Tất cả</option>
                        </select>
                    </div>

                    <div class="col-12 col-md-4 col-xl-2">
                        <label class="form-label fw-semibold small" for="leave-filter-from">Từ ngày</label>
                        <input class="form-control form-control-sm" id="leave-filter-from" type="date">
                    </div>

                    <div class="col-12 col-md-4 col-xl-2">
                        <label class="form-label fw-semibold small" for="leave-filter-to">Đến ngày</label>
                        <input class="form-control form-control-sm" id="leave-filter-to" type="date">
                    </div>

                    <div class="col-12 col-xl-3">
                        <button class="btn btn-outline-secondary btn-sm w-100" id="leave-filter-reset" type="button">
                            ↻ Đặt lại
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

                    <div class="card-footer bg-white d-flex flex-column flex-lg-row
                            align-items-lg-center justify-content-between gap-3 py-3">
                    <span class="small text-secondary flex-shrink-0"
                          id="leave-pagination-info">
                        Hiển thị 0 đơn
                    </span>

                        <div class="leave-pagination-controls d-flex align-items-center gap-2">
                            <nav aria-label="Phân trang nghỉ phép"
                                 class="flex-shrink-0">
                                <ul class="pagination pagination-sm mb-0"
                                    id="leave-pagination"></ul>
                            </nav>

                            <select class="form-select form-select-sm leave-page-size"
                                    id="leave-page-size"
                                    aria-label="Số dòng mỗi trang">
                                <option value="5">5 / trang</option>
                                <option value="10" selected>10 / trang</option>
                                <option value="20">20 / trang</option>
                            </select>
                        </div>
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
                                <button class="btn btn-primary btn-sm flex-fill" id="leave-action-approve" type="button">✓ Phê duyệt</button>
                                <button class="btn btn-outline-danger btn-sm flex-fill" id="leave-action-reject" type="button">✕ Từ chối</button>
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

        .leave-approval-page .pagination .page-link {
            min-width: 32px;
            text-align: center;
        }

        .leave-approval-page .pagination .active .page-link {
            color: #fff;
            background: #0969da;
            border-color: #0969da;
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
