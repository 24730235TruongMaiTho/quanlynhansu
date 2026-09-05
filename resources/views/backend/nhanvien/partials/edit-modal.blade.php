<dialog
    class="employee-edit-dialog"
    id="employee-edit-modal"
    data-employee-edit-modal
    aria-modal="true"
    aria-labelledby="employee-edit-modal-title"
    aria-describedby="edit-form-help"
>
    <div class="employee-edit-dialog-header">
        <h2 class="h5 mb-0" id="employee-edit-modal-title">Chỉnh sửa hồ sơ nhân viên</h2>
        <button class="btn btn-outline-secondary btn-sm" type="button" data-employee-edit-close>
                    <i class="bi bi-x-lg" aria-hidden="true"></i>Đóng
        </button>
    </div>
    <div class="employee-edit-dialog-status" id="edit-form-help" data-employee-edit-status aria-live="polite">
        <p class="mb-0" data-employee-edit-loading hidden>Đang tải biểu mẫu chỉnh sửa...</p>
        <p class="alert alert-danger mb-2" role="alert" data-employee-edit-error hidden></p>
        <div class="d-flex flex-wrap align-items-center gap-2" data-employee-edit-recovery hidden>
            <a class="btn btn-outline-secondary btn-sm" href="#" data-employee-edit-fallback hidden>
                    <i class="bi bi-box-arrow-up-right" aria-hidden="true"></i>Mở trang chỉnh sửa đầy đủ
            </a>
            <button class="btn btn-primary btn-sm" type="button" data-employee-edit-retry hidden>
                    <i class="bi bi-arrow-clockwise" aria-hidden="true"></i>Thử lại
            </button>
        </div>
    </div>
    <div data-employee-edit-content></div>
</dialog>
