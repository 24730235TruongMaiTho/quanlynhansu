@php
    $dialogKey = preg_replace('/[^A-Za-z0-9_-]/', '-', (string) $employee->ma_nv);
    $resetDialogId = 'employee-reset-password-' . $dialogKey;
    $destroyDialogId = 'employee-destroy-' . $dialogKey;
@endphp

<div class="employee-action-dialogs d-inline-flex flex-wrap gap-2 mt-2" data-action-dialogs>
    <button
        class="btn btn-sm btn-outline-secondary"
        type="button"
        data-dialog-open="{{ $resetDialogId }}"
        aria-controls="{{ $resetDialogId }}"
    >Đặt lại mật khẩu</button>
    <button
        class="btn btn-sm btn-outline-danger"
        type="button"
        data-dialog-open="{{ $destroyDialogId }}"
        aria-controls="{{ $destroyDialogId }}"
    >Xóa hoặc kết thúc</button>

    <dialog
        class="employee-action-dialog"
        id="{{ $resetDialogId }}"
        data-action-dialog
        aria-labelledby="{{ $resetDialogId }}-title"
    >
        <form method="POST" action="{{ route('backend.nhanvien.reset-password', ['ma_nv' => $employee->ma_nv]) }}" data-dialog-form>
            @csrf
            @method('PATCH')
            <h2 class="h5" id="{{ $resetDialogId }}-title">Đặt lại mật khẩu nhân viên</h2>
            <p>Mật khẩu sẽ được thay bằng quy ước tĩnh <code>nhom3@{năm thao tác}</code>; mật khẩu thực không hiển thị trên trang.</p>
            <div class="d-flex justify-content-end gap-2">
                <button type="button" class="btn btn-outline-secondary" data-dialog-cancel>Hủy</button>
                <button type="submit" class="btn btn-primary" data-dialog-submit>Đặt lại mật khẩu</button>
            </div>
        </form>
    </dialog>

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
                <button type="button" class="btn btn-outline-secondary" data-dialog-cancel>Hủy</button>
                <button type="submit" class="btn btn-danger" data-dialog-submit>Xác nhận thao tác</button>
            </div>
        </form>
    </dialog>
</div>
