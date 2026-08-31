@extends('backend.layouts.app')

@section('title', 'Sửa chức vụ')

@section('content')
    @php
        $backQuery = request()->only(['ten_cv', 'page', 'so_dong']);
        $backUrl = route('backend.chucvu.index', $backQuery);
    @endphp

    <main class="container-fluid container-xxl py-4" aria-labelledby="position-edit-title">
        <div class="mb-4">
            <div class="small text-secondary mb-1"><a href="{{ $backUrl }}">Chức vụ</a> / Chỉnh sửa</div>
            <h1 class="h3 fw-semibold mb-1" id="position-edit-title">Sửa chức vụ</h1>
            <p class="text-secondary mb-0">Cập nhật chức vụ #{{ $position->ma_cv }}.</p>
        </div>

        @if ($errors->has('chuc_vu'))
            <div class="alert alert-danger" role="alert">{{ $errors->first('chuc_vu') }}</div>
        @endif

        <section class="card shadow-sm" aria-labelledby="position-form-title">
            <div class="card-body">
                @include('backend.chucvu.partials.edit-form')
                <div class="mt-3">
                    <a class="btn btn-outline-secondary" href="{{ $backUrl }}">Hủy</a>
                </div>
            </div>
        </section>
    </main>
@endsection

@push('scripts')
    @vite('resources/js/frontend/chucvu/chucvu.js')
@endpush
