@extends('backend.layouts.app')

@section('title', 'Sửa phòng ban')

@section('content')
    @php
        $backQuery = request()->only(['ten_pb', 'page', 'so_dong']);
        $backUrl = route('backend.phongban.index', $backQuery);
    @endphp

    <main class="container-fluid container-xxl py-4" aria-labelledby="department-edit-title">
        <x-backend.page-header
            title="Sửa phòng ban"
            title-id="department-edit-title"
            icon="bi-building"
            description="Cập nhật tên phòng ban #{{ $department->ma_pb }}."
            :breadcrumbs="[
                ['label' => 'Nhân sự', 'url' => route('backend.tongquan.index')],
                ['label' => 'Phòng ban', 'url' => $backUrl],
                ['label' => 'Chỉnh sửa'],
            ]"
        />

        @if ($errors->has('phong_ban'))
            <div class="alert alert-danger" role="alert">{{ $errors->first('phong_ban') }}</div>
        @endif

        <section class="card shadow-sm" aria-labelledby="department-form-title">
            <div class="card-body">
                @include('backend.phongban.partials.edit-form')
                <div class="mt-3">
                    <a class="btn btn-outline-secondary d-inline-flex align-items-center gap-2" href="{{ $backUrl }}"><i class="bi bi-x-lg" aria-hidden="true"></i>Hủy</a>
                </div>
            </div>
        </section>
    </main>
@endsection

@push('scripts')
    @vite('resources/js/frontend/phongban/phongban.js')
@endpush
