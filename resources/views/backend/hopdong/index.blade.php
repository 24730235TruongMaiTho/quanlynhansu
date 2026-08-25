@extends('backend.layouts.app')

@section('title', 'Quản lý hợp đồng')

@section('content')
<main class="content-area" aria-labelledby="contract-title">
    <div class="page-header"><div><h1 id="contract-title">Quản lý hợp đồng</h1><p class="text-secondary mb-0">Cảnh báo hợp đồng hết hạn trong {{ config('hopdong.expiring_warning_days', 30) }} ngày.</p></div>
        @can(\App\Enums\HopDongPermission::Tao->value)<a class="btn btn-primary" href="{{ route('backend.hopdong.create') }}">Thêm hợp đồng</a>@endcan
    </div>
    @if(session('success'))<div class="alert alert-success" role="status">{{ session('success') }}</div>@endif
    <form class="card card-body mb-3" method="get"><div class="row g-2">
        <div class="col-md-5"><label class="form-label" for="keyword">Nhân viên</label><input class="form-control" id="keyword" name="keyword" value="{{ request('keyword') }}"></div>
        <div class="col-md-4"><label class="form-label" for="ma_lhd">Loại hợp đồng</label><select class="form-select" id="ma_lhd" name="ma_lhd"><option value="">Tất cả</option>@foreach($types as $type)<option value="{{ $type->ma_lhd }}" @selected((string) request('ma_lhd') === (string) $type->ma_lhd)>{{ $type->ten_lhd }}</option>@endforeach</select></div>
        <div class="col-md-3 d-flex align-items-end"><button class="btn btn-outline-primary w-100">Lọc</button></div>
    </div></form>
    <div class="card"><div class="table-responsive"><table class="table align-middle mb-0"><thead><tr><th>Mã</th><th>Nhân viên</th><th>Loại</th><th>Ngày ký</th><th>Hết hạn</th><th>Lương cơ bản</th><th>Thao tác</th></tr></thead><tbody>
        @forelse($contracts as $contract)<tr class="{{ $contract->sap_het_han ? 'table-warning' : '' }}"><td>{{ $contract->ma_hd }}</td><td>{{ $contract->ma_nv }} — {{ $contract->ho_ten }}</td><td>{{ $contract->ten_lhd }}</td><td>{{ $contract->ngay_ky }}</td><td>{{ $contract->ngay_het_han ?? 'Không thời hạn' }}</td><td>{{ number_format($contract->luong_co_ban, 0, ',', '.') }} ₫</td><td><div class="d-flex gap-2">@can(\App\Enums\HopDongPermission::Sua->value)<a class="btn btn-sm btn-outline-primary" href="{{ route('backend.hopdong.edit', $contract->ma_hd) }}">Sửa</a>@endcan @can(\App\Enums\HopDongPermission::Xoa->value)<form method="post" action="{{ route('backend.hopdong.destroy', $contract->ma_hd) }}">@csrf @method('DELETE')<button class="btn btn-sm btn-outline-danger">Xóa</button></form>@endcan</div></td></tr>
        @empty<tr><td colspan="7" class="text-center text-secondary py-5">Không có hợp đồng phù hợp.</td></tr>@endforelse
    </tbody></table></div></div><div class="mt-3">{{ $contracts->links() }}</div>
</main>
@endsection
