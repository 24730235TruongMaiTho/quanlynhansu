<div class="backend-edit-modal-page">
    <form class="card shadow-sm overflow-hidden" method="post" action="{{ route('backend.hopdong.update', $contract->ma_hd) }}" data-contract-form data-simple-modal-form>
        @csrf
        @method('PUT')
        <div class="alert alert-danger mb-3" role="alert" data-modal-form-error hidden></div>
        <div class="card-header bg-white py-3"><h2 class="h6 fw-semibold mb-0">Thông tin hợp đồng</h2></div>
        <div class="card-body p-4">
            @include('backend.hopdong.partials.form-fields', ['fieldPrefix' => 'contract-edit-'])
        </div>
        <div class="card-footer bg-white d-flex flex-wrap justify-content-end gap-2 py-3">
            <button class="btn btn-outline-secondary d-inline-flex align-items-center gap-2" type="button" data-contract-modal-close><i class="bi bi-x-lg" aria-hidden="true"></i>Hủy</button>
            <button class="btn btn-primary d-inline-flex align-items-center gap-2" type="submit" data-submit-edit data-submit data-submitting-text="Đang lưu..."><i class="bi bi-check2" aria-hidden="true"></i>Cập nhật hợp đồng</button>
        </div>
    </form>
</div>
