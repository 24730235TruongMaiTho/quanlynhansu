@php($fieldPrefix = $fieldPrefix ?? '')

<div class="row g-3">
    <div class="col-md-6">
        <label class="form-label" for="{{ $fieldPrefix }}ma_nv">Nhân viên <span class="text-danger">*</span></label>
        <select class="form-select" id="{{ $fieldPrefix }}ma_nv" name="ma_nv" required>
            @foreach ($employees as $employee)
                <option value="{{ $employee->ma_nv }}" @selected(old('ma_nv', $contract->ma_nv ?? '') === $employee->ma_nv)>{{ $employee->ma_nv }} — {{ $employee->ho_ten }}</option>
            @endforeach
        </select>
        @error('ma_nv')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
    </div>
    <div class="col-md-6">
        <label class="form-label" for="{{ $fieldPrefix }}ma_lhd">Loại hợp đồng <span class="text-danger">*</span></label>
        <select class="form-select" id="{{ $fieldPrefix }}ma_lhd" name="ma_lhd" required>
            @foreach ($types as $type)
                <option value="{{ $type->ma_lhd }}" data-contract-term="{{ (int) $type->ma_lhd === 1 ? 'indefinite' : 'finite' }}" @selected((string) old('ma_lhd', $contract->ma_lhd ?? '') === (string) $type->ma_lhd)>{{ $type->ten_lhd }}</option>
            @endforeach
        </select>
        @error('ma_lhd')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
    </div>
    <div class="col-md-4">
        <label class="form-label" for="{{ $fieldPrefix }}ngay_ky">Ngày ký <span class="text-danger">*</span></label>
        <input class="form-control" type="date" id="{{ $fieldPrefix }}ngay_ky" name="ngay_ky" value="{{ \App\Support\DisplayDateFormatter::formatForInput(old('ngay_ky', $contract->ngay_ky ?? '')) }}" required>
        @error('ngay_ky')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
    </div>
    <div class="col-md-4">
        <label class="form-label" for="{{ $fieldPrefix }}ngay_het_han">Ngày hết hạn</label>
        <input class="form-control" type="date" id="{{ $fieldPrefix }}ngay_het_han" name="ngay_het_han" value="{{ \App\Support\DisplayDateFormatter::formatForInput(old('ngay_het_han', $contract->ngay_het_han ?? '')) }}">
        <div class="form-text" data-expiry-required-marker>Để trống đối với hợp đồng không xác định thời hạn.</div>
        @error('ngay_het_han')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
    </div>
    <div class="col-md-4">
        <label class="form-label" for="{{ $fieldPrefix }}luong_co_ban">Lương cơ bản <span class="text-danger">*</span></label>
        <div class="input-group">
            <input class="form-control" type="text" inputmode="numeric" id="{{ $fieldPrefix }}luong_co_ban" name="luong_co_ban" value="{{ old('luong_co_ban', $contract->luong_co_ban ?? '') }}" required>
            <span class="input-group-text">VNĐ</span>
        </div>
        @error('luong_co_ban')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
    </div>
</div>
