@php
    $dialogKey = preg_replace('/[^A-Za-z0-9_-]/', '-', (string) $employee->ma_nv);
    $destroyDialogId = 'employee-destroy-' . $dialogKey;
    $wrapActions = $wrapActions ?? true;
    $includeResetPassword = $includeResetPassword ?? true;
@endphp

@if ($wrapActions)
<div class="employee-action-dialogs table-actions d-inline-flex flex-wrap gap-2 mt-2" data-action-dialogs>
@endif

@if ($includeResetPassword)
@can(\App\Enums\NhanVienPermission::DatLaiMatKhau->value)
    <form
        method="POST"
        action="{{ route('backend.nhanvien.reset-password', ['ma_nv' => $employee->ma_nv]) }}"
        class="d-inline-flex"
        data-reset-password-form
        data-confirm-action="reset-password"
        data-confirm-message="Xác nhận reset mật khẩu?"
    >
        @csrf
        <button
            class="btn btn-outline-warning btn-icon-text"
            type="submit"
            aria-label="Đặt lại mật khẩu cho {{ $employee->ho_ten }}"
            title="Đặt lại mật khẩu cho {{ $employee->ho_ten }}"
        ><i class="bi bi-key" aria-hidden="true"></i><span>Đặt lại mật khẩu</span></button>
    </form>
@endcan
@endif

@can(\App\Enums\NhanVienPermission::Xoa->value)
@if ((string) auth()->id() !== (string) $employee->ma_nv)

    <dialog
        class="employee-action-dialog"
        id="{{ $destroyDialogId }}"
        data-action-dialog
        aria-labelledby="{{ $destroyDialogId }}-title"
    >
        <form
            method="POST"
            action="{{ route('backend.nhanvien.destroy', ['ma_nv' => $employee->ma_nv]) }}"
            data-dialog-form
            data-confirm-message="Xác nhận xóa cứng nếu chưa có lịch sử; nếu đã có lịch sử, hồ sơ sẽ được kết thúc theo lịch sử."
        >
            @csrf
            @method('DELETE')
            <h2 class="h5" id="{{ $destroyDialogId }}-title">Xóa hoặc kết thúc hồ sơ</h2>
            <p>Xóa cứng nếu chưa có lịch sử; nếu đã có lịch sử, hệ thống chỉ kết thúc hồ sơ và giữ lại lịch sử liên quan.</p>
            <div class="d-flex justify-content-end gap-2">
                <button type="button" class="btn btn-outline-secondary btn-icon-text" data-dialog-cancel><i class="bi bi-x-lg" aria-hidden="true"></i><span>Hủy</span></button>
                <button type="submit" class="btn btn-danger btn-icon-text" data-dialog-submit><i class="bi bi-check2" aria-hidden="true"></i><span>Xác nhận thao tác</span></button>
            </div>
        </form>
    </dialog>
@endif
@endcan

@if ($wrapActions)
</div>
@endif
