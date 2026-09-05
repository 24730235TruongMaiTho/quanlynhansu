@extends('backend.layouts.app')

@section('title', 'Thêm phòng ban')

@section('content')
    <main class="container-fluid container-xxl py-4" aria-labelledby="department-create-title">
        <x-backend.page-header
            title="Thêm phòng ban"
            title-id="department-create-title"
            icon="bi-building-add"
            description="Nhập tên phòng ban theo danh mục dùng chung của hệ thống."
            :breadcrumbs="[
                ['label' => 'Nhân sự', 'url' => route('backend.tongquan.index')],
                ['label' => 'Phòng ban', 'url' => route('backend.phongban.index')],
                ['label' => 'Thêm mới'],
            ]"
        />

        @if ($errors->has('phong_ban'))
            <div class="alert alert-danger" role="alert">{{ $errors->first('phong_ban') }}</div>
        @endif

        <section class="card shadow-sm" aria-labelledby="department-form-title">
            <div class="card-body">
                <form method="POST" action="{{ route('backend.phongban.store') }}" data-phong-ban-form>
                    @csrf
                    <h2 class="h5 mb-3" id="department-form-title">Thông tin phòng ban</h2>
                    <div class="mb-3">
                        <label class="form-label" for="ten_pb">Tên phòng ban <span aria-hidden="true">*</span></label>
                        <input class="form-control @error('ten_pb') is-invalid @enderror" id="ten_pb" name="ten_pb" type="text" maxlength="100" required value="{{ old('ten_pb') }}" autocomplete="organization-title">
                        @error('ten_pb')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>
                    <div class="d-flex flex-wrap gap-2">
                        <button class="btn btn-primary d-inline-flex align-items-center gap-2" type="submit" data-submit data-submitting-text="Đang lưu..."><i class="bi bi-check2" aria-hidden="true"></i>Lưu phòng ban</button>
                        <a class="btn btn-outline-secondary d-inline-flex align-items-center gap-2" href="{{ route('backend.phongban.index') }}"><i class="bi bi-x-lg" aria-hidden="true"></i>Hủy</a>
                    </div>
                </form>
            </div>
        </section>
    </main>
@endsection

@push('scripts')
    @vite('resources/js/frontend/phongban/phongban.js')
@endpush
