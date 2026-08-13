@if (session('success'))
    <div class="alert alert-success" role="status">
        {{ session('success') }}
    </div>
@endif

@if ($errors->any())
    <div class="alert alert-danger" role="alert">
        <p class="fw-semibold mb-1">Chưa thể lưu nhân viên. Vui lòng kiểm tra:</p>
        <ul class="mb-0">
            @foreach ($errors->all() as $error)
                <li>{{ $error }}</li>
            @endforeach
        </ul>
    </div>
@endif
