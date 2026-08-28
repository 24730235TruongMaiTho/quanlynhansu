import {
    COEFFICIENT_PERMISSION_CODES,
    loadAuthContext,
    guard,
} from './luongPermissions.js';

document.addEventListener(
    'DOMContentLoaded',
    async () => {
        await loadAuthContext();

        const API =
            '/api/v1/luong/he-so-luong';

        const modal =
            document.getElementById(
                'coefficient-modal'
            );

        const form =
            document.getElementById(
                'coefficient-form'
            );

        if (
            !modal ||
            !form
        ) {
            return;
        }

        const elements = {
            title:
                document.getElementById(
                    'coefficient-modal-title'
                ),

            submit:
                document.getElementById(
                    'coefficient-modal-submit'
                ),

            close:
                document.getElementById(
                    'coefficient-modal-close'
                ),

            cancel:
                document.getElementById(
                    'coefficient-modal-cancel'
                ),

            id:
                document.getElementById(
                    'coefficient-id'
                ),

            employeeCode:
                document.getElementById(
                    'coefficient-employee-code'
                ),

            employeeName:
                document.getElementById(
                    'coefficient-employee-name'
                ),

            value:
                document.getElementById(
                    'coefficient-value'
                ),

            from:
                document.getElementById(
                    'coefficient-from-date'
                ),

            to:
                document.getElementById(
                    'coefficient-to-date'
                ),
        };

        const state = {
            mode:
                'view',

            id:
                null,

            employeeCode:
                null,

            employeeName:
                null,
        };

        async function requestJson(
            url,
            options = {}
        ) {
            const response =
                await fetch(
                    url,
                    {
                        headers: {
                            Accept:
                                'application/json',

                            'Content-Type':
                                'application/json',
                        },

                        credentials:
                            'same-origin',

                        ...options,
                    }
                );

            const result =
                await response.json();

            if (
                !response.ok ||
                result.success ===
                false
            ) {
                throw new Error(
                    result.message ||
                    'Request thất bại.'
                );
            }

            return result;
        }

        function reset() {
            form.reset();

            elements.id.value =
                '';

            elements.employeeCode.value =
                state.employeeCode ||
                '';

            elements.employeeName.value =
                state.employeeName ||
                '';
        }

        function setMode(
            mode
        ) {
            state.mode =
                mode;

            const view =
                mode ===
                'view';

            elements.value.disabled =
                view;

            elements.from.disabled =
                view;

            elements.to.disabled =
                view;

            elements.submit.hidden =
                view;

            elements.title.textContent =
                mode ===
                'create'
                    ? 'Thêm hệ số lương'
                    : mode ===
                    'edit'
                        ? 'Cập nhật hệ số lương'
                        : 'Chi tiết hệ số lương';
        }

        async function openExisting(
            id,
            mode
        ) {
            const permission =
                mode ===
                'edit'
                    ? COEFFICIENT_PERMISSION_CODES.UPDATE
                    : COEFFICIENT_PERMISSION_CODES.READ;

            if (
                !guard(
                    permission,
                    mode ===
                    'edit'
                        ? 'sửa hệ số lương'
                        : 'xem hệ số lương'
                )
            ) {
                return;
            }

            reset();
            setMode(
                mode
            );

            modal.showModal();

            const result =
                await requestJson(
                    `${API}/${encodeURIComponent(
                        id
                    )}`
                );

            const item =
                result.data ??
                result;

            state.id =
                item.ma_ls;

            elements.id.value =
                item.ma_ls ??
                '';

            elements.employeeCode.value =
                item.ma_nv ??
                '';

            elements.value.value =
                item.he_so_luong ??
                '';

            elements.from.value =
                String(
                    item.tu_ngay ||
                    ''
                ).substring(
                    0,
                    10
                );

            elements.to.value =
                String(
                    item.den_ngay ||
                    ''
                ).substring(
                    0,
                    10
                );
        }

        document.addEventListener(
            'salary-coefficient:action',
            async (event) => {
                const {
                    action,
                    coefficientId,
                    employeeCode,
                    employeeName,
                } =
                event.detail ||
                {};

                state.employeeCode =
                    employeeCode;

                state.employeeName =
                    employeeName;

                if (
                    action ===
                    'create'
                ) {
                    if (
                        !guard(
                            COEFFICIENT_PERMISSION_CODES.INSERT,
                            'thêm hệ số lương'
                        )
                    ) {
                        return;
                    }

                    reset();
                    setMode(
                        'create'
                    );

                    modal.showModal();
                }

                if (
                    action ===
                    'view'
                ) {
                    await openExisting(
                        coefficientId,
                        'view'
                    );
                }

                if (
                    action ===
                    'edit'
                ) {
                    await openExisting(
                        coefficientId,
                        'edit'
                    );
                }

                if (
                    action ===
                    'delete'
                ) {
                    if (
                        !guard(
                            COEFFICIENT_PERMISSION_CODES.DELETE,
                            'xóa hệ số lương'
                        )
                    ) {
                        return;
                    }

                    if (
                        !window.confirm(
                            'Bạn có chắc muốn xóa hệ số này?'
                        )
                    ) {
                        return;
                    }

                    await requestJson(
                        `${API}/${encodeURIComponent(
                            coefficientId
                        )}`,
                        {
                            method:
                                'DELETE',
                        }
                    );

                    document.dispatchEvent(
                        new CustomEvent(
                            'salary-coefficient:data-changed'
                        )
                    );
                }
            }
        );

        form.addEventListener(
            'submit',
            async (event) => {
                event.preventDefault();

                if (
                    state.mode ===
                    'view'
                ) {
                    return;
                }

                const permission =
                    state.mode ===
                    'edit'
                        ? COEFFICIENT_PERMISSION_CODES.UPDATE
                        : COEFFICIENT_PERMISSION_CODES.INSERT;

                if (
                    !guard(
                        permission,
                        state.mode ===
                        'edit'
                            ? 'sửa hệ số lương'
                            : 'thêm hệ số lương'
                    )
                ) {
                    return;
                }

                const payload = {
                    ma_nv:
                    elements.employeeCode
                        .value,

                    he_so_luong:
                        Number(
                            elements.value
                                .value
                        ),

                    tu_ngay:
                    elements.from
                        .value,

                    den_ngay:
                        elements.to
                            .value ||
                        null,
                };

                await requestJson(
                    state.mode ===
                    'edit'
                        ? `${API}/${encodeURIComponent(
                            state.id
                        )}`
                        : API,
                    {
                        method:
                            state.mode ===
                            'edit'
                                ? 'PUT'
                                : 'POST',

                        body:
                            JSON.stringify(
                                payload
                            ),
                    }
                );

                modal.close();

                document.dispatchEvent(
                    new CustomEvent(
                        'salary-coefficient:data-changed'
                    )
                );
            }
        );

        elements.close
            ?.addEventListener(
                'click',
                () =>
                    modal.close()
            );

        elements.cancel
            ?.addEventListener(
                'click',
                () =>
                    modal.close()
            );
    }
);
