@extends('backend.layouts.app')

@section('title', 'Hồ sơ cá nhân')

@section('content')
    @php
        $displayDate = static function (string $field) use ($employee): string {
            $value = old($field, data_get($employee, $field));

            return \App\Support\DisplayDateFormatter::format(
                $value,
                is_scalar($value) ? (string) $value : '',
            );
        };
        $value = static fn (string $field): mixed => old($field, data_get($employee, $field));
    @endphp

    <main class="container container-xl py-4 profile-page" aria-labelledby="page-title">
        <x-backend.page-header
            title="Hồ sơ cá nhân"
            title-id="page-title"
            icon="bi-person-circle"
            description-id="profile-form-help"
            description="Cập nhật thông tin liên hệ và địa chỉ của chính bạn."
            :breadcrumbs="[
                ['label' => 'Tài khoản', 'url' => route('backend.tongquan.index')],
                ['label' => 'Hồ sơ cá nhân'],
            ]"
        >
            <x-slot:actions>
                <a class="btn btn-outline-secondary" href="{{ route('backend.profile.password.edit') }}"><i class="bi bi-key button-icon" aria-hidden="true"></i>Đổi mật khẩu</a>
            </x-slot:actions>
        </x-backend.page-header>

        @if (session('success'))
            <div class="alert alert-success" role="status">{{ session('success') }}</div>
        @endif
        @if ($errors->any())
            <div class="alert alert-danger" role="alert">
                <p class="fw-semibold mb-1">Chưa thể cập nhật hồ sơ. Vui lòng kiểm tra:</p>
                <ul class="mb-0">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <div class="card shadow-sm">
            <div class="card-body">
                <dl class="row small mb-4">
                    <dt class="col-sm-3">Mã nhân viên</dt>
                    <dd class="col-sm-9 mb-2">{{ $employee->ma_nv }}</dd>
                    <dt class="col-sm-3">Vai trò</dt>
                    <dd class="col-sm-9 mb-0">{{ $employee->ten_vt ?: 'Người dùng' }}</dd>
                </dl>

                <form action="{{ route('backend.profile.update') }}" method="POST" enctype="multipart/form-data" aria-describedby="profile-form-help">
                    @csrf
                    @method('PATCH')

                    <fieldset class="mb-4">
                        <legend class="h5 fw-semibold">Thông tin cá nhân và liên hệ</legend>
                        <div class="row g-3">
                            <div class="col-12 col-md-6">
                                <label class="form-label" for="ho_ten">Họ và tên <span aria-hidden="true">*</span></label>
                                <input class="form-control @error('ho_ten') is-invalid @enderror" id="ho_ten" name="ho_ten" type="text" maxlength="50" autocomplete="name" value="{{ $value('ho_ten') }}" required @error('ho_ten') aria-describedby="ho_ten-error" @enderror>
                                @error('ho_ten')<div class="invalid-feedback" id="ho_ten-error" role="alert">{{ $message }}</div>@enderror
                            </div>
                            <div class="col-12 col-md-6">
                                <label class="form-label" for="ngay_sinh">Ngày sinh <span aria-hidden="true">*</span></label>
                                <input class="form-control @error('ngay_sinh') is-invalid @enderror" id="ngay_sinh" name="ngay_sinh" type="text" inputmode="numeric" maxlength="10" placeholder="dd/mm/yyyy" value="{{ $displayDate('ngay_sinh') }}" required aria-describedby="ngay_sinh-help @error('ngay_sinh') ngay_sinh-error @enderror">
                                <div class="form-text" id="ngay_sinh-help">Định dạng ngày: dd/mm/yyyy.</div>
                                @error('ngay_sinh')<div class="invalid-feedback" id="ngay_sinh-error" role="alert">{{ $message }}</div>@enderror
                            </div>
                            <div class="col-12 col-md-6">
                                <label class="form-label" for="gioi_tinh">Giới tính <span aria-hidden="true">*</span></label>
                                <select class="form-select @error('gioi_tinh') is-invalid @enderror" id="gioi_tinh" name="gioi_tinh" required @error('gioi_tinh') aria-describedby="gioi_tinh-error" @enderror>
                                    <option value="">Chọn giới tính</option>
                                    <option value="1" @selected((string) $value('gioi_tinh') === '1')>Nam</option>
                                    <option value="0" @selected((string) $value('gioi_tinh') === '0')>Nữ</option>
                                </select>
                                @error('gioi_tinh')<div class="invalid-feedback" id="gioi_tinh-error" role="alert">{{ $message }}</div>@enderror
                            </div>
                            <div class="col-12 col-md-6">
                                <label class="form-label" for="sdt">Số điện thoại <span aria-hidden="true">*</span></label>
                                <input class="form-control @error('sdt') is-invalid @enderror" id="sdt" name="sdt" type="tel" inputmode="tel" maxlength="10" autocomplete="tel" value="{{ $value('sdt') }}" required @error('sdt') aria-describedby="sdt-error" @enderror>
                                @error('sdt')<div class="invalid-feedback" id="sdt-error" role="alert">{{ $message }}</div>@enderror
                            </div>
                            <div class="col-12 col-md-6">
                                <label class="form-label" for="email">Email <span aria-hidden="true">*</span></label>
                                <input class="form-control @error('email') is-invalid @enderror" id="email" name="email" type="email" maxlength="100" autocomplete="email" value="{{ $value('email') }}" required @error('email') aria-describedby="email-error" @enderror>
                                @error('email')<div class="invalid-feedback" id="email-error" role="alert">{{ $message }}</div>@enderror
                            </div>
                            <div class="col-12 col-md-6">
                                <label class="form-label" for="dan_toc">Dân tộc <span aria-hidden="true">*</span></label>
                                <input class="form-control @error('dan_toc') is-invalid @enderror" id="dan_toc" name="dan_toc" type="text" maxlength="50" value="{{ $value('dan_toc') }}" required @error('dan_toc') aria-describedby="dan_toc-error" @enderror>
                                @error('dan_toc')<div class="invalid-feedback" id="dan_toc-error" role="alert">{{ $message }}</div>@enderror
                            </div>
                            <div class="col-12 col-md-6">
                                <label class="form-label" for="hoc_van">Trình độ học vấn <span aria-hidden="true">*</span></label>
                                <input class="form-control @error('hoc_van') is-invalid @enderror" id="hoc_van" name="hoc_van" type="text" maxlength="50" value="{{ $value('hoc_van') }}" required @error('hoc_van') aria-describedby="hoc_van-error" @enderror>
                                @error('hoc_van')<div class="invalid-feedback" id="hoc_van-error" role="alert">{{ $message }}</div>@enderror
                            </div>
                            <div class="col-12 col-md-6">
                                <label class="form-label" for="cccd">Số CCCD <span aria-hidden="true">*</span></label>
                                <input class="form-control @error('cccd') is-invalid @enderror" id="cccd" name="cccd" type="text" inputmode="numeric" maxlength="12" autocomplete="off" value="{{ $value('cccd') }}" required @error('cccd') aria-describedby="cccd-error" @enderror>
                                @error('cccd')<div class="invalid-feedback" id="cccd-error" role="alert">{{ $message }}</div>@enderror
                            </div>
                            <div class="col-12 col-md-6">
                                <label class="form-label" for="noi_cap_cccd">Nơi cấp CCCD <span aria-hidden="true">*</span></label>
                                <input class="form-control @error('noi_cap_cccd') is-invalid @enderror" id="noi_cap_cccd" name="noi_cap_cccd" type="text" maxlength="50" value="{{ $value('noi_cap_cccd') }}" required @error('noi_cap_cccd') aria-describedby="noi_cap_cccd-error" @enderror>
                                @error('noi_cap_cccd')<div class="invalid-feedback" id="noi_cap_cccd-error" role="alert">{{ $message }}</div>@enderror
                            </div>
                        </div>
                    </fieldset>

                    <fieldset class="mb-4">
                        <legend class="h5 fw-semibold">Địa chỉ liên hệ</legend>
                        <p class="form-text">Có thể nhập từng thành phần địa chỉ; các trường này không bắt buộc.</p>
                        <div class="row g-3">
                            @foreach ([
                                ['dia_chi_cu_the', 'Địa chỉ cụ thể', '255'],
                                ['phuong_xa', 'Phường/Xã', '100'],
                                ['quan_huyen', 'Quận/Huyện', '100'],
                                ['tinh_thanh', 'Tỉnh/Thành phố', '100'],
                            ] as [$field, $label, $max])
                                <div class="col-12 col-md-6">
                                    <label class="form-label" for="{{ $field }}">{{ $label }}</label>
                                    <input class="form-control @error($field) is-invalid @enderror" id="{{ $field }}" name="{{ $field }}" type="text" maxlength="{{ $max }}" value="{{ $value($field) }}" @error($field) aria-describedby="{{ $field }}-error" @enderror>
                                    @error($field)<div class="invalid-feedback" id="{{ $field }}-error" role="alert">{{ $message }}</div>@enderror
                                </div>
                            @endforeach
                        </div>
                    </fieldset>

                    <fieldset class="mb-4">
                        <legend class="h5 fw-semibold">Ảnh đại diện</legend>
                        <label class="form-label" for="anh_dai_dien">Tải ảnh mới</label>
                        <input class="form-control @error('anh_dai_dien') is-invalid @enderror" id="anh_dai_dien" name="anh_dai_dien" type="file" accept="image/jpeg,image/png,image/webp" aria-describedby="anh_dai_dien-help @error('anh_dai_dien') anh_dai_dien-error @enderror">
                        <div class="form-text" id="anh_dai_dien-help">JPEG, PNG hoặc WebP; tối đa 2 MB.</div>
                        @error('anh_dai_dien')<div class="invalid-feedback" id="anh_dai_dien-error" role="alert">{{ $message }}</div>@enderror
                        @if ($avatarUrl)
                            <img class="employee-avatar-preview mt-3" src="{{ $avatarUrl }}" alt="Ảnh đại diện hiện tại của {{ $employee->ho_ten }}" width="96" height="96">
                            <div class="form-check mt-2">
                                <input class="form-check-input @error('xoa_anh_dai_dien') is-invalid @enderror" id="xoa_anh_dai_dien" name="xoa_anh_dai_dien" type="checkbox" value="1" @checked(old('xoa_anh_dai_dien')) @error('xoa_anh_dai_dien') aria-describedby="xoa_anh_dai_dien-error" @enderror>
                                <label class="form-check-label" for="xoa_anh_dai_dien">Xóa ảnh đại diện hiện tại</label>
                                @error('xoa_anh_dai_dien')<div class="invalid-feedback" id="xoa_anh_dai_dien-error" role="alert">{{ $message }}</div>@enderror
                            </div>
                        @else
                            <p class="form-text mb-0 mt-2">Chưa có ảnh đại diện hợp lệ để hiển thị.</p>
                        @endif
                    </fieldset>

                    <div class="d-flex flex-wrap justify-content-end gap-2">
                        <a class="btn btn-outline-secondary" href="{{ route('backend.tongquan.index') }}"><i class="bi bi-x-circle button-icon" aria-hidden="true"></i>Hủy</a>
                        <button class="btn btn-primary" type="submit"><i class="bi bi-floppy button-icon" aria-hidden="true"></i>Lưu hồ sơ</button>
                    </div>
                </form>
            </div>
        </div>
    </main>
@endsection
