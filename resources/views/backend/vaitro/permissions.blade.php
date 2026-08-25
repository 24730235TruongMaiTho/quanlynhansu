@extends('backend.layouts.app')
@section('title', 'Phân quyền vai trò')
@section('content')
<main class="content-area" aria-labelledby="permissions-title">
    <div class="page-header"><div><h1 id="permissions-title">Phân quyền: {{ $role->ten_vt }}</h1><p class="text-secondary">Chọn các quyền được cấp cho vai trò này.</p></div></div>
    @if(session('success'))<div class="alert alert-success" role="status">{{ session('success') }}</div>@endif
    @if($errors->any())<div class="alert alert-danger" role="alert">{{ $errors->first() }}</div>@endif
    <form method="post" action="{{ route('backend.vaitro.permissions.update', $role->ma_vt) }}">@csrf @method('PUT')
        <div class="row g-3">@foreach($permissions as $module => $items)<div class="col-md-6"><fieldset class="card card-body h-100"><legend class="h5">{{ $module }}</legend>
            @foreach($items as $permission)<div class="form-check"><input class="form-check-input" type="checkbox" name="ma_quyen[]" value="{{ $permission->ma_quyen }}" id="permission-{{ $permission->ma_quyen }}" @checked(in_array((int) $permission->ma_quyen, $selected, true))><label class="form-check-label" for="permission-{{ $permission->ma_quyen }}">{{ $permission->ten_quyen }} <small class="text-secondary">({{ $permission->ky_hieu_quyen }})</small></label></div>@endforeach
        </fieldset></div>@endforeach</div>
        <div class="mt-4 d-flex gap-2"><button class="btn btn-primary">Lưu phân quyền</button><a class="btn btn-outline-secondary" href="{{ route('backend.vaitro.index') }}">Quay lại</a></div>
    </form>
</main>
@endsection
