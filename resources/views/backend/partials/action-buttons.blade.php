@php
    $viewUrl = $viewUrl ?? null;
    $editUrl = $editUrl ?? null;
    $deleteUrl = $deleteUrl ?? null;
    $resetUrl = $resetUrl ?? null;
    $permissionUrl = $permissionUrl ?? null;
    $permission = $permission ?? [];
    $permissionAllowed = static function (string $action) use ($permission): bool {
        if (is_bool($permission)) return $permission;

        $value = is_array($permission) ? ($permission[$action] ?? true) : $permission;
        if (is_bool($value)) return $value;
        if (is_string($value) && $value !== '') {
            return app(\Illuminate\Contracts\Auth\Access\Gate::class)->allows($value);
        }

        return true;
    };
    $canView = $canView ?? $permissionAllowed('view');
    $canEdit = $canEdit ?? $permissionAllowed('edit');
    $canDelete = $canDelete ?? $permissionAllowed('delete');
    $canReset = $canReset ?? $permissionAllowed('reset');
    $canPermission = $canPermission ?? $permissionAllowed('permission');
    $viewLabel = $viewLabel ?? 'Xem';
    $editLabel = $editLabel ?? 'Sửa';
    $deleteLabel = $deleteLabel ?? 'Xóa';
    $resetLabel = $resetLabel ?? 'Đặt lại';
    $permissionLabel = $permissionLabel ?? 'Phân quyền';
    $deleteMethod = strtoupper($deleteMethod ?? 'DELETE');
    $resetMethod = strtoupper($resetMethod ?? 'POST');
    $deleteConfirmMessage = $deleteConfirmMessage ?? 'Bạn có chắc muốn xóa mục này?';
    $resetConfirmMessage = $resetConfirmMessage ?? 'Xác nhận thao tác đặt lại?';
@endphp

<div class="table-actions" data-action-buttons>
    @if ($viewUrl && $canView)
        <a class="btn btn-sm btn-outline-primary" href="{{ $viewUrl }}" aria-label="{{ $viewLabel }}"><i class="bi bi-eye button-icon" aria-hidden="true"></i>{{ $viewLabel }}</a>
    @endif
    @if ($editUrl && $canEdit)
        <a class="btn btn-sm btn-outline-secondary btn-icon-action" href="{{ $editUrl }}" aria-label="{{ $editLabel }}" title="{{ $editLabel }}"><i class="bi bi-pencil-square button-icon" aria-hidden="true"></i></a>
    @endif
    @if ($deleteUrl && $canDelete)
        <form method="POST" action="{{ $deleteUrl }}" class="d-inline" data-confirm-action="delete" data-confirm-message="{{ $deleteConfirmMessage }}">
            @csrf
            @method($deleteMethod)
            <button class="btn btn-sm btn-outline-danger btn-icon-action" type="submit" aria-label="{{ $deleteLabel }}" title="{{ $deleteLabel }}"><i class="bi bi-trash button-icon" aria-hidden="true"></i></button>
        </form>
    @endif
    @if ($resetUrl && $canReset)
        <form method="POST" action="{{ $resetUrl }}" class="d-inline" data-confirm-action="reset" data-confirm-message="{{ $resetConfirmMessage }}">
            @csrf
            @if ($resetMethod !== 'POST')
                @method($resetMethod)
            @endif
            <button class="btn btn-sm btn-outline-secondary" type="submit" aria-label="{{ $resetLabel }}"><i class="bi bi-key button-icon" aria-hidden="true"></i>{{ $resetLabel }}</button>
        </form>
    @endif
    @if ($permissionUrl && $canPermission)
        <a class="btn btn-sm btn-outline-secondary" href="{{ $permissionUrl }}" aria-label="{{ $permissionLabel }}"><i class="bi bi-shield-lock button-icon" aria-hidden="true"></i>{{ $permissionLabel }}</a>
    @endif
</div>
