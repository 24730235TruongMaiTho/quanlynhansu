@php
    $state = $state ?? 'empty';
    $colspan = max(1, (int) ($colspan ?? 1));
    $role = $role ?? ($state === 'error' ? 'alert' : 'status');
    $role = in_array($role, ['status', 'alert'], true) ? $role : 'status';
    $message = $message ?? match ($state) {
        'loading' => 'Đang tải dữ liệu…',
        'error' => 'Không thể tải dữ liệu. Vui lòng thử lại.',
        default => 'Chưa có dữ liệu.',
    };
@endphp

<tr class="table-state-row">
    <td colspan="{{ $colspan }}">
        <div class="table-state text-center py-4" role="{{ $role }}" aria-live="polite" data-table-state="{{ $state }}">
            @if ($state === 'loading')
                <span class="spinner-border spinner-border-sm me-2" aria-hidden="true"></span>
            @endif
            <span>{{ $message }}</span>
        </div>
    </td>
</tr>
