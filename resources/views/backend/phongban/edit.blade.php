@extends('backend.layouts.app')

@section('title', 'Sửa phòng ban')

@section('content')
    <main class="container-fluid container-xxl py-4" aria-labelledby="department-edit-title">
        <div class="mb-4">
            <div class="small text-secondary mb-1"><a href="{{ route('backend.phongban.index') }}">Phòng ban</a> / Chỉnh sửa</div>
            <h1 class="h3 fw-semibold mb-1" id="department-edit-title">Sửa phòng ban</h1>
            <p class="text-secondary mb-0">Cập nhật tên phòng ban #{{ $department->ma_pb }}.</p>
        </div>

        @if ($errors->has('phong_ban'))
            <div class="alert alert-danger" role="alert">{{ $errors->first('phong_ban') }}</div>
        @endif

        <section class="card shadow-sm" aria-labelledby="department-form-title">
            <div class="card-body">
                <form method="POST" action="{{ route('backend.phongban.update', ['ma_pb' => $department->ma_pb]) }}" data-phong-ban-form>
                    @csrf
                    @method('PUT')
                    <h2 class="h5 mb-3" id="department-form-title">Thông tin phòng ban</h2>
                    <div class="mb-3">
                        <label class="form-label" for="ten_pb">Tên phòng ban <span aria-hidden="true">*</span></label>
                        <input class="form-control @error('ten_pb') is-invalid @enderror" id="ten_pb" name="ten_pb" type="text" maxlength="100" required value="{{ old('ten_pb', $department->ten_pb) }}" autocomplete="organization-title">
                        @error('ten_pb')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>
                    <div class="d-flex flex-wrap gap-2">
                        <button class="btn btn-primary" type="submit" data-submit data-submitting-text="Đang lưu...">Lưu thay đổi</button>
                        <a class="btn btn-outline-secondary" href="{{ route('backend.phongban.index') }}">Hủy</a>
                    </div>
                </form>
            </div>
        </section>
    </main>
@endsection

@push('scripts')
    @vite('resources/js/frontend/phongban/phongban.js')
@endpush
