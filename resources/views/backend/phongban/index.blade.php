@extends('backend.layouts.app')

@section('title', 'Quản lý phòng ban')

@section('content')
    @php
        $canCreate = Gate::allows(\App\Enums\PhongBanPermission::Tao->value);
        $canEdit = Gate::allows(\App\Enums\PhongBanPermission::Sua->value);
        $canDelete = Gate::allows(\App\Enums\PhongBanPermission::Xoa->value);
    @endphp

    <main class="content-area" aria-labelledby="department-page-title">
        <div class="page-header">
            <div class="left">
                <div>
                    <h1 id="department-page-title">
                        <i class="bi bi-building-fill text-primary me-2" aria-hidden="true"></i>
                        Danh sách phòng ban
                    </h1>
                    <nav aria-label="breadcrumb">
                        <ol class="breadcrumb">
                            <li class="breadcrumb-item"><a href="{{ url('/admin') }}">Trang chủ</a></li>
                            <li class="breadcrumb-item"><a href="{{ route('backend.phongban.index') }}">Phòng ban</a></li>
                            <li class="breadcrumb-item active" aria-current="page">Danh sách phòng ban</li>
                        </ol>
                    </nav>
                </div>
            </div>
            <div class="d-flex flex-column flex-sm-row gap-2">
                <form class="d-flex gap-2" method="GET" action="{{ route('backend.phongban.index') }}" role="search">
                    <label class="visually-hidden" for="department-search">Tìm theo tên phòng ban</label>
                    <input class="form-control" id="department-search" name="ten_pb" type="search" value="{{ $search }}" placeholder="Tìm phòng ban...">
                    <input type="hidden" name="per_page" value="{{ $pageSize }}">
                    <button class="btn btn-outline-secondary" type="submit" aria-label="Tìm kiếm">
                        <i class="bi bi-search" aria-hidden="true"></i>
                    </button>
                    @if ($search !== '')
                        <a class="btn btn-outline-secondary" href="{{ route('backend.phongban.index', ['per_page' => $pageSize]) }}" aria-label="Xóa bộ lọc tìm kiếm" title="Xóa bộ lọc tìm kiếm">
                            <i class="bi bi-x-lg" aria-hidden="true"></i>
                        </a>
                    @endif
                </form>
                @if ($canCreate)
                    <a class="btn btn-primary" href="{{ route('backend.phongban.create') }}">
                        <i class="bi bi-plus-lg me-1" aria-hidden="true"></i>
                        Thêm phòng ban
                    </a>
                @endif
            </div>
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

        <section class="card shadow-sm" aria-labelledby="department-table-title">
            <div class="card-header bg-white py-3">
                <div class="d-flex flex-column flex-sm-row justify-content-between align-items-sm-center gap-2">
                    <small class="text-secondary" aria-live="polite">
                        @if ($departments->total() > 0)
                            Hiển thị {{ $departments->firstItem() }}-{{ $departments->lastItem() }} trong tổng số {{ $departments->total() }} phòng ban
                        @else
                            Hiển thị 0 trong tổng số 0 phòng ban
                        @endif
                    </small>
                    <form method="GET" action="{{ route('backend.phongban.index') }}" class="d-flex align-items-center gap-2">
                        <input type="hidden" name="ten_pb" value="{{ $search }}">
                        <label class="small text-secondary mb-0" for="department-page-size">Số phần tử / trang</label>
                        <select class="form-select form-select-sm w-auto pe-5" id="department-page-size" name="per_page" onchange="this.form.submit()">
                            @foreach ([5, 10, 20] as $size)
                                <option value="{{ $size }}" @selected($pageSize === $size)>{{ $size }}</option>
                            @endforeach
                        </select>
                    </form>
                </div>
            </div>
            @if ($departmentError)
                <div class="card-body">
                    <div class="alert alert-danger mb-0" role="alert">
                        <p class="fw-semibold mb-1">Không tải được dữ liệu</p>
                        <p class="mb-0">{{ $departmentError }}</p>
                    </div>
                </div>
            @elseif ($departments->total() === 0)
                <div class="card-body text-center py-5" aria-live="polite">
                    <i class="bi bi-building fs-1 text-secondary" aria-hidden="true"></i>
                    <h2 class="h5 mt-3 mb-1">{{ $search !== '' ? 'Không tìm thấy phòng ban phù hợp' : 'Chưa có phòng ban nào' }}</h2>
                    <p class="text-secondary mb-0">{{ $search !== '' ? 'Hãy thử từ khóa khác.' : 'Thêm phòng ban đầu tiên để bắt đầu quản lý nhân sự.' }}</p>
                </div>
            @else
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <caption class="visually-hidden">Danh sách phòng ban và số nhân viên</caption>
                        <thead class="table-light">
                            <tr>
                                <th scope="col">Mã</th>
                                <th scope="col">Tên phòng ban</th>
                                <th scope="col">Số nhân viên</th>
                                @if ($canEdit || $canDelete)
                                    <th scope="col" class="text-end">Thao tác</th>
                                @endif
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($departments as $department)
                                @php($hasEmployees = (int) $department->so_nhan_vien > 0)
                                <tr>
                                    <th scope="row">{{ $department->ma_pb }}</th>
                                    <td class="fw-medium">{{ $department->ten_pb }}</td>
                                    <td>
                                        @if ($hasEmployees)
                                            {{ $department->so_nhan_vien }}
                                        @else
                                            <span class="text-secondary">Chưa có nhân viên</span>
                                        @endif
                                    </td>
                                    @if ($canEdit || $canDelete)
                                        <td>
                                            <div class="d-flex flex-wrap justify-content-end gap-2">
                                                @if ($canEdit)
                                                    <a class="btn btn-sm btn-outline-primary" href="{{ route('backend.phongban.edit', ['ma_pb' => $department->ma_pb]) }}">
                                                        <i class="bi bi-pencil me-1" aria-hidden="true"></i>Chỉnh sửa
                                                    </a>
                                                @endif
                                                @if ($canDelete && ! $hasEmployees)
                                                    <form method="POST" action="{{ route('backend.phongban.destroy', ['ma_pb' => $department->ma_pb]) }}" data-confirm-delete="Xác nhận xóa phòng ban này?">
                                                        @csrf
                                                        @method('DELETE')
                                                        <button class="btn btn-sm btn-outline-danger" type="submit" data-submit>
                                                            <i class="bi bi-trash me-1" aria-hidden="true"></i>Xóa
                                                        </button>
                                                    </form>
                                                @elseif ($canDelete)
                                                    <button class="btn btn-sm btn-outline-secondary" type="button" disabled title="Không thể xóa phòng ban đang có nhân viên">
                                                        <i class="bi bi-trash me-1" aria-hidden="true"></i>Xóa
                                                    </button>
                                                @endif
                                            </div>
                                        </td>
                                    @endif
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                <div class="card-footer bg-white d-flex flex-column flex-sm-row justify-content-between align-items-sm-center gap-2 py-3">
                    <small class="text-secondary" aria-live="polite">
                        Hiển thị {{ $departments->firstItem() ?? 0 }}-{{ $departments->lastItem() ?? 0 }} trong tổng số {{ $departments->total() }} phòng ban
                    </small>
                    <nav aria-label="Phân trang danh sách phòng ban">
                        <ul class="pagination pagination-sm mb-0" id="department-pagination">
                            <li class="page-item {{ $departments->onFirstPage() ? 'disabled' : '' }}">
                                <a class="page-link" href="{{ $departments->previousPageUrl() ?? '#' }}" aria-label="Trang trước">
                                    <i class="bi bi-chevron-left" aria-hidden="true"></i>
                                </a>
                            </li>
                            @for ($page = 1; $page <= $departments->lastPage(); $page++)
                                <li class="page-item {{ $page === $departments->currentPage() ? 'active' : '' }}">
                                    <a class="page-link" href="{{ $departments->url($page) }}" @if ($page === $departments->currentPage()) aria-current="page" @endif>{{ $page }}</a>
                                </li>
                            @endfor
                            <li class="page-item {{ $departments->currentPage() === $departments->lastPage() ? 'disabled' : '' }}">
                                <a class="page-link" href="{{ $departments->nextPageUrl() ?? '#' }}" aria-label="Trang sau">
                                    <i class="bi bi-chevron-right" aria-hidden="true"></i>
                                </a>
                            </li>
                        </ul>
                    </nav>
                </div>
            @endif
        </section>
    </main>
@endsection

@push('scripts')
    @vite('resources/js/frontend/phongban/phongban.js')
@endpush

@push('styles')
    <style>
        #department-pagination {
            gap: 8px;
        }

        #department-pagination .page-item + .page-item {
            margin-left: 0;
        }

        #department-pagination .page-link {
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

        #department-pagination .page-link:hover,
        #department-pagination .page-link:focus {
            color: #0d6efd;
            border-color: #0d6efd;
            background: #fff;
            box-shadow: 0 0 0 3px rgba(13, 110, 253, 0.1);
        }

        #department-pagination .page-item.active .page-link {
            color: #fff;
            border-color: #0d6efd;
            background: #0d6efd;
        }

        #department-pagination .page-item.disabled .page-link {
            color: #adb5bd;
            border-color: #e9ecef;
            background: #fff;
            pointer-events: none;
        }

        @media (max-width: 575.98px) {
            #department-pagination .page-link {
                width: 40px;
                height: 40px;
            }
        }
    </style>
@endpush
