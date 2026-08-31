<dialog
    class="backend-edit-dialog"
    id="{{ $modalId }}"
    data-simple-edit-modal
    aria-modal="true"
    aria-labelledby="{{ $modalId }}-title"
    aria-describedby="{{ $modalId }}-status"
>
    <div class="backend-edit-dialog-header">
        <h2 class="h5 mb-0" id="{{ $modalId }}-title">{{ $title }}</h2>
        <button class="btn btn-outline-secondary btn-sm" type="button" data-edit-modal-close>
            Đóng
        </button>
    </div>
    <div class="backend-edit-dialog-status" id="{{ $modalId }}-status" aria-live="polite">
        <p class="mb-0" data-edit-modal-loading hidden>Đang tải biểu mẫu chỉnh sửa...</p>
        <p class="alert alert-danger mb-2" role="alert" data-edit-modal-error hidden></p>
        <div class="d-flex flex-wrap align-items-center gap-2" data-edit-modal-recovery hidden>
            <a class="btn btn-outline-secondary btn-sm" href="#" data-edit-modal-fallback hidden>
                Mở trang chỉnh sửa đầy đủ
            </a>
            <button class="btn btn-primary btn-sm" type="button" data-edit-modal-retry hidden>
                Thử lại
            </button>
        </div>
    </div>
    <div data-edit-modal-content></div>
</dialog>
