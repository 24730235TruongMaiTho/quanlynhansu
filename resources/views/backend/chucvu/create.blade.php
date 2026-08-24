@extends('backend.layouts.app')

@section('title', 'Thêm chức vụ')

@section('content')
    <main class="container-fluid container-xxl py-4" aria-labelledby="position-create-title">
        <div class="mb-4">
            <div class="small text-secondary mb-1"><a href="{{ route('backend.chucvu.index') }}">Chức vụ</a> / Thêm mới</div>
            <h1 class="h3 fw-semibold mb-1" id="position-create-title">Thêm chức vụ</h1>
            <p class="text-secondary mb-0">Nhập tên chức vụ và hệ số phụ cấp theo danh mục dùng chung.</p>
        </div>

        @if ($errors->has('chuc_vu'))
            <div class="alert alert-danger" role="alert">{{ $errors->first('chuc_vu') }}</div>
        @endif

        <section class="card shadow-sm" aria-labelledby="position-form-title">
            <div class="card-body">
                <form method="POST" action="{{ route('backend.chucvu.store') }}" data-chuc-vu-form>
                    @csrf
                    <h2 class="h5 mb-3" id="position-form-title">Thông tin chức vụ</h2>
                    <div class="mb-3">
                        <label class="form-label" for="ten_cv">Tên chức vụ <span aria-hidden="true">*</span></label>
                        <input class="form-control @error('ten_cv') is-invalid @enderror" id="ten_cv" name="ten_cv" type="text" maxlength="100" required value="{{ old('ten_cv') }}" autocomplete="organization-title">
                        @error('ten_cv')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="mb-3">
                        <label class="form-label" for="he_so_phu_cap">Hệ số phụ cấp <span aria-hidden="true">*</span></label>
                        <input class="form-control @error('he_so_phu_cap') is-invalid @enderror" id="he_so_phu_cap" name="he_so_phu_cap" type="number" min="0" max="99.99" step="0.01" required value="{{ old('he_so_phu_cap') }}" inputmode="decimal">
                        @error('he_so_phu_cap')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="d-flex flex-wrap gap-2">
                        <button class="btn btn-primary" type="submit" data-submit data-submitting-text="Đang lưu...">Lưu chức vụ</button>
                        <a class="btn btn-outline-secondary" href="{{ route('backend.chucvu.index') }}">Hủy</a>
                    </div>
                </form>
            </div>
        </section>
    </main>
@endsection

@push('scripts')
    @vite('resources/js/frontend/chucvu/chucvu.js')
@endpush
