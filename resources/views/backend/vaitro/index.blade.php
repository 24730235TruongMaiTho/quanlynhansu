@extends('backend.layouts.app')

@section('title', 'Quản lý vai trò')

@section('content')
    <main class="container-fluid container-xxl py-4" aria-labelledby="role-page-title"
        data-role-data-url="{{ route('backend.vaitro.data') }}"
        data-role-search-url="{{ route('backend.vaitro.search') }}"
        data-role-store-url="{{ route('backend.vaitro.store') }}"
        data-role-can-create="{{ \Illuminate\Support\Facades\Gate::allows(\App\Enums\VaiTroPermission::Tao->value) ? '1' : '0' }}"
        data-role-can-edit="{{ \Illuminate\Support\Facades\Gate::allows(\App\Enums\VaiTroPermission::Sua->value) ? '1' : '0' }}"
        data-role-can-delete="{{ \Illuminate\Support\Facades\Gate::allows(\App\Enums\VaiTroPermission::Xoa->value) ? '1' : '0' }}"
        data-role-can-permission="{{ \Illuminate\Support\Facades\Gate::allows(\App\Enums\PhanQuyenPermission::Xem->value) ? '1' : '0' }}">
        <x-backend.page-header
            title="Danh sách vai trò"
            title-id="role-page-title"
            icon="bi-shield-lock"
            :breadcrumbs="[
                ['label' => 'Hệ thống', 'url' => route('backend.tongquan.index')],
                ['label' => 'Vai trò'],
            ]"
        >
            <x-slot:actions>
                @can(\App\Enums\VaiTroPermission::Tao->value)
                    <button class="btn btn-primary d-inline-flex align-items-center gap-2" type="button" data-role-create aria-label="Thêm vai trò" title="Thêm vai trò">
                        <i class="bi bi-plus-circle" aria-hidden="true"></i>Thêm vai trò
                    </button>
                @endcan
            </x-slot:actions>
        </x-backend.page-header>

        <section class="card shadow-sm mb-3 filter-card" aria-labelledby="role-filter-title">
            <div class="card-header bg-white py-3"><h2 class="h6 fw-semibold mb-0" id="role-filter-title">Bộ lọc vai trò</h2></div>
            <div class="card-body">
                <form class="filter-bar" id="role-search-form" role="search">
                    <div class="filter-bar__fields">
                        <div class="filter-bar__field">
                            <label class="form-label" for="role-search">Tìm theo tên vai trò</label>
                            <input class="form-control" id="role-search" name="ten_vt" type="search" placeholder="Tìm vai trò...">
                        </div>
                    </div>
                    <div class="filter-bar__actions">
                        <button class="btn btn-primary d-inline-flex align-items-center gap-2" type="submit"><i class="bi bi-search" aria-hidden="true"></i>Tìm kiếm</button>
                        <button class="btn btn-outline-secondary d-inline-flex align-items-center gap-2" type="reset"><i class="bi bi-arrow-counterclockwise" aria-hidden="true"></i>Đặt lại</button>
                    </div>
                </form>
            </div>
        </section>

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
            <div class="card-footer pagination-footer bg-white d-flex flex-column flex-sm-row justify-content-between align-items-sm-center gap-2 py-3">
                <small class="pagination-footer__meta text-secondary" id="role-pagination-summary" aria-live="polite"></small>
                <nav class="backend-pagination" id="role-pagination" aria-label="Phân trang danh sách vai trò">
                    <ul class="pagination pagination-sm mb-0"></ul>
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
                    <button class="btn btn-outline-secondary d-inline-flex align-items-center gap-2" type="button" data-bs-dismiss="modal"><i class="bi bi-x-lg" aria-hidden="true"></i>Hủy</button>
                    <button class="btn btn-primary d-inline-flex align-items-center gap-2" type="submit" id="role-submit"><i class="bi bi-check2" aria-hidden="true"></i>Lưu vai trò</button>
                </div>
            </form>
        </div>
    </div>
@endsection

@push('scripts')
    @vite('resources/js/frontend/vaitro/vaitro.js')
@endpush
