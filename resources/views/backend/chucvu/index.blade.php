@extends('backend.layouts.app')

@section('title', 'Quản lý chức vụ')

@section('content')
    <main class="container-fluid container-xxl py-4" aria-labelledby="position-page-title">
        <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3 mb-4">
            <div>
                <div class="small text-secondary mb-1">Nhân sự / Chức vụ</div>
                <h1 class="h3 fw-semibold mb-1" id="position-page-title">Danh sách chức vụ</h1>
                <p class="text-secondary mb-0">Quản lý tên chức vụ, hệ số phụ cấp và số nhân viên đang sử dụng.</p>
            </div>
            <a class="btn btn-primary" href="{{ route('backend.chucvu.create') }}">
                <i class="bi bi-plus-lg" aria-hidden="true"></i>
                Thêm chức vụ
            </a>
        </div>

        @if (session('success'))
            <div class="alert alert-success" role="status">{{ session('success') }}</div>
        @endif

        @if ($errors->any())
            <div class="alert alert-danger" role="alert">
                <p class="fw-semibold mb-1">Không thể hoàn tất thao tác.</p>
                <ul class="mb-0">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        @if ($positionError)
            <div class="alert alert-danger" role="alert">
                <p class="fw-semibold mb-1">Không tải được dữ liệu</p>
                <p class="mb-0">{{ $positionError }}</p>
            </div>
        @elseif (count($positions) === 0)
            <section class="card shadow-sm" aria-live="polite">
                <div class="card-body text-center py-5">
                    <i class="bi bi-person-badge fs-1 text-secondary" aria-hidden="true"></i>
                    <h2 class="h5 mt-3 mb-1">Chưa có chức vụ nào</h2>
                    <p class="text-secondary mb-0">Thêm chức vụ đầu tiên để bắt đầu quản lý danh mục.</p>
                </div>
            </section>
        @else
            <section class="card shadow-sm overflow-hidden" aria-labelledby="position-table-title">
                <div class="card-header bg-white py-3">
                    <h2 class="h6 fw-semibold mb-0" id="position-table-title">Chức vụ hiện có</h2>
                </div>
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <caption class="visually-hidden">Danh sách chức vụ, hệ số phụ cấp và số nhân viên</caption>
                        <thead class="table-light">
                            <tr>
                                <th scope="col">Mã chức vụ</th>
                                <th scope="col">Tên chức vụ</th>
                                <th scope="col">Hệ số phụ cấp</th>
                                <th scope="col">Số nhân viên</th>
                                <th scope="col">Thao tác</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($positions as $position)
                                @php($hasEmployees = (int) $position->so_nhan_vien > 0)
                                <tr>
                                    <th scope="row">{{ $position->ma_cv }}</th>
                                    <td class="fw-medium">{{ $position->ten_cv }}</td>
                                    <td>{{ number_format((float) $position->he_so_phu_cap, 2, '.', '') }}</td>
                                    <td>
                                        @if ($hasEmployees)
                                            {{ $position->so_nhan_vien }}
                                        @else
                                            <span class="text-secondary">Chưa có nhân viên</span>
                                        @endif
                                    </td>
                                    <td>
                                        <div class="d-flex flex-wrap gap-2">
                                            <a class="btn btn-sm btn-outline-primary" href="{{ route('backend.chucvu.edit', ['ma_cv' => $position->ma_cv]) }}">Chỉnh sửa</a>
                                            @if (! $hasEmployees)
                                                <form method="POST" action="{{ route('backend.chucvu.destroy', ['ma_cv' => $position->ma_cv]) }}" data-confirm-delete="Xác nhận xóa chức vụ này?">
                                                    @csrf
                                                    @method('DELETE')
                                                    <button class="btn btn-sm btn-outline-danger" type="submit" data-submit>Xóa</button>
                                                </form>
                                            @else
                                                <button class="btn btn-sm btn-outline-secondary" type="button" disabled title="Không thể xóa chức vụ đang có nhân viên">Xóa</button>
                                            @endif
                                        </div>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </section>
        @endif
    </main>
@endsection

@push('scripts')
    @vite('resources/js/frontend/chucvu/chucvu.js')
@endpush
