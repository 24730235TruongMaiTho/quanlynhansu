@extends('backend.layouts.app')

@section('title', 'Sửa chức vụ')

@section('content')
    @php
        $backQuery = request()->only(['ten_cv', 'page', 'so_dong']);
        $backUrl = route('backend.chucvu.index', $backQuery);
    @endphp

    <main class="container-fluid container-xxl py-4" aria-labelledby="position-edit-title">
        <x-backend.page-header
            title="Sửa chức vụ"
            title-id="position-edit-title"
            icon="bi-person-badge"
            description="Cập nhật chức vụ #{{ $position->ma_cv }}."
            :breadcrumbs="[
                ['label' => 'Nhân sự', 'url' => route('backend.tongquan.index')],
                ['label' => 'Chức vụ', 'url' => $backUrl],
                ['label' => 'Chỉnh sửa'],
            ]"
        />

        @if ($errors->has('chuc_vu'))
            <div class="alert alert-danger" role="alert">{{ $errors->first('chuc_vu') }}</div>
        @endif

        <section class="card shadow-sm" aria-labelledby="position-form-title">
            <div class="card-body">
                @include('backend.chucvu.partials.edit-form')
                <div class="mt-3">
                    <a class="btn btn-outline-secondary d-inline-flex align-items-center gap-2" href="{{ $backUrl }}"><i class="bi bi-x-lg" aria-hidden="true"></i>Hủy</a>
                </div>
            </div>
        </section>
    </main>
@endsection

@push('scripts')
    @vite('resources/js/frontend/chucvu/chucvu.js')
@endpush
