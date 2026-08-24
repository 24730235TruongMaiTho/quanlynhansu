@extends('backend.layouts.app')
@section('title', 'Tổng quan - Quản lý nhân sự')
@section('content')
<div class="content-area">
    <div class="page-header">
        <div class="left">
            <div>   
                <h1>
                    <i class="bi bi-house-fill text-danger me-2"></i>
                    Tổng quan
                </h1>
                <nav aria-label="breadcrumb">
                    <ol class="breadcrumb">
                        <li class="breadcrumb-item"><a href="{{ route('backend.tongquan.index') }}">Trang chủ</a></li>
                        <li class="breadcrumb-item active" aria-current="page">Tổng quan</li>
                    </ol>
                </nav>
            </div>
        </div>
    </div>
</div>
@endsection
@push('styles')

@endpush
@push('scripts')
<script>

</script>
@endpush