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

            modalMessage:
                document.getElementById(
                    'coefficient-modal-message'
                ),

            listError:
                document.getElementById(
                    'salary-coefficient-list-error'
                ),

            listErrorMessage:
                document.getElementById(
                    'salary-coefficient-list-error-message'
                ),

            listErrorClose:
                document.getElementById(
                    'salary-coefficient-list-error-close'
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

        function getCsrfToken() {
            return document
                .querySelector(
                    'meta[name="csrf-token"]'
                )
                ?.getAttribute(
                    'content'
                ) || null;
        }


        function extractErrorMessage(
            result,
            fallback
        ) {
            const validation =
                result?.errors &&
                typeof result.errors ===
                'object'
                    ? Object.values(
                        result.errors
                    )
                        .flat()
                        .filter(Boolean)
                        .join(' ')
                    : null;

            return (
                validation ||
                result?.message ||
                fallback
            );
        }

        function clearModalError() {
            if (!elements.modalMessage) {
                return;
            }

            elements.modalMessage.textContent =
                '';

            elements.modalMessage.hidden =
                true;
        }

        function showModalError(message) {
            if (!elements.modalMessage) {
                return;
            }

            elements.modalMessage.textContent =
                message;

            elements.modalMessage.hidden =
                false;
        }

        function clearListError() {
            if (elements.listError) {
                elements.listError.hidden =
                    true;
            }

            if (elements.listErrorMessage) {
                elements.listErrorMessage.textContent =
                    '';
            }
        }

        function showListError(message) {
            if (!elements.listError) {
                window.alert(message);
                return;
            }

            if (elements.listErrorMessage) {
                elements.listErrorMessage.textContent =
                    message;
            }

            elements.listError.hidden =
                false;
        }

        function showCoefficientError(
            error,
            {
                modal = false,
            } = {}
        ) {
            const message =
                error instanceof Error
                    ? error.message
                    : String(
                        error ||
                        'Đã xảy ra lỗi khi xử lý hệ số lương.'
                    );

            showListError(
                message
            );

            if (modal) {
                showModalError(
                    message
                );
            }
        }

        async function requestJson(
            url,
            options = {}
        ) {
            const method = String(
                options.method || 'GET'
            ).toUpperCase();

            const csrfToken =
                getCsrfToken();

            const headers = {
                Accept:
                    'application/json',

                'Content-Type':
                    'application/json',

                'X-Requested-With':
                    'XMLHttpRequest',

                ...(options.headers || {}),
            };

            if (
                csrfToken &&
                !['GET', 'HEAD'].includes(
                    method
                )
            ) {
                headers['X-CSRF-TOKEN'] =
                    csrfToken;
            }

            const response =
                await fetch(
                    url,
                    {
                        ...options,
                        method,
                        headers,
                        credentials:
                            'same-origin',
                    }
                );

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
                    'Coefficient API trả về HTML/text:',
                    text
                );

                throw new Error(
                    `API hệ số lương không trả JSON. HTTP ${response.status}`
                );
            }

            const result =
                await response.json();

            if (
                !response.ok ||
                result.success ===
                false
            ) {
                if (response.status === 419) {
                    throw new Error(
                        'CSRF token đã hết hạn. Vui lòng tải lại trang.'
                    );
                }

                if (response.status === 401) {
                    throw new Error(
                        'Phiên đăng nhập đã hết hạn.'
                    );
                }

                if (response.status === 403) {
                    throw new Error(
                        result.message ||
                        'Bạn không có quyền thực hiện thao tác này.'
                    );
                }

                throw new Error(
                    extractErrorMessage(
                        result,
                        `Request thất bại. HTTP ${response.status}`
                    )
                );
            }

            return result;
        }

        function reset() {
            clearModalError();

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

            clearListError();
            reset();

            setMode(
                mode
            );

            modal.showModal();

            try {
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
            } catch (error) {
                console.error(
                    'Load coefficient failed:',
                    error
                );

                showCoefficientError(
                    error,
                    {
                        modal: true,
                    }
                );
            }
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

                clearListError();

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
                    return;
                }

                if (
                    action ===
                    'view'
                ) {
                    await openExisting(
                        coefficientId,
                        'view'
                    );
                    return;
                }

                if (
                    action ===
                    'edit'
                ) {
                    await openExisting(
                        coefficientId,
                        'edit'
                    );
                    return;
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

                    try {
                        await requestJson(
                            `${API}/${encodeURIComponent(
                                coefficientId
                            )}`,
                            {
                                method:
                                    'DELETE',
                            }
                        );

                        clearListError();

                        document.dispatchEvent(
                            new CustomEvent(
                                'salary-coefficient:data-changed'
                            )
                        );
                    } catch (error) {
                        console.error(
                            'Delete coefficient failed:',
                            error
                        );

                        showCoefficientError(
                            error
                        );
                    }
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

                clearModalError();
                clearListError();

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

                const oldLabel =
                    elements.submit.textContent;

                elements.submit.disabled =
                    true;

                elements.submit.textContent =
                    state.mode ===
                    'edit'
                        ? 'Đang cập nhật...'
                        : 'Đang lưu...';

                try {
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

                    clearModalError();
                    clearListError();

                    modal.close();

                    document.dispatchEvent(
                        new CustomEvent(
                            'salary-coefficient:data-changed'
                        )
                    );
                } catch (error) {
                    console.error(
                        'Save coefficient failed:',
                        error
                    );

                    showCoefficientError(
                        error,
                        {
                            modal: true,
                        }
                    );
                } finally {
                    elements.submit.disabled =
                        false;

                    elements.submit.textContent =
                        oldLabel;
                }
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

        elements.listErrorClose
            ?.addEventListener(
                'click',
                clearListError
            );
    }
);
