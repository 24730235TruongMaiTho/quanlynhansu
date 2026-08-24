@extends('backend.layouts.app')

@section('title', 'Quản lý vai trò')

@section('content')
    <main class="content-area" aria-labelledby="role-page-title"
        data-role-data-url="{{ route('backend.vaitro.data') }}"
        data-role-search-url="{{ route('backend.vaitro.search') }}"
        data-role-store-url="{{ route('backend.vaitro.store') }}">
        <div class="page-header">
            <div class="left">
                <div>
                    <h1 id="role-page-title">
                        <i class="bi bi-shield-lock-fill text-danger me-2"></i>
                        Danh sách vai trò
                    </h1>
                    <nav aria-label="breadcrumb">
                        <ol class="breadcrumb">
                            <li class="breadcrumb-item"><a href="{{ route('backend.tongquan.index') }}">Trang chủ</a></li>
                            <li class="breadcrumb-item"><a href="{{ route('backend.vaitro.index') }}">Vai trò</a></li>
                            <li class="breadcrumb-item active" aria-current="page">Danh sách vai trò</li>
                        </ol>
                    </nav>
                </div>
            </div>
            <div class="d-flex flex-column flex-sm-row gap-2">
                <form class="d-flex gap-2" id="role-search-form" role="search">
                    <label class="visually-hidden" for="role-search">Tìm theo tên vai trò</label>
                    <input class="form-control" id="role-search" name="ten_vt" type="search" placeholder="Tìm vai trò...">
                    <button class="btn btn-outline-secondary" type="submit" aria-label="Tìm kiếm">
                        <i class="bi bi-search" aria-hidden="true"></i>
                    </button>
                </form>
                <button class="btn btn-primary" type="button" data-role-create>
                    <i class="bi bi-plus-lg me-1" aria-hidden="true"></i> Thêm vai trò
                </button>
            </div>
        </div>

        <div id="role-feedback" aria-live="polite"></div>

        <section class="card shadow-sm" aria-labelledby="role-list-title">
            <div class="card-header bg-white py-3">
                <div class="d-flex flex-column flex-sm-row justify-content-between align-items-sm-center gap-2">
                    <small class="text-secondary" id="role-total-summary" aria-live="polite"></small>
                    <div class="d-flex align-items-center gap-2">
                        <label class="small text-secondary mb-0" for="role-page-size">Số phần tử / trang</label>
                        <select class="form-select form-select-sm w-auto pe-5" id="role-page-size">
                            <option value="5">5</option>
                            <option value="10" selected>10</option>
                            <option value="20">20</option>
                        </select>
                    </div>
                </div>
            </div>
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <caption class="visually-hidden">Danh sách vai trò</caption>
                    <thead class="table-light">
                        <tr>
                            <th scope="col">Mã</th>
                            <th scope="col">Tên vai trò</th>
                            <th scope="col">Mô tả</th>
                            <th scope="col" class="text-end">Thao tác</th>
                        </tr>
                    </thead>
                    <tbody id="role-table-body">
                        <tr><td class="text-center text-secondary py-5" colspan="4">Đang tải dữ liệu...</td></tr>
                    </tbody>
                </table>
            </div>
            <div class="card-footer bg-white d-flex flex-column flex-sm-row justify-content-between align-items-sm-center gap-2 py-3">
                <small class="text-secondary" id="role-pagination-summary" aria-live="polite"></small>
                <nav aria-label="Phân trang danh sách vai trò">
                    <ul class="pagination pagination-sm mb-0" id="role-pagination"></ul>
                </nav>
            </div>
        </section>
    </main>

    <div class="modal fade" id="role-modal" tabindex="-1" aria-labelledby="role-modal-title" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <form class="modal-content" id="role-form">
                <div class="modal-header">
                    <h2 class="modal-title h5" id="role-modal-title">Thêm vai trò</h2>
                    <button class="btn-close" type="button" data-bs-dismiss="modal" aria-label="Đóng"></button>
                </div>
                <div class="modal-body">
                    <div id="role-form-error" class="alert alert-danger d-none" role="alert"></div>
                    <div class="mb-3">
                        <label class="form-label" for="role-name">Tên vai trò <span class="text-danger">*</span></label>
                        <input class="form-control" id="role-name" name="ten_vt" maxlength="100" required>
                    </div>
                    <div>
                        <label class="form-label" for="role-description">Mô tả</label>
                        <textarea class="form-control" id="role-description" name="mo_ta" maxlength="255" rows="3"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button class="btn btn-outline-secondary" type="button" data-bs-dismiss="modal">Hủy</button>
                    <button class="btn btn-primary" type="submit" id="role-submit">Lưu vai trò</button>
                </div>
            </form>
        </div>
    </div>
@endsection

@push('scripts')
    @vite('resources/js/frontend/vaitro/vaitro.js')
@endpush

@push('styles')
    <style>
        #role-pagination {
            gap: 8px;
        }

        #role-pagination .page-item + .page-item {
            margin-left: 0;
        }

        #role-pagination .page-link {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 44px;
            height: 44px;
            padding: 0;
            border: 1px solid #e0e0e0;
            border-radius: 10px;
            color: #495057;
            background: #fff;
            box-shadow: none;
        }

        #role-pagination .page-link:hover,
        #role-pagination .page-link:focus {
            color: #e94560;
            border-color: #e94560;
            background: #fff;
            box-shadow: 0 0 0 3px rgba(233, 69, 96, 0.1);
        }

        #role-pagination .page-item.active .page-link {
            color: #fff;
            border-color: #e94560;
            background: #e94560;
        }

        #role-pagination .page-item.disabled .page-link {
            color: #adb5bd;
            border-color: #e9ecef;
            background: #fff;
            pointer-events: none;
        }

        @media (max-width: 575.98px) {
            #role-pagination .page-link {
                width: 40px;
                height: 40px;
            }
        }
    </style>
@endpush
