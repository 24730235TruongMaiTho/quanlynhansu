@extends('backend.layouts.app')

@section('title', 'Thêm chức vụ')

@section('content')
    <main class="container-fluid container-xxl py-4" aria-labelledby="position-create-title">
        <x-backend.page-header
            title="Thêm chức vụ"
            title-id="position-create-title"
            icon="bi-person-badge"
            description="Nhập tên chức vụ và hệ số phụ cấp theo danh mục dùng chung."
            :breadcrumbs="[
                ['label' => 'Nhân sự', 'url' => route('backend.tongquan.index')],
                ['label' => 'Chức vụ', 'url' => route('backend.chucvu.index')],
                ['label' => 'Thêm mới'],
            ]"
        />

        @if ($errors->has('chuc_vu'))
            <div class="alert alert-danger" role="alert">{{ $errors->first('chuc_vu') }}</div>
        @endif

        <section class="card shadow-sm" aria-labelledby="position-form-title">
            <div class="card-body">
                @include('backend.chucvu.partials.create-form')
            </div>
        </section>
    </main>
@endsection

@push('scripts')
    @vite('resources/js/frontend/chucvu/chucvu.js')
@endpush
