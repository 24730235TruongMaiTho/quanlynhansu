@props([
    'column',
    'label',
    'sort' => null,
    'direction' => 'desc',
    'href' => '#',
    'numeric' => false,
])

@php
    $active = (string) $sort === (string) $column;
    $activeDirection = strtolower((string) $direction) === 'asc' ? 'asc' : 'desc';
    $nextDirection = $active && $activeDirection === 'asc' ? 'desc' : 'asc';
    $stateLabel = $active
        ? ($activeDirection === 'asc' ? 'đang tăng dần' : 'đang giảm dần')
        : 'chưa sắp xếp';
    $nextLabel = $active && $activeDirection === 'asc' ? 'giảm dần' : 'tăng dần';
    $icon = $active
        ? ($activeDirection === 'asc' ? 'bi-arrow-up-short' : 'bi-arrow-down-short')
        : 'bi-arrow-down-up';
@endphp

<a
    {{ $attributes->class([
        'table-sort-control',
        'table-sort-control--numeric' => $numeric,
        'is-active' => $active,
    ]) }}
    href="{{ $href }}"
    data-sort-column="{{ $column }}"
    data-sort-direction="{{ $nextDirection }}"
    aria-label="Sắp xếp {{ $label }}; {{ $stateLabel }}; nhấn để sắp xếp {{ $nextLabel }}"
>
    <i class="bi {{ $icon }}" aria-hidden="true"></i>
    <span>{{ $label }}</span>
</a>
