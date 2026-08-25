@extends('backend.layouts.app')
@section('title', isset($contract) ? 'Sửa hợp đồng' : 'Thêm hợp đồng')
@section('content')
<main class="content-area"><h1>{{ isset($contract) ? 'Sửa hợp đồng' : 'Thêm hợp đồng' }}</h1>
<form class="card card-body" method="post" action="{{ isset($contract) ? route('backend.hopdong.update', $contract->ma_hd) : route('backend.hopdong.store') }}">@csrf @isset($contract) @method('PUT') @endisset
@if($errors->any())<div class="alert alert-danger" role="alert">Vui lòng kiểm tra lại dữ liệu.</div>@endif
<div class="row g-3"><div class="col-md-6"><label class="form-label" for="ma_nv">Nhân viên</label><select class="form-select" id="ma_nv" name="ma_nv" required>@foreach($employees as $employee)<option value="{{ $employee->ma_nv }}" @selected(old('ma_nv', $contract->ma_nv ?? '') === $employee->ma_nv)>{{ $employee->ma_nv }} — {{ $employee->ho_ten }}</option>@endforeach</select>@error('ma_nv')<div class="text-danger">{{ $message }}</div>@enderror</div>
<div class="col-md-6"><label class="form-label" for="ma_lhd">Loại hợp đồng</label><select class="form-select" id="ma_lhd" name="ma_lhd" required>@foreach($types as $type)<option value="{{ $type->ma_lhd }}" @selected((string) old('ma_lhd', $contract->ma_lhd ?? '') === (string) $type->ma_lhd)>{{ $type->ten_lhd }}</option>@endforeach</select></div>
<div class="col-md-4"><label class="form-label" for="ngay_ky">Ngày ký</label><input class="form-control" type="date" id="ngay_ky" name="ngay_ky" value="{{ old('ngay_ky', $contract->ngay_ky ?? '') }}" required></div>
<div class="col-md-4"><label class="form-label" for="ngay_het_han">Ngày hết hạn</label><input class="form-control" type="date" id="ngay_het_han" name="ngay_het_han" value="{{ old('ngay_het_han', $contract->ngay_het_han ?? '') }}"></div>
<div class="col-md-4"><label class="form-label" for="luong_co_ban">Lương cơ bản</label><input class="form-control" type="number" min="0" id="luong_co_ban" name="luong_co_ban" value="{{ old('luong_co_ban', $contract->luong_co_ban ?? '') }}" required></div></div>
<div class="mt-4 d-flex gap-2"><button class="btn btn-primary">Lưu</button><a class="btn btn-outline-secondary" href="{{ route('backend.hopdong.index') }}">Hủy</a></div></form></main>
@endsection
