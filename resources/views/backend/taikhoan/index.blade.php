@extends('backend.layouts.app')
@section('title', 'Phân quyền tài khoản')
@section('content')
<main class="content-area" aria-labelledby="account-role-title"><h1 id="account-role-title">Phân quyền tài khoản</h1>
<form class="mb-3" method="get"><label class="visually-hidden" for="keyword">Tìm tài khoản</label><div class="input-group"><input class="form-control" id="keyword" name="keyword" value="{{ request('keyword') }}" placeholder="Mã hoặc tên nhân viên"><button class="btn btn-outline-primary">Tìm</button></div></form>
@if(session('success'))<div class="alert alert-success">{{ session('success') }}</div>@endif
<div class="card"><div class="table-responsive"><table class="table align-middle mb-0"><thead><tr><th>Mã</th><th>Nhân viên</th><th>Email</th><th>Vai trò</th><th>Cập nhật</th></tr></thead><tbody>
@forelse($accounts as $account)<tr><td>{{ $account->ma_nv }}</td><td>{{ $account->ho_ten }}</td><td>{{ $account->email }}</td><td>{{ $account->ten_vt }}</td><td><form class="d-flex gap-2" method="post" action="{{ route('backend.taikhoan.assign-role', $account->ma_nv) }}">@csrf @method('PATCH')<select class="form-select" name="ma_vt" aria-label="Vai trò của {{ $account->ho_ten }}">@foreach($roles as $role)<option value="{{ $role->ma_vt }}" @selected($role->ma_vt == $account->ma_vt)>{{ $role->ten_vt }}</option>@endforeach</select><button class="btn btn-primary">Lưu</button></form></td></tr>
@empty<tr><td colspan="5" class="text-center text-secondary py-5">Không có tài khoản phù hợp.</td></tr>@endforelse
</tbody></table></div></div></main>
@endsection
