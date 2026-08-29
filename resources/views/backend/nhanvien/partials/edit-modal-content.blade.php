<div class="employee-page employee-edit-modal-page">
    @if ($lookupError)
        <div class="alert alert-danger" role="alert">
            <p class="fw-semibold mb-1">Không tải được dữ liệu danh mục</p>
            <p class="mb-0">{{ $lookupError }}</p>
        </div>
    @elseif ($missingLookups !== [])
        <div class="alert alert-warning" role="alert">
            <p class="fw-semibold mb-1">Thiếu dữ liệu danh mục bắt buộc</p>
            <p class="mb-2">Chưa thể cập nhật nhân viên cho tới khi có đủ:</p>
            <ul class="mb-0">
                @foreach ($missingLookups as $missingLookup)
                    <li>{{ $missingLookup }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    @include('backend.nhanvien.partials.edit-form')
</div>
