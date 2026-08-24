<section class="employee-form-section" aria-labelledby="address-fields-title">
    @php($formEmployee = $employee ?? null)
    <h3 class="h6 fw-semibold" id="address-fields-title">Địa chỉ</h3>
    <div class="employee-form-grid">
        @foreach ([
            'dia_chi_cu_the' => ['Địa chỉ cụ thể', 255, 'street-address'],
            'phuong_xa' => ['Phường/Xã', 100, 'address-level3'],
            'quan_huyen' => ['Quận/Huyện', 100, 'address-level2'],
            'tinh_thanh' => ['Tỉnh/Thành phố', 100, 'address-level1'],
        ] as $field => [$label, $maxLength, $autocomplete])
            <div class="employee-field {{ $field === 'dia_chi_cu_the' ? 'employee-field-wide' : '' }}">
                <label class="form-label" for="{{ $field }}">{{ $label }} <span aria-hidden="true">*</span></label>
                <input
                    class="form-control @error($field) is-invalid @enderror"
                    id="{{ $field }}"
                    name="{{ $field }}"
                    type="text"
                    maxlength="{{ $maxLength }}"
                    autocomplete="{{ $autocomplete }}"
                    value="{{ old($field, data_get($formEmployee, $field)) }}"
                    required
                    @error($field) aria-describedby="{{ $field }}-error" @enderror
                    @if ($firstErrorField === $field) data-error-focus @endif
                >
                @error($field)
                    <div class="invalid-feedback" id="{{ $field }}-error">{{ $message }}</div>
                @enderror
            </div>
        @endforeach
    </div>
</section>
