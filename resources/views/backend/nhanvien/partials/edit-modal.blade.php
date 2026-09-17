<dialog
    class="employee-edit-dialog"
    id="employee-edit-modal"
    data-employee-edit-modal
    aria-modal="true"
    aria-labelledby="employee-edit-modal-title"
    aria-describedby="edit-form-help"
>
    <div class="employee-edit-dialog-header">
        <h2
            class="h5 mb-0"
            id="employee-edit-modal-title"
            data-employee-edit-title
            data-edit-title="Chỉnh sửa hồ sơ nhân viên"
            data-create-title="Thêm nhân viên"
        >Chỉnh sửa hồ sơ nhân viên</h2>
        <button class="btn btn-outline-secondary btn-sm btn-icon-text" type="button" data-employee-edit-close>
                    <i class="bi bi-x-lg" aria-hidden="true"></i><span>Đóng</span>
        </button>
    </div>
    <div class="employee-edit-dialog-status" id="edit-form-help" data-employee-edit-status aria-live="polite">
        <p class="mb-0" data-employee-edit-loading hidden>Đang tải biểu mẫu chỉnh sửa...</p>
        <p class="alert alert-danger mb-2" role="alert" data-employee-edit-error hidden></p>
    </div>
    <div data-employee-edit-content></div>
</dialog>
