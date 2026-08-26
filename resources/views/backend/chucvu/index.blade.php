@extends('backend.layouts.app')

@section('title', 'Quản lý chức vụ')

@section('content')
    @php
        $canCreate = Gate::allows(\App\Enums\ChucVuPermission::Tao->value);
        $canEdit = Gate::allows(\App\Enums\ChucVuPermission::Sua->value);
        $canDelete = Gate::allows(\App\Enums\ChucVuPermission::Xoa->value);
    @endphp

    <main class="content-area" aria-labelledby="position-page-title">
        <div class="page-header">
            <div class="left">
                <div>
                    <h1 id="position-page-title">
                        <i class="bi bi-person-badge-fill text-danger me-2" aria-hidden="true"></i>
                        Danh sách chức vụ
                    </h1>
                    <nav aria-label="breadcrumb">
                        <ol class="breadcrumb">
                            <li class="breadcrumb-item"><a href="{{ url('/admin') }}">Trang chủ</a></li>
                            <li class="breadcrumb-item"><a href="{{ route('backend.chucvu.index') }}">Chức vụ</a></li>
                            <li class="breadcrumb-item active" aria-current="page">Danh sách chức vụ</li>
                        </ol>
                    </nav>
                </div>
            </div>
            <div class="d-flex flex-column flex-sm-row gap-2">
                <form class="d-flex gap-2" method="GET" action="{{ route('backend.chucvu.index') }}" role="search">
                    <label class="visually-hidden" for="position-search">Tìm theo tên chức vụ</label>
                    <input class="form-control" id="position-search" name="ten_cv" type="search" value="{{ $search }}" placeholder="Tìm chức vụ...">
                    <input type="hidden" name="per_page" value="{{ $pageSize }}">
                    <button class="btn btn-outline-secondary" type="submit" aria-label="Tìm kiếm">
                        <i class="bi bi-search" aria-hidden="true"></i>
                    </button>
                </form>
                <button class="btn btn-primary" type="button" data-position-create>
                    <i class="bi bi-plus-lg me-1" aria-hidden="true"></i>
                    Thêm chức vụ
                </button>
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

        <section class="card shadow-sm" aria-labelledby="position-table-title">
            <div class="card-header bg-white py-3">
                <div class="d-flex flex-column flex-sm-row justify-content-between align-items-sm-center gap-2">
                    <small class="text-secondary" aria-live="polite">
                        @if ($positions->total() > 0)
                            Hiển thị {{ $positions->firstItem() }}-{{ $positions->lastItem() }} trong tổng số {{ $positions->total() }} chức vụ
                        @else
                            Hiển thị 0 trong tổng số 0 chức vụ
                        @endif
                    </small>
                    <form method="GET" action="{{ route('backend.chucvu.index') }}" class="d-flex align-items-center gap-2">
                        <input type="hidden" name="ten_cv" value="{{ $search }}">
                        <label class="small text-secondary mb-0" for="position-page-size">Số phần tử / trang</label>
                        <select class="form-select form-select-sm w-auto pe-5" id="position-page-size" name="per_page" onchange="this.form.submit()">
                            @foreach ([5, 10, 20] as $size)
                                <option value="{{ $size }}" @selected($pageSize === $size)>{{ $size }}</option>
                            @endforeach
                        </select>
                    </form>
                </div>
            </div>
            @if ($positionError)
                <div class="card-body">
                    <div class="alert alert-danger mb-0" role="alert">
                        <p class="fw-semibold mb-1">Không tải được dữ liệu</p>
                        <p class="mb-0">{{ $positionError }}</p>
                    </div>
                </div>
            @elseif ($positions->total() === 0)
                <div class="card-body text-center py-5" aria-live="polite">
                    <i class="bi bi-person-badge fs-1 text-secondary" aria-hidden="true"></i>
                    <h2 class="h5 mt-3 mb-1">{{ $search !== '' ? 'Không tìm thấy chức vụ phù hợp' : 'Chưa có chức vụ nào' }}</h2>
                    <p class="text-secondary mb-0">{{ $search !== '' ? 'Hãy thử từ khóa khác.' : 'Thêm chức vụ đầu tiên để bắt đầu quản lý danh mục.' }}</p>
                </div>
            @else
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <caption class="visually-hidden">Danh sách chức vụ, hệ số phụ cấp và số nhân viên</caption>
                        <thead class="table-light">
                            <tr>
                                <th scope="col">Mã</th>
                                <th scope="col">Tên chức vụ</th>
                                <th scope="col">Hệ số phụ cấp</th>
                                <th scope="col">Số nhân viên</th>
                                <th scope="col" class="text-end">Thao tác</th>
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
                                        <div class="d-flex flex-wrap justify-content-end gap-2">
                                            @if ($canEdit || ! auth()->check())
                                                <a class="btn btn-sm btn-outline-primary" href="{{ route('backend.chucvu.edit', ['ma_cv' => $position->ma_cv]) }}">
                                                    <i class="bi bi-pencil" aria-hidden="true"></i>
                                                </a>
                                            @endif
                                            @if (($canDelete || ! auth()->check()) && ! $hasEmployees)
                                                <form method="POST" action="{{ route('backend.chucvu.destroy', ['ma_cv' => $position->ma_cv]) }}" data-confirm-delete="Xác nhận xóa chức vụ này?">
                                                    @csrf
                                                    @method('DELETE')
                                                    <button class="btn btn-sm btn-outline-danger" type="submit" data-submit>
                                                        <i class="bi bi-trash" aria-hidden="true"></i>
                                                    </button>
                                                </form>
                                            @elseif ($canDelete || ! auth()->check())
                                                <button class="btn btn-sm btn-outline-secondary" type="button" disabled title="Không thể xóa chức vụ đang có nhân viên">
                                                    <i class="bi bi-trash" aria-hidden="true"></i>
                                                </button>
                                            @endif
                                        </div>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                <div class="card-footer bg-white d-flex flex-column flex-sm-row justify-content-between align-items-sm-center gap-2 py-3">
                    <small class="text-secondary" aria-live="polite">
                        Hiển thị {{ $positions->firstItem() ?? 0 }}-{{ $positions->lastItem() ?? 0 }} trong tổng số {{ $positions->total() }} chức vụ
                    </small>
                    <nav aria-label="Phân trang danh sách chức vụ">
                        <ul class="pagination pagination-sm mb-0" id="position-pagination">
                            <li class="page-item {{ $positions->onFirstPage() ? 'disabled' : '' }}">
                                <a class="page-link" href="{{ $positions->previousPageUrl() ?? '#' }}" aria-label="Trang trước">
                                    <i class="bi bi-chevron-left" aria-hidden="true"></i>
                                </a>
                            </li>
                            @for ($page = 1; $page <= $positions->lastPage(); $page++)
                                <li class="page-item {{ $page === $positions->currentPage() ? 'active' : '' }}">
                                    <a class="page-link" href="{{ $positions->url($page) }}" @if ($page === $positions->currentPage()) aria-current="page" @endif>{{ $page }}</a>
                                </li>
                            @endfor
                            <li class="page-item {{ $positions->currentPage() === $positions->lastPage() ? 'disabled' : '' }}">
                                <a class="page-link" href="{{ $positions->nextPageUrl() ?? '#' }}" aria-label="Trang sau">
                                    <i class="bi bi-chevron-right" aria-hidden="true"></i>
                                </a>
                            </li>
                        </ul>
                    </nav>
                </div>
            @endif
        </section>
    </main>

    <div class="modal fade" id="position-modal" tabindex="-1" aria-labelledby="position-modal-title" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <form class="modal-content" id="position-form" method="POST" action="{{ route('backend.chucvu.store') }}">
                @csrf
                <div class="modal-header">
                    <h2 class="modal-title h5" id="position-modal-title">Thêm chức vụ</h2>
                    <button class="btn-close" type="button" data-bs-dismiss="modal" aria-label="Đóng"></button>
                </div>
                <div class="modal-body">
                    <div id="position-form-error" class="alert alert-danger d-none" role="alert"></div>
                    <div class="mb-3">
                        <label class="form-label" for="position-name">Tên chức vụ <span class="text-danger">*</span></label>
                        <input class="form-control" id="position-name" name="ten_cv" type="text" maxlength="100" required autocomplete="organization-title">
                    </div>
                    <div>
                        <label class="form-label" for="position-rate">Hệ số phụ cấp <span class="text-danger">*</span></label>
                        <input class="form-control" id="position-rate" name="he_so_phu_cap" type="number" min="0" max="99.99" step="0.01" required inputmode="decimal">
                    </div>
                </div>
                <div class="modal-footer">
                    <button class="btn btn-outline-secondary" type="button" data-bs-dismiss="modal">Hủy</button>
                    <button class="btn btn-primary" type="submit" id="position-submit">Lưu chức vụ</button>
                </div>
            </form>
        </div>
    </div>
@endsection

@push('scripts')
    @vite('resources/js/frontend/chucvu/chucvu.js')
@endpush

@push('styles')
    <style>
        #position-pagination {
            gap: 8px;
        }

        #position-pagination .page-item + .page-item {
            margin-left: 0;
        }

        #position-pagination .page-link {
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

        #position-pagination .page-link:hover,
        #position-pagination .page-link:focus {
            color: #e94560;
            border-color: #e94560;
            background: #fff;
            box-shadow: 0 0 0 3px rgba(233, 69, 96, 0.1);
        }

        #position-pagination .page-item.active .page-link {
            color: #fff;
            border-color: #e94560;
            background: #e94560;
        }

        #position-pagination .page-item.disabled .page-link {
            color: #adb5bd;
            border-color: #e9ecef;
            background: #fff;
            pointer-events: none;
        }

        @media (max-width: 575.98px) {
            #position-pagination .page-link {
                width: 40px;
                height: 40px;
            }
        }
    </style>
@endpush
