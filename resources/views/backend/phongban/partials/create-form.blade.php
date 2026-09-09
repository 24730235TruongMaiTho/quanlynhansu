<form
    method="POST"
    action="{{ route('backend.phongban.store') }}"
    aria-busy="false"
    data-phong-ban-form
    data-simple-modal-form
>
    @csrf

    <div class="alert alert-danger mb-3" role="alert" data-modal-form-error hidden></div>
    <h2 class="h5 mb-3" id="department-form-title">Thông tin phòng ban</h2>
    <div class="mb-3">
        <label class="form-label" for="ten_pb">Tên phòng ban <span aria-hidden="true">*</span></label>
        <input class="form-control @error('ten_pb') is-invalid @enderror" id="ten_pb" name="ten_pb" type="text" maxlength="100" required value="{{ old('ten_pb') }}" autocomplete="organization-title">
        @error('ten_pb')
            <div class="invalid-feedback">{{ $message }}</div>
        @enderror
    </div>
    <div class="d-flex flex-wrap gap-2">
        <button class="btn btn-primary d-inline-flex align-items-center gap-2" type="submit" data-submit-edit data-submit data-submitting-text="Đang lưu..."><i class="bi bi-check2" aria-hidden="true"></i>Lưu phòng ban</button>
        <a class="btn btn-outline-secondary d-inline-flex align-items-center gap-2" href="{{ route('backend.phongban.index') }}"><i class="bi bi-x-lg" aria-hidden="true"></i>Hủy</a>
    </div>
</form>
