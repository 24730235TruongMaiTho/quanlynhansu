@extends('backend.layouts.app')

@section('title', 'Thêm phòng ban')

@section('content')
    <main class="container-fluid container-xxl py-4" aria-labelledby="department-create-title">
        <x-backend.page-header
            title="Thêm phòng ban"
            title-id="department-create-title"
            icon="bi-building-add"
            description="Nhập tên phòng ban theo danh mục dùng chung của hệ thống."
            :breadcrumbs="[
                ['label' => 'Nhân sự', 'url' => route('backend.tongquan.index')],
                ['label' => 'Phòng ban', 'url' => route('backend.phongban.index')],
                ['label' => 'Thêm mới'],
            ]"
        />

        @if ($errors->has('phong_ban'))
            <div class="alert alert-danger" role="alert">{{ $errors->first('phong_ban') }}</div>
        @endif

        <section class="card shadow-sm" aria-labelledby="department-form-title">
            <div class="card-body">
                @include('backend.phongban.partials.create-form')
            </div>
        </section>
    </main>
@endsection

@push('scripts')
    @vite('resources/js/frontend/phongban/phongban.js')
@endpush
