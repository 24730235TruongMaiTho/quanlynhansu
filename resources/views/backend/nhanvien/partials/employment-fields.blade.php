<section class="employee-form-section" aria-labelledby="employment-fields-title">
    @php
        $formEmployee = $employee ?? null;
        $terminatedEmployee = data_get($formEmployee, 'ky_hieu') === 'DA_NGHI';
    @endphp
    <h3 class="h6 fw-semibold" id="employment-fields-title">Công việc và trạng thái</h3>
    <div class="employee-form-grid">
        <div class="employee-field">
            <label class="form-label" for="ngay_vao_lam">Ngày vào làm <span aria-hidden="true">*</span></label>
            <input
                class="form-control @error('ngay_vao_lam') is-invalid @enderror"
                id="ngay_vao_lam"
                name="ngay_vao_lam"
                type="date"
                value="{{ old('ngay_vao_lam', data_get($formEmployee, 'ngay_vao_lam')) }}"
                required
                @error('ngay_vao_lam') aria-describedby="ngay_vao_lam-error" @enderror
                @if ($firstErrorField === 'ngay_vao_lam') data-error-focus @endif
            >
            @error('ngay_vao_lam')
                <div class="invalid-feedback" id="ngay_vao_lam-error">{{ $message }}</div>
            @enderror
        </div>

        <div class="employee-field">
            <label class="form-label" for="ma_pb">Phòng ban <span aria-hidden="true">*</span></label>
            <select
                class="form-select @error('ma_pb') is-invalid @enderror"
                id="ma_pb"
                name="ma_pb"
                required
                @error('ma_pb') aria-describedby="ma_pb-error" @enderror
                @if ($firstErrorField === 'ma_pb') data-error-focus @endif
            >
                <option value="">Chọn phòng ban</option>
                @foreach ($lookups['phong_ban'] as $department)
                    <option value="{{ $department->ma_pb }}" @selected((string) old('ma_pb', data_get($formEmployee, 'ma_pb')) === (string) $department->ma_pb)>
                        {{ $department->ten_pb }}
                    </option>
                @endforeach
            </select>
            @error('ma_pb')
                <div class="invalid-feedback" id="ma_pb-error">{{ $message }}</div>
            @enderror
        </div>

        <div class="employee-field">
            <label class="form-label" for="ma_cv">Chức vụ <span aria-hidden="true">*</span></label>
            <select
                class="form-select @error('ma_cv') is-invalid @enderror"
                id="ma_cv"
                name="ma_cv"
                required
                @error('ma_cv') aria-describedby="ma_cv-error" @enderror
                @if ($firstErrorField === 'ma_cv') data-error-focus @endif
            >
                <option value="">Chọn chức vụ</option>
                @foreach ($lookups['chuc_vu'] as $position)
                    <option value="{{ $position->ma_cv }}" @selected((string) old('ma_cv', data_get($formEmployee, 'ma_cv')) === (string) $position->ma_cv)>
                        {{ $position->ten_cv }}
                    </option>
                @endforeach
            </select>
            @error('ma_cv')
                <div class="invalid-feedback" id="ma_cv-error">{{ $message }}</div>
            @enderror
        </div>

        <div class="employee-field">
            @if ($terminatedEmployee)
                <span class="form-label d-block">Trạng thái làm việc</span>
                <p class="form-control-plaintext mb-1">{{ data_get($formEmployee, 'ten_tt') }}</p>
                <input type="hidden" name="ma_tt" value="{{ data_get($formEmployee, 'ma_tt') }}">
                <p class="form-text mb-0">Trạng thái không thể thay đổi qua cập nhật hồ sơ.</p>
            @else
                <label class="form-label" for="ma_tt">Trạng thái làm việc <span aria-hidden="true">*</span></label>
                <select
                    class="form-select @error('ma_tt') is-invalid @enderror"
                    id="ma_tt"
                    name="ma_tt"
                    required
                    @error('ma_tt') aria-describedby="ma_tt-error" @enderror
                    @if ($firstErrorField === 'ma_tt') data-error-focus @endif
                >
                    <option value="">Chọn trạng thái</option>
                    @foreach ($lookups['trang_thai'] as $status)
                        <option value="{{ $status->ma_tt }}" @selected((string) old('ma_tt', data_get($formEmployee, 'ma_tt')) === (string) $status->ma_tt)>
                            {{ $status->ten_tt }}
                        </option>
                    @endforeach
                </select>
                @error('ma_tt')
                    <div class="invalid-feedback" id="ma_tt-error">{{ $message }}</div>
                @enderror
            @endif
        </div>
    </div>
</section>
