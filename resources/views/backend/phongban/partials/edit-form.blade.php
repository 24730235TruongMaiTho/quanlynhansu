<form
    method="POST"
    action="{{ route('backend.phongban.update', ['ma_pb' => $department->ma_pb]) }}"
    aria-busy="false"
    data-phong-ban-form
    data-simple-edit-form
>
    @csrf
    @method('PUT')

    <div class="alert alert-danger mb-3" role="alert" data-modal-form-error hidden></div>
    <h2 class="h5 mb-3" id="department-form-title">Thông tin phòng ban</h2>
    <div class="mb-3">
        <label class="form-label" for="ten_pb">Tên phòng ban <span aria-hidden="true">*</span></label>
        <input class="form-control @error('ten_pb') is-invalid @enderror" id="ten_pb" name="ten_pb" type="text" maxlength="100" required value="{{ old('ten_pb', $department->ten_pb) }}" autocomplete="organization-title">
        @error('ten_pb')
            <div class="invalid-feedback">{{ $message }}</div>
        @enderror
    </div>
    <button class="btn btn-primary" type="submit" data-submit-edit data-submit data-submitting-text="Đang lưu...">Lưu thay đổi</button>
</form>
