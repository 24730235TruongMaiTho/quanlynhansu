<dialog
    class="backend-edit-dialog"
    id="{{ $modalId }}"
    data-simple-edit-modal
    aria-modal="true"
    aria-labelledby="{{ $modalId }}-title"
    aria-describedby="{{ $modalId }}-status"
>
    <div class="backend-edit-dialog-header">
        <h2
            class="h5 mb-0"
            id="{{ $modalId }}-title"
            data-edit-modal-title
            data-edit-title="{{ $editTitle ?? $title }}"
            data-create-title="{{ $createTitle ?? $title }}"
        >{{ $title }}</h2>
        <button class="btn btn-outline-secondary btn-sm d-inline-flex align-items-center gap-2" type="button" data-edit-modal-close>
                <i class="bi bi-x-lg" aria-hidden="true"></i>Đóng
        </button>
    </div>
    <div class="backend-edit-dialog-status" id="{{ $modalId }}-status" aria-live="polite">
        <p class="mb-0" data-edit-modal-loading hidden>Đang tải biểu mẫu chỉnh sửa...</p>
        <p class="alert alert-danger mb-2" role="alert" data-edit-modal-error hidden></p>
    </div>
    <div data-edit-modal-content></div>
</dialog>
