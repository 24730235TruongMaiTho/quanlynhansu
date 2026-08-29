@extends('backend.layouts.app')

@section('title', 'Sửa phòng ban')

@section('content')
    @php
        $backQuery = request()->only(['ten_pb', 'page', 'so_dong']);
        $backUrl = route('backend.phongban.index', $backQuery);
    @endphp

    <main class="container-fluid container-xxl py-4" aria-labelledby="department-edit-title">
        <div class="mb-4">
            <div class="small text-secondary mb-1"><a href="{{ $backUrl }}">Phòng ban</a> / Chỉnh sửa</div>
            <h1 class="h3 fw-semibold mb-1" id="department-edit-title">Sửa phòng ban</h1>
            <p class="text-secondary mb-0">Cập nhật tên phòng ban #{{ $department->ma_pb }}.</p>
        </div>

        @if ($errors->has('phong_ban'))
            <div class="alert alert-danger" role="alert">{{ $errors->first('phong_ban') }}</div>
        @endif

        <section class="card shadow-sm" aria-labelledby="department-form-title">
            <div class="card-body">
                @include('backend.phongban.partials.edit-form')
                <div class="mt-3">
                    <a class="btn btn-outline-secondary" href="{{ $backUrl }}">Hủy</a>
                </div>
            </div>
        </section>
    </main>
@endsection

@push('scripts')
    @vite('resources/js/frontend/phongban/phongban.js')
@endpush
