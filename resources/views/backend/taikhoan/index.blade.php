@extends('backend.layouts.app')

@section('title', 'Phân Quyền tài khoản')

@section('content')
    @php
        $accountSort = request('sort', 'ma_nv');
        $accountDirection = request('direction', 'desc');
        $accountListQuery = array_filter(request()->only(['tu_khoa', 'per_page', 'sort', 'direction']), static fn (mixed $value): bool => $value !== null && $value !== '');
        $accountSortUrl = static function (string $column) use ($accountSort, $accountDirection, $accountListQuery): string {
            $next = $accountSort === $column && $accountDirection === 'asc' ? 'desc' : 'asc';
            return route('backend.taikhoan.index', array_merge($accountListQuery, ['sort' => $column, 'direction' => $next, 'page' => 1]));
        };
    @endphp
    <main class="container-fluid container-xxl py-4" aria-labelledby="account-role-title">
        <x-backend.page-header
            title="Phân Quyền tài khoản"
            title-id="account-role-title"
            icon="bi-person-lock"
            description="Chọn vai trò cho nhân viên; quyền được cấu hình theo vai trò, không thiết lập trực tiếp tại đây."
            :breadcrumbs="[
                ['label' => 'Hệ thống', 'url' => route('backend.tongquan.index')],
                ['label' => 'Phân Quyền tài khoản'],
            ]"
        >
            <x-slot:actions>
            @can(\App\Enums\VaiTroPermission::Xem->value)
                <a class="btn btn-outline-primary btn-icon-text" href="{{ route('backend.vaitro.index') }}"><i class="bi bi-shield-check" aria-hidden="true"></i><span>Quản lý vai trò và quyền</span></a>
            @endcan
            </x-slot:actions>
        </x-backend.page-header>

        <div class="alert alert-info d-flex gap-3 align-items-start" role="note">
            <i class="bi bi-info-circle-fill" aria-hidden="true"></i>
            <div><strong>Quyền được cấu hình theo vai trò.</strong> Muốn thay đổi hành động được phép trên một module, hãy mở Danh sách vai trò và chọn “Phân quyền”.</div>
        </div>

        @if (session('success'))<div class="alert alert-success" role="status">{{ session('success') }}</div>@endif
        @if ($errors->any())<div class="alert alert-danger" role="alert">{{ $errors->first() }}</div>@endif

        <section class="card shadow-sm mb-3 filter-card" aria-labelledby="account-filter-title">
            <div class="card-header bg-white py-3"><h2 class="h6 fw-semibold mb-0" id="account-filter-title">Tìm tài khoản</h2></div>
            <div class="card-body">
                <form method="get" action="{{ route('backend.taikhoan.index') }}" class="filter-bar">
                    <div class="filter-bar__fields">
                        <div class="filter-bar__field">
                            <label class="form-label" for="tu_khoa">Mã hoặc tên nhân viên</label>
                            <input class="form-control" id="tu_khoa" name="tu_khoa" type="search" maxlength="100" value="{{ request('tu_khoa') }}" placeholder="Ví dụ: 00001 hoặc Nguyễn Văn An">
                        </div>
                    </div>
                    <div class="filter-bar__actions">
                        <button class="btn btn-primary btn-icon-text" type="submit"><i class="bi bi-search" aria-hidden="true"></i><span>Áp dụng bộ lọc</span></button>
                        @if (filled(request('tu_khoa')))<a class="btn btn-outline-secondary btn-icon-text" href="{{ route('backend.taikhoan.index') }}"><i class="bi bi-arrow-counterclockwise" aria-hidden="true"></i><span>Đặt lại</span></a>@endif
                    </div>
                </form>
            </div>
        </section>

        <section class="card shadow-sm overflow-hidden" aria-labelledby="account-table-title">
            <div class="card-header bg-white py-3">
                <h2 class="h6 fw-semibold mb-1" id="account-table-title">Danh sách tài khoản</h2>
                <p class="small text-secondary mb-0">Tìm thấy {{ number_format($accounts->total(), 0, ',', '.') }} tài khoản.</p>
            </div>
            @if ($accounts->count() > 0)
                <form method="POST" action="{{ route('backend.taikhoan.assign-roles', array_filter(request()->only(['tu_khoa', 'page', 'per_page', 'sort', 'direction']), static fn (mixed $value): bool => $value !== null && $value !== '')) }}">
                    @csrf
                    @method('PATCH')
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <caption class="visually-hidden">Danh sách tài khoản và vai trò hiện tại</caption>
                        <thead class="table-light">
                            <tr>
                                <th scope="col" aria-sort="{{ $accountSort === 'ma_nv' ? ($accountDirection === 'asc' ? 'ascending' : 'descending') : 'none' }}"><x-backend.table-sort column="ma_nv" label="Mã" :sort="$accountSort" :direction="$accountDirection" :href="$accountSortUrl('ma_nv')" /></th>
                                <th scope="col" aria-sort="{{ $accountSort === 'ho_ten' ? ($accountDirection === 'asc' ? 'ascending' : 'descending') : 'none' }}"><x-backend.table-sort column="ho_ten" label="Nhân viên" :sort="$accountSort" :direction="$accountDirection" :href="$accountSortUrl('ho_ten')" /></th>
                                <th scope="col" aria-sort="{{ $accountSort === 'email' ? ($accountDirection === 'asc' ? 'ascending' : 'descending') : 'none' }}"><x-backend.table-sort column="email" label="Email" :sort="$accountSort" :direction="$accountDirection" :href="$accountSortUrl('email')" /></th>
                                <th scope="col" aria-sort="{{ $accountSort === 'ten_vt' ? ($accountDirection === 'asc' ? 'ascending' : 'descending') : 'none' }}"><x-backend.table-sort column="ten_vt" label="Vai trò hiện tại" :sort="$accountSort" :direction="$accountDirection" :href="$accountSortUrl('ten_vt')" /></th>
                                <th scope="col">Phân quyền</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($accounts as $account)
                                <tr>
                                    <th scope="row"><span class="identifier-text">{{ $account->ma_nv }}</span></th>
                                    <td class="fw-semibold">{{ $account->ho_ten }}</td>
                                    <td>{{ $account->email }}</td>
                                    <td>{{ $account->ten_vt }}</td>
                                    <td>
                                        @can(\App\Enums\PhanQuyenPermission::Sua->value)
                                            <label class="visually-hidden" for="account-role-{{ $account->ma_nv }}">Vai trò của {{ $account->ho_ten }}</label>
                                            <select class="form-select" id="account-role-{{ $account->ma_nv }}" name="assignments[{{ $account->ma_nv }}]">
                                                @foreach ($roles as $role)<option value="{{ $role->ma_vt }}" @selected($role->ma_vt == $account->ma_vt)>{{ $role->ten_vt }}</option>@endforeach
                                            </select>
                                        @else
                                            <span class="text-secondary">Chỉ xem</span>
                                        @endcan
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                @can(\App\Enums\PhanQuyenPermission::Sua->value)
                    <div class="card-footer bg-white d-flex justify-content-end py-3">
                        <button class="btn btn-primary btn-icon-text" type="submit"><i class="bi bi-check2" aria-hidden="true"></i><span data-button-label>Lưu phân quyền</span></button>
                    </div>
                @endcan
                </form>
            @else
                <div class="card-body text-center py-5" role="status">
                    <i class="bi bi-person-x fs-1 text-secondary" aria-hidden="true"></i>
                    <h3 class="h6 mt-3 mb-1">Không có tài khoản phù hợp</h3>
                    <p class="text-secondary mb-0">Hãy kiểm tra lại mã hoặc tên nhân viên.</p>
                </div>
            @endif
            @if ($accounts->hasPages())
                <div class="card-footer pagination-footer bg-white py-3">
                    @include('backend.partials.pagination', ['paginator' => $accounts, 'label' => 'tài khoản'])
                </div>
            @endif
        </section>
    </main>
@endsection
