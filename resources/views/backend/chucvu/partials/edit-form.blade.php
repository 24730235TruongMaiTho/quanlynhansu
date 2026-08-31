<form
    method="POST"
    action="{{ route('backend.chucvu.update', ['ma_cv' => $position->ma_cv]) }}"
    aria-busy="false"
    data-chuc-vu-form
    data-simple-edit-form
>
    @csrf
    @method('PUT')

    <div class="alert alert-danger mb-3" role="alert" data-modal-form-error hidden></div>
    <h2 class="h5 mb-3" id="position-form-title">Thông tin chức vụ</h2>
    <div class="mb-3">
        <label class="form-label" for="ten_cv">Tên chức vụ <span aria-hidden="true">*</span></label>
        <input class="form-control @error('ten_cv') is-invalid @enderror" id="ten_cv" name="ten_cv" type="text" maxlength="100" required value="{{ old('ten_cv', $position->ten_cv) }}" autocomplete="organization-title">
        @error('ten_cv')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>
    <div class="mb-3">
        <label class="form-label" for="he_so_phu_cap">Hệ số phụ cấp <span aria-hidden="true">*</span></label>
        <input class="form-control @error('he_so_phu_cap') is-invalid @enderror" id="he_so_phu_cap" name="he_so_phu_cap" type="number" min="0" max="99.99" step="0.01" required value="{{ old('he_so_phu_cap', number_format((float) $position->he_so_phu_cap, 2, '.', '')) }}" inputmode="decimal">
        @error('he_so_phu_cap')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>
    <button class="btn btn-primary" type="submit" data-submit-edit data-submit data-submitting-text="Đang lưu...">Lưu thay đổi</button>
</form>
