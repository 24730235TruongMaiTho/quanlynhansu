@php
    $label = $label ?? 'bản ghi';
    $lastPage = max(1, (int) $paginator->lastPage());
    $currentPage = min($lastPage, max(1, (int) $paginator->currentPage()));
@endphp

@if ($paginator->hasPages())
    @php
        $windowStart = max(1, $currentPage - 2);
        $windowEnd = min($lastPage, $currentPage + 2);
    @endphp
    <nav class="backend-pagination" aria-label="Phân trang {{ $label }}">
        <ul class="pagination pagination-sm mb-0 flex-wrap justify-content-center">
            <li class="page-item {{ $paginator->onFirstPage() ? 'disabled' : '' }}">
                @if ($paginator->onFirstPage())
                    <span class="page-link" aria-disabled="true" aria-label="Trang đầu"><i class="bi bi-chevron-double-left" aria-hidden="true"></i><span class="visually-hidden">«</span></span>
                @else
                    <a class="page-link" href="{{ $paginator->url(1) }}" aria-label="Trang đầu"><i class="bi bi-chevron-double-left" aria-hidden="true"></i><span class="visually-hidden">«</span></a>
                @endif
            </li>
            <li class="page-item {{ $paginator->onFirstPage() ? 'disabled' : '' }}">
                @if ($paginator->onFirstPage())
                    <span class="page-link" aria-disabled="true" aria-label="Trang trước"><i class="bi bi-chevron-left" aria-hidden="true"></i></span>
                @else
                    <a class="page-link" href="{{ $paginator->previousPageUrl() }}" aria-label="Trang trước"><i class="bi bi-chevron-left" aria-hidden="true"></i></a>
                @endif
            </li>

            @if ($windowStart > 1)
                <li class="page-item"><a class="page-link" href="{{ $paginator->url(1) }}">1</a></li>
                @if ($windowStart > 2)
                    <li class="page-item disabled"><span class="page-link" aria-hidden="true">…</span></li>
                @endif
            @endif

            @for ($page = $windowStart; $page <= $windowEnd; $page++)
                <li class="page-item {{ $page === (int) $paginator->currentPage() ? 'active' : '' }}">
                    @if ($page === (int) $paginator->currentPage())
                        <span class="page-link" aria-current="page">{{ $page }}</span>
                    @else
                        <a class="page-link" href="{{ $paginator->url($page) }}">{{ $page }}</a>
                    @endif
                </li>
            @endfor

            @if ($windowEnd < $lastPage)
                @if ($windowEnd < $lastPage - 1)
                    <li class="page-item disabled"><span class="page-link" aria-hidden="true">…</span></li>
                @endif
                <li class="page-item"><a class="page-link" href="{{ $paginator->url($lastPage) }}">{{ $lastPage }}</a></li>
            @endif

            <li class="page-item {{ $paginator->hasMorePages() ? '' : 'disabled' }}">
                @if ($paginator->hasMorePages())
                    <a class="page-link" href="{{ $paginator->nextPageUrl() }}" aria-label="Trang sau"><i class="bi bi-chevron-right" aria-hidden="true"></i></a>
                @else
                    <span class="page-link" aria-disabled="true" aria-label="Trang sau"><i class="bi bi-chevron-right" aria-hidden="true"></i></span>
                @endif
            </li>
            <li class="page-item {{ $paginator->hasMorePages() ? '' : 'disabled' }}">
                @if ($paginator->hasMorePages())
                    <a class="page-link" href="{{ $paginator->url($lastPage) }}" aria-label="Trang cuối"><i class="bi bi-chevron-double-right" aria-hidden="true"></i><span class="visually-hidden">»</span></a>
                @else
                    <span class="page-link" aria-disabled="true" aria-label="Trang cuối"><i class="bi bi-chevron-double-right" aria-hidden="true"></i><span class="visually-hidden">»</span></span>
                @endif
            </li>
        </ul>
    </nav>
@endif
