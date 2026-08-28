@php
    $summaryLabel = $summaryLabel ?? 'bản ghi';
    $summaryTotal = (int) $paginator->total();
    $summaryFrom = $summaryTotal === 0 ? 0 : (int) ($paginator->firstItem() ?? 0);
    $summaryTo = $summaryTotal === 0 ? 0 : (int) ($paginator->lastItem() ?? 0);
@endphp

<p class="small text-secondary mb-0" data-pagination-summary>
    Hiển thị {{ number_format($summaryFrom, 0, ',', '.') }}-{{ number_format($summaryTo, 0, ',', '.') }} / {{ number_format($summaryTotal, 0, ',', '.') }} {{ $summaryLabel }}
</p>
