@extends('backend.layouts.app')

@section('title', 'Đổi mật khẩu')

@section('content')
    <main class="container container-xl py-4 profile-page" aria-labelledby="page-title">
        <x-backend.page-header
            title="Đổi mật khẩu"
            title-id="page-title"
            icon="bi-key"
            description-id="password-form-help"
            description="Dùng mật khẩu hiện tại để đặt mật khẩu mới cho tài khoản của bạn."
            :breadcrumbs="[
                ['label' => 'Tài khoản', 'url' => route('backend.profile.edit')],
                ['label' => 'Đổi mật khẩu'],
            ]"
        />

        @if (session('success'))
            <div class="alert alert-success" role="status">{{ session('success') }}</div>
        @endif
        @if ($errors->any())
            <div class="alert alert-danger" role="alert">
                <p class="fw-semibold mb-1">Chưa thể đổi mật khẩu. Vui lòng kiểm tra:</p>
                <ul class="mb-0">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <div class="card shadow-sm">
            <div class="card-body">
                <form action="{{ route('backend.profile.password.update') }}" method="POST" aria-describedby="password-form-help" autocomplete="off">
                    @csrf
                    @method('PATCH')
                    <div class="mb-3">
                        <label class="form-label" for="mat_khau_hien_tai">Mật khẩu hiện tại <span aria-hidden="true">*</span></label>
                        <input class="form-control @error('mat_khau_hien_tai') is-invalid @enderror" id="mat_khau_hien_tai" name="mat_khau_hien_tai" type="password" autocomplete="current-password" required @error('mat_khau_hien_tai') aria-describedby="mat_khau_hien_tai-error" @enderror>
                        @error('mat_khau_hien_tai')<div class="invalid-feedback" id="mat_khau_hien_tai-error" role="alert">{{ $message }}</div>@enderror
                    </div>
                    <div class="mb-3">
                        <label class="form-label" for="mat_khau_moi">Mật khẩu mới <span aria-hidden="true">*</span></label>
                        <input class="form-control @error('mat_khau_moi') is-invalid @enderror" id="mat_khau_moi" name="mat_khau_moi" type="password" minlength="8" autocomplete="new-password" required aria-describedby="mat_khau_moi-help @error('mat_khau_moi') mat_khau_moi-error @enderror">
                        <div class="form-text" id="mat_khau_moi-help">Mật khẩu mới có ít nhất 8 ký tự và phải khác mật khẩu hiện tại.</div>
                        @error('mat_khau_moi')<div class="invalid-feedback" id="mat_khau_moi-error" role="alert">{{ $message }}</div>@enderror
                    </div>
                    <div class="mb-4">
                        <label class="form-label" for="mat_khau_moi_confirmation">Xác nhận mật khẩu mới <span aria-hidden="true">*</span></label>
                        <input class="form-control @error('mat_khau_moi_confirmation') is-invalid @enderror" id="mat_khau_moi_confirmation" name="mat_khau_moi_confirmation" type="password" minlength="8" autocomplete="new-password" required @error('mat_khau_moi_confirmation') aria-describedby="mat_khau_moi_confirmation-error" @enderror>
                        @error('mat_khau_moi_confirmation')<div class="invalid-feedback" id="mat_khau_moi_confirmation-error" role="alert">{{ $message }}</div>@enderror
                    </div>
                    <div class="d-flex flex-wrap justify-content-end gap-2">
                        <a class="btn btn-outline-secondary" href="{{ route('backend.profile.edit') }}"><i class="bi bi-x-circle button-icon" aria-hidden="true"></i>Hủy</a>
                        <button class="btn btn-primary" type="submit"><i class="bi bi-key button-icon" aria-hidden="true"></i>Đổi mật khẩu</button>
                    </div>
                </form>
            </div>
        </div>
    </main>
@endsection
