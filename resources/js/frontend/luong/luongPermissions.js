const AUTH_ME_API_URL =
    '/api/v1/auth/me';

const PERMISSION_CODES = Object.freeze({
    READ: 'Luong.Read',
    INSERT: 'Luong.Insert',
    UPDATE: 'Luong.Update',
    DELETE: 'Luong.Delete',
});

const COEFFICIENT_PERMISSION_CODES = Object.freeze({
    READ: 'HeSoLuong.Read',
    INSERT: 'HeSoLuong.Insert',
    UPDATE: 'HeSoLuong.Update',
    DELETE: 'HeSoLuong.Delete',
});

const state = {
    initialized: false,
    loadingPromise: null,
    user: null,
    permissions: new Set(),
};

function normalizeAuthData(result) {
    const data = result?.data || {};

    if (
        data.user ||
        Array.isArray(data.permissions)
    ) {
        return {
            user: data.user || null,

            permissions:
                Array.isArray(data.permissions)
                    ? data.permissions
                    : [],
        };
    }

    return {
        user: {
            ma_nv: data.ma_nv ?? null,
            ho_ten: data.ho_ten ?? null,
            email: data.email ?? null,
            ma_vt: data.ma_vt ?? null,
            vai_tro: data.vai_tro ?? null,
        },

        permissions:
            Array.isArray(data.quyen)
                ? data.quyen
                    .map((item) =>
                        item?.ky_hieu_quyen
                    )
                    .filter((item) =>
                        typeof item === 'string'
                    )
                : [],
    };
}

async function loadAuthContext() {
    if (state.initialized) {
        return getContext();
    }

    if (state.loadingPromise) {
        return state.loadingPromise;
    }

    state.loadingPromise = fetch(
        AUTH_ME_API_URL,
        {
            method: 'GET',

            headers: {
                Accept: 'application/json',
                'X-Requested-With':
                    'XMLHttpRequest',
            },

            credentials: 'same-origin',
        }
    )
        .then(async (response) => {
            const contentType =
                response.headers.get(
                    'content-type'
                ) || '';

            if (
                !contentType.includes(
                    'application/json'
                )
            ) {
                const text =
                    await response.text();

                console.error(
                    'Auth API trả HTML/text:',
                    text
                );

                throw new Error(
                    `API xác thực không trả JSON. HTTP ${response.status}`
                );
            }

            const result =
                await response.json();

            if (
                !response.ok ||
                result.success === false
            ) {
                throw new Error(
                    result.message ||
                    `Không thể xác thực người dùng. HTTP ${response.status}`
                );
            }

            const normalized =
                normalizeAuthData(result);

            state.user =
                normalized.user;

            state.permissions =
                new Set(
                    normalized.permissions
                );

            state.initialized =
                true;

            return getContext();
        })
        .finally(() => {
            state.loadingPromise =
                null;
        });

    return state.loadingPromise;
}

function can(permission) {
    return state.permissions.has(
        permission
    );
}

function canAny(...permissions) {
    return permissions.some(
        (permission) =>
            can(permission)
    );
}

function canAll(...permissions) {
    return permissions.every(
        (permission) =>
            can(permission)
    );
}

function getUser() {
    return state.user;
}

function getPermissions() {
    return [
        ...state.permissions,
    ];
}

function getContext() {
    return {
        user:
        state.user,

        permissions:
            getPermissions(),
    };
}

function notifyDenied(
    action = 'thực hiện thao tác này'
) {
    const message =
        `Bạn không có quyền ${action}.`;

    const toast =
        document.querySelector(
            '.salary-toast'
        );

    if (toast) {
        toast.textContent =
            message;

        toast.classList.add(
            'show'
        );

        window.setTimeout(
            () => {
                toast.classList.remove(
                    'show'
                );
            },
            2500
        );

        return;
    }

    window.alert(
        message
    );
}

function guard(
    permission,
    action = 'thực hiện thao tác này'
) {
    if (
        can(permission)
    ) {
        return true;
    }

    notifyDenied(
        action
    );

    return false;
}

function applyPermissionVisibility(
    root = document
) {
    if (
        !root?.querySelectorAll
    ) {
        return;
    }

    root
        .querySelectorAll(
            '[data-salary-permission]'
        )
        .forEach((element) => {
            const required =
                String(
                    element.dataset
                        .salaryPermission ||
                    ''
                )
                    .split(',')
                    .map(
                        (item) =>
                            item.trim()
                    )
                    .filter(Boolean);

            const allowed =
                required.length === 0 ||
                required.some(
                    (permission) =>
                        can(permission)
                );

            element.hidden =
                !allowed;

            element.classList.toggle(
                'd-none',
                !allowed
            );
        });
}

function setPermissionBadge(
    id,
    allowed,
    label
) {
    const element =
        document.getElementById(
            id
        );

    if (!element) {
        return;
    }

    element.className =
        allowed
            ? 'badge rounded-pill text-bg-success'
            : 'badge rounded-pill text-bg-light border text-secondary';

    element.textContent =
        `${allowed ? '✓' : '—'} ${label}`;
}

function updatePermissionSummary() {
    setPermissionBadge(
        'salary-permission-read-badge',
        can(PERMISSION_CODES.READ),
        'Xem'
    );

    setPermissionBadge(
        'salary-permission-insert-badge',
        can(PERMISSION_CODES.INSERT),
        'Thêm'
    );

    setPermissionBadge(
        'salary-permission-update-badge',
        can(PERMISSION_CODES.UPDATE),
        'Sửa'
    );

    setPermissionBadge(
        'salary-permission-delete-badge',
        can(PERMISSION_CODES.DELETE),
        'Xóa'
    );

    const readOnly =
        can(PERMISSION_CODES.READ) &&
        !can(PERMISSION_CODES.INSERT) &&
        !can(PERMISSION_CODES.UPDATE) &&
        !can(PERMISSION_CODES.DELETE);

    const readOnlyBadge =
        document.getElementById(
            'salary-readonly-badge'
        );

    if (readOnlyBadge) {
        readOnlyBadge.hidden =
            !readOnly;
    }

    const noReadNotice =
        document.getElementById(
            'salary-no-read-notice'
        );

    if (noReadNotice) {
        noReadNotice.hidden =
            can(
                PERMISSION_CODES.READ
            );
    }
}

function applyAllPermissionUI(
    root = document
) {
    applyPermissionVisibility(
        root
    );

    updatePermissionSummary();
}

async function initializeSalaryPermissionUI() {
    const loading =
        document.getElementById(
            'salary-auth-loading'
        );

    const denied =
        document.getElementById(
            'salary-access-denied'
        );

    const deniedMessage =
        document.getElementById(
            'salary-access-denied-message'
        );

    const content =
        document.getElementById(
            'salary-content'
        );

    try {
        await loadAuthContext();

        if (loading) {
            loading.hidden =
                true;
        }

        const hasAccess =
            canAny(
                PERMISSION_CODES.READ,
                PERMISSION_CODES.INSERT,
                PERMISSION_CODES.UPDATE,
                PERMISSION_CODES.DELETE,
                COEFFICIENT_PERMISSION_CODES.READ,
                COEFFICIENT_PERMISSION_CODES.INSERT,
                COEFFICIENT_PERMISSION_CODES.UPDATE,
                COEFFICIENT_PERMISSION_CODES.DELETE
            );

        if (!hasAccess) {
            if (denied) {
                denied.hidden =
                    false;
            }

            return false;
        }

        if (content) {
            content.hidden =
                false;
        }

        applyAllPermissionUI();

        return true;
    } catch (error) {
        console.error(
            'Permission initialization failed:',
            error
        );

        if (loading) {
            loading.hidden =
                true;
        }

        if (denied) {
            denied.hidden =
                false;
        }

        if (deniedMessage) {
            deniedMessage.textContent =
                error.message;
        }

        return false;
    }
}

export {
    PERMISSION_CODES,
    COEFFICIENT_PERMISSION_CODES,

    loadAuthContext,
    initializeSalaryPermissionUI,

    can,
    canAny,
    canAll,

    guard,
    notifyDenied,

    getUser,
    getPermissions,
    getContext,

    applyPermissionVisibility,
    applyAllPermissionUI,
    updatePermissionSummary,
};
