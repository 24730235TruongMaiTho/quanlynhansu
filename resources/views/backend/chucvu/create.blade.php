@extends('backend.layouts.app')

@section('title', 'Thêm chức vụ')

@section('content')
    <main class="container-fluid container-xxl py-4" aria-labelledby="position-create-title">
        <x-backend.page-header
            title="Thêm chức vụ"
            title-id="position-create-title"
            icon="bi-person-badge"
            description="Nhập tên chức vụ và hệ số phụ cấp theo danh mục dùng chung."
            :breadcrumbs="[
                ['label' => 'Nhân sự', 'url' => route('backend.tongquan.index')],
                ['label' => 'Chức vụ', 'url' => route('backend.chucvu.index')],
                ['label' => 'Thêm mới'],
            ]"
        />

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
                        <button class="btn btn-primary" type="submit" data-submit data-submitting-text="Đang lưu..."><i class="bi bi-check2" aria-hidden="true"></i>Lưu chức vụ</button>
                        <a class="btn btn-outline-secondary" href="{{ route('backend.chucvu.index') }}"><i class="bi bi-x-lg" aria-hidden="true"></i>Hủy</a>
                    </div>
                </form>
            </div>
        </section>
    </main>
@endsection

@push('scripts')
    @vite('resources/js/frontend/chucvu/chucvu.js')
@endpush
