@extends('backend.layouts.app')

@section('title', 'Gán vai trò tài khoản')

@section('content')
    <main class="container-fluid container-xxl py-4" aria-labelledby="account-role-title">
        <nav class="mb-3" aria-label="Đường dẫn trang">
            <ol class="breadcrumb mb-0">
                <li class="breadcrumb-item">Hệ thống</li>
                <li class="breadcrumb-item active" aria-current="page">Gán vai trò tài khoản</li>
            </ol>
        </nav>

        <header class="d-flex flex-column flex-lg-row justify-content-between align-items-lg-center gap-3 mb-4">
            <div>
                <h1 class="h3 fw-semibold mb-1" id="account-role-title">Gán vai trò tài khoản</h1>
                <p class="text-secondary mb-0">Gán một vai trò cho nhân viên; quyền được cấu hình theo vai trò, không thiết lập trực tiếp tại đây.</p>
            </div>
            @can(\App\Enums\VaiTroPermission::Xem->value)
                <a class="btn btn-outline-primary" href="{{ route('backend.vaitro.index') }}"><i class="bi bi-shield-check me-1" aria-hidden="true"></i>Quản lý vai trò và quyền</a>
            @endcan
        </header>

        <div class="alert alert-info d-flex gap-3 align-items-start" role="note">
            <i class="bi bi-info-circle-fill" aria-hidden="true"></i>
            <div><strong>Quyền được cấu hình theo vai trò.</strong> Muốn thay đổi hành động được phép trên một module, hãy mở Danh sách vai trò và chọn “Phân quyền”.</div>
        </div>

        @if (session('success'))<div class="alert alert-success" role="status">{{ session('success') }}</div>@endif
        @if ($errors->any())<div class="alert alert-danger" role="alert">{{ $errors->first() }}</div>@endif

        <section class="card shadow-sm mb-3" aria-labelledby="account-filter-title">
            <div class="card-header bg-white py-3"><h2 class="h6 fw-semibold mb-0" id="account-filter-title">Tìm tài khoản</h2></div>
            <div class="card-body">
                <form method="get" action="{{ route('backend.taikhoan.index') }}">
                    <div class="row g-3 align-items-end">
                        <div class="col-12 col-lg-7">
                            <label class="form-label" for="keyword">Mã hoặc tên nhân viên</label>
                            <input class="form-control" id="keyword" name="keyword" type="search" maxlength="100" value="{{ request('keyword') }}" placeholder="Ví dụ: 00001 hoặc Nguyễn Văn An">
                        </div>
                        <div class="col-12 col-lg-5 d-flex flex-wrap gap-2">
                            <button class="btn btn-success" type="submit"><i class="bi bi-search me-1" aria-hidden="true"></i>Tìm</button>
                            @if (filled(request('keyword')))<a class="btn btn-outline-secondary" href="{{ route('backend.taikhoan.index') }}">Xóa lọc</a>@endif
                        </div>
                    </div>
                </form>
            </div>
        </section>

        <section class="card shadow-sm overflow-hidden" aria-labelledby="account-table-title">
            <div class="card-header bg-white py-3">
                <h2 class="h6 fw-semibold mb-1" id="account-table-title">Danh sách tài khoản</h2>
                <p class="small text-secondary mb-0">Tìm thấy {{ number_format(count($accounts), 0, ',', '.') }} tài khoản.</p>
            </div>
            @if (count($accounts) > 0)
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <caption class="visually-hidden">Danh sách tài khoản và vai trò hiện tại</caption>
                        <thead class="table-light"><tr><th scope="col">Mã</th><th scope="col">Nhân viên</th><th scope="col">Email</th><th scope="col">Vai trò hiện tại</th><th scope="col">Gán vai trò</th></tr></thead>
                        <tbody>
                            @foreach ($accounts as $account)
                                <tr>
                                    <th scope="row"><span class="badge bg-primary">{{ $account->ma_nv }}</span></th>
                                    <td class="fw-semibold">{{ $account->ho_ten }}</td>
                                    <td>{{ $account->email }}</td>
                                    <td>{{ $account->ten_vt }}</td>
                                    <td>
                                        <form class="d-flex flex-column flex-sm-row gap-2" method="post" action="{{ route('backend.taikhoan.assign-role', $account->ma_nv) }}">
                                            @csrf @method('PATCH')
                                            <label class="visually-hidden" for="account-role-{{ $account->ma_nv }}">Vai trò của {{ $account->ho_ten }}</label>
                                            <select class="form-select form-select-sm" id="account-role-{{ $account->ma_nv }}" name="ma_vt">
                                                @foreach ($roles as $role)<option value="{{ $role->ma_vt }}" @selected($role->ma_vt == $account->ma_vt)>{{ $role->ten_vt }}</option>@endforeach
                                            </select>
                                            <button class="btn btn-primary btn-sm text-nowrap" type="submit">Lưu vai trò</button>
                                        </form>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @else
                <div class="card-body text-center py-5" role="status">
                    <i class="bi bi-person-x fs-1 text-secondary" aria-hidden="true"></i>
                    <h3 class="h6 mt-3 mb-1">Không có tài khoản phù hợp</h3>
                    <p class="text-secondary mb-0">Hãy kiểm tra lại mã hoặc tên nhân viên.</p>
                </div>
            @endif
        </section>
    </main>
@endsection
