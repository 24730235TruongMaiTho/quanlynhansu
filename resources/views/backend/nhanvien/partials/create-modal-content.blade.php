<div class="employee-page employee-create-modal-page">
    @if ($lookupError)
        <div class="alert alert-danger" role="alert">
            <p class="fw-semibold mb-1">Không tải được dữ liệu danh mục</p>
            <p class="mb-0">{{ $lookupError }}</p>
        </div>
    @elseif ($missingLookups !== [])
        <div class="alert alert-warning" role="alert">
            <p class="fw-semibold mb-1">Thiếu dữ liệu danh mục bắt buộc</p>
            <p class="mb-2">Chưa thể tạo nhân viên cho tới khi có đủ:</p>
            <ul class="mb-0">
                @foreach ($missingLookups as $missingLookup)
                    <li>{{ $missingLookup }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <div class="alert alert-info" role="note">
        <p class="mb-0"><strong>Mã nhân viên được hệ thống tự cấp</strong> sau khi lưu thành công.</p>
    </div>

    @include('backend.nhanvien.partials.create-form', ['modalOnly' => true])
</div>
