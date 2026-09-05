@php
    $filterAction = $action ?? request()->url();
    $filterMethod = strtoupper($method ?? 'GET');
    $filters = $filters ?? [];
    $resetUrl = $resetUrl ?? null;
    $applyLabel = $applyLabel ?? 'Áp dụng bộ lọc';
    $resetLabel = $resetLabel ?? 'Đặt lại';
@endphp

<form class="filter-bar" method="{{ $filterMethod }}" action="{{ $filterAction }}" data-filter-bar>
    <div class="filter-bar__fields">
        @foreach ($filters as $filter)
            @php
                $name = (string) ($filter['name'] ?? 'filter');
                $id = (string) ($filter['id'] ?? str_replace(['[', ']'], '', $name));
                $type = (string) ($filter['type'] ?? 'text');
                $label = (string) ($filter['label'] ?? $name);
                $value = array_key_exists('value', $filter)
                    ? $filter['value']
                    : request()->query($name, '');
                $fieldModifier = $filter['class'] ?? (in_array($type, ['select', 'date', 'number'], true)
                    ? 'filter-bar__field--compact'
                    : 'filter-bar__field--wide');
            @endphp
            <div class="filter-bar__field {{ $fieldModifier }}">
                <label class="form-label" for="{{ $id }}">{{ $label }}</label>
                @if ($type === 'select')
                    <select class="form-select" id="{{ $id }}" name="{{ $name }}">
                        @foreach (($filter['options'] ?? []) as $optionValue => $optionLabel)
                            <option value="{{ $optionValue }}" @selected((string) $value === (string) $optionValue)>{{ $optionLabel }}</option>
                        @endforeach
                    </select>
                @else
                    <input class="form-control" id="{{ $id }}" name="{{ $name }}" type="{{ in_array($type, ['date', 'number', 'search', 'text'], true) ? $type : 'text' }}" value="{{ $value }}">
                @endif
            </div>
        @endforeach
    </div>
    <div class="filter-bar__actions">
        <button class="btn btn-primary" type="submit"><i class="bi bi-funnel button-icon" aria-hidden="true"></i>{{ $applyLabel }}</button>
        @if ($resetUrl)
            <a class="btn btn-outline-secondary" href="{{ $resetUrl }}"><i class="bi bi-arrow-counterclockwise button-icon" aria-hidden="true"></i>{{ $resetLabel }}</a>
        @else
            <button class="btn btn-outline-secondary" type="reset"><i class="bi bi-arrow-counterclockwise button-icon" aria-hidden="true"></i>{{ $resetLabel }}</button>
        @endif
    </div>
</form>
