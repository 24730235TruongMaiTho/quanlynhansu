<section class="employee-form-section" aria-labelledby="personal-fields-title">
    @php
        $formEmployee = $employee ?? null;
        $editingEmployee = $formEmployee !== null;
    @endphp
    <h3 class="h6 fw-semibold" id="personal-fields-title">Thông tin cá nhân và liên hệ</h3>
    <div class="employee-form-grid">
        <div class="employee-field employee-field-wide">
            <label class="form-label" for="anh_dai_dien">Ảnh đại diện</label>
            <input
                class="form-control @error('anh_dai_dien') is-invalid @enderror"
                id="anh_dai_dien"
                name="anh_dai_dien"
                type="file"
                data-avatar-upload
                accept="image/jpeg,image/png,image/webp"
                aria-describedby="anh_dai_dien-help @error('anh_dai_dien') anh_dai_dien-error @enderror"
                @if ($firstErrorField === 'anh_dai_dien') data-error-focus @endif
            >
            <div class="form-text" id="anh_dai_dien-help">JPEG, PNG hoặc WebP; tối đa 2 MB.</div>
            @error('anh_dai_dien')
                <div class="invalid-feedback" id="anh_dai_dien-error">{{ $message }}</div>
            @enderror

            @if ($editingEmployee)
                @if ($avatarUrl ?? null)
                    <img
                        class="employee-avatar-preview mt-3"
                        src="{{ $avatarUrl }}"
                        alt="Ảnh đại diện hiện tại của {{ $formEmployee->ho_ten }}"
                        width="96"
                        height="96"
                    >
                @else
                    <p class="form-text mb-0 mt-2">Chưa có ảnh đại diện hợp lệ để hiển thị.</p>
                @endif
                <div class="form-check mt-2">
                    <input
                        class="form-check-input @error('xoa_anh_dai_dien') is-invalid @enderror"
                        id="xoa_anh_dai_dien"
                        name="xoa_anh_dai_dien"
                        type="checkbox"
                        value="1"
                        data-avatar-delete
                        @checked(old('xoa_anh_dai_dien'))
                        @error('xoa_anh_dai_dien') aria-describedby="xoa_anh_dai_dien-error" @enderror
                        @if ($firstErrorField === 'xoa_anh_dai_dien') data-error-focus @endif
                    >
                    <label class="form-check-label" for="xoa_anh_dai_dien">Xóa ảnh đại diện hiện tại</label>
                    @error('xoa_anh_dai_dien')
                        <div class="invalid-feedback" id="xoa_anh_dai_dien-error">{{ $message }}</div>
                    @enderror
                </div>
            @endif
        </div>

        <div class="employee-field">
            <label class="form-label" for="ho_ten">Họ và tên <span aria-hidden="true">*</span></label>
            <input
                class="form-control @error('ho_ten') is-invalid @enderror"
                id="ho_ten"
                name="ho_ten"
                type="text"
                maxlength="50"
                autocomplete="name"
                value="{{ old('ho_ten', data_get($formEmployee, 'ho_ten')) }}"
                required
                @error('ho_ten') aria-describedby="ho_ten-error" @enderror
                @if ($firstErrorField === 'ho_ten') data-error-focus @endif
            >
            @error('ho_ten')
                <div class="invalid-feedback" id="ho_ten-error">{{ $message }}</div>
            @enderror
        </div>

        <div class="employee-field">
            <label class="form-label" for="ngay_sinh">Ngày sinh <span aria-hidden="true">*</span></label>
            <input
                class="form-control @error('ngay_sinh') is-invalid @enderror"
                id="ngay_sinh"
                name="ngay_sinh"
                type="date"
                value="{{ old('ngay_sinh', data_get($formEmployee, 'ngay_sinh')) }}"
                required
                @error('ngay_sinh') aria-describedby="ngay_sinh-error" @enderror
                @if ($firstErrorField === 'ngay_sinh') data-error-focus @endif
            >
            @error('ngay_sinh')
                <div class="invalid-feedback" id="ngay_sinh-error">{{ $message }}</div>
            @enderror
        </div>

        <div class="employee-field">
            <label class="form-label" for="gioi_tinh">Giới tính <span aria-hidden="true">*</span></label>
            <select
                class="form-select @error('gioi_tinh') is-invalid @enderror"
                id="gioi_tinh"
                name="gioi_tinh"
                required
                @error('gioi_tinh') aria-describedby="gioi_tinh-error" @enderror
                @if ($firstErrorField === 'gioi_tinh') data-error-focus @endif
            >
                <option value="">Chọn giới tính</option>
                <option value="1" @selected((string) old('gioi_tinh', data_get($formEmployee, 'gioi_tinh')) === '1')>Nam</option>
                <option value="0" @selected((string) old('gioi_tinh', data_get($formEmployee, 'gioi_tinh')) === '0')>Nữ</option>
            </select>
            @error('gioi_tinh')
                <div class="invalid-feedback" id="gioi_tinh-error">{{ $message }}</div>
            @enderror
        </div>

        <div class="employee-field">
            <label class="form-label" for="dan_toc">Dân tộc <span aria-hidden="true">*</span></label>
            <input
                class="form-control @error('dan_toc') is-invalid @enderror"
                id="dan_toc"
                name="dan_toc"
                type="text"
                maxlength="50"
                value="{{ old('dan_toc', data_get($formEmployee, 'dan_toc')) }}"
                required
                @error('dan_toc') aria-describedby="dan_toc-error" @enderror
                @if ($firstErrorField === 'dan_toc') data-error-focus @endif
            >
            @error('dan_toc')
                <div class="invalid-feedback" id="dan_toc-error">{{ $message }}</div>
            @enderror
        </div>

        <div class="employee-field">
            <label class="form-label" for="hoc_van">Trình độ học vấn <span aria-hidden="true">*</span></label>
            <input
                class="form-control @error('hoc_van') is-invalid @enderror"
                id="hoc_van"
                name="hoc_van"
                type="text"
                maxlength="50"
                value="{{ old('hoc_van', data_get($formEmployee, 'hoc_van')) }}"
                required
                @error('hoc_van') aria-describedby="hoc_van-error" @enderror
                @if ($firstErrorField === 'hoc_van') data-error-focus @endif
            >
            @error('hoc_van')
                <div class="invalid-feedback" id="hoc_van-error">{{ $message }}</div>
            @enderror
        </div>

        <div class="employee-field">
            <label class="form-label" for="cccd">Số CCCD <span aria-hidden="true">*</span></label>
            <input
                class="form-control @error('cccd') is-invalid @enderror"
                id="cccd"
                name="cccd"
                type="text"
                inputmode="numeric"
                pattern="[0-9]{12}"
                maxlength="12"
                autocomplete="off"
                value="{{ old('cccd', data_get($formEmployee, 'cccd')) }}"
                required
                @error('cccd') aria-describedby="cccd-error" @enderror
                @if ($firstErrorField === 'cccd') data-error-focus @endif
            >
            @error('cccd')
                <div class="invalid-feedback" id="cccd-error">{{ $message }}</div>
            @enderror
        </div>

        <div class="employee-field">
            <label class="form-label" for="noi_cap_cccd">Nơi cấp CCCD <span aria-hidden="true">*</span></label>
            <input
                class="form-control @error('noi_cap_cccd') is-invalid @enderror"
                id="noi_cap_cccd"
                name="noi_cap_cccd"
                type="text"
                maxlength="50"
                value="{{ old('noi_cap_cccd', data_get($formEmployee, 'noi_cap_cccd')) }}"
                required
                @error('noi_cap_cccd') aria-describedby="noi_cap_cccd-error" @enderror
                @if ($firstErrorField === 'noi_cap_cccd') data-error-focus @endif
            >
            @error('noi_cap_cccd')
                <div class="invalid-feedback" id="noi_cap_cccd-error">{{ $message }}</div>
            @enderror
        </div>

        <div class="employee-field">
            <label class="form-label" for="sdt">Số điện thoại <span aria-hidden="true">*</span></label>
            <input
                class="form-control @error('sdt') is-invalid @enderror"
                id="sdt"
                name="sdt"
                type="tel"
                inputmode="tel"
                pattern="0[0-9]{9}"
                maxlength="10"
                autocomplete="tel"
                value="{{ old('sdt', data_get($formEmployee, 'sdt')) }}"
                required
                @error('sdt') aria-describedby="sdt-error" @enderror
                @if ($firstErrorField === 'sdt') data-error-focus @endif
            >
            @error('sdt')
                <div class="invalid-feedback" id="sdt-error">{{ $message }}</div>
            @enderror
        </div>

        <div class="employee-field">
            <label class="form-label" for="email">Email <span aria-hidden="true">*</span></label>
            <input
                class="form-control @error('email') is-invalid @enderror"
                id="email"
                name="email"
                type="email"
                maxlength="100"
                autocomplete="email"
                value="{{ old('email', data_get($formEmployee, 'email')) }}"
                required
                @error('email') aria-describedby="email-error" @enderror
                @if ($firstErrorField === 'email') data-error-focus @endif
            >
            @error('email')
                <div class="invalid-feedback" id="email-error">{{ $message }}</div>
            @enderror
        </div>
    </div>
</section>
