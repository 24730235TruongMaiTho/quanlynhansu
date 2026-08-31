import {
    PERMISSION_CODES,
    loadAuthContext,
    guard,
} from './luongPermissions.js';

document.addEventListener(
    'DOMContentLoaded',
    async () => {
        await loadAuthContext();

        const LUONG_API_URL =
            '/api/v1/luong';

        const elements = {
            modal:
                document.getElementById(
                    'salary-modal'
                ),

            form:
                document.getElementById(
                    'salary-form'
                ),

            modalTitle:
                document.getElementById(
                    'salary-modal-title'
                ),

            modalDescription:
                document.getElementById(
                    'salary-modal-description'
                ),

            modalMessage:
                document.getElementById(
                    'salary-modal-message'
                ),

            closeButton:
                document.getElementById(
                    'salary-modal-close'
                ),

            cancelButton:
                document.getElementById(
                    'salary-modal-cancel'
                ),

            submitButton:
                document.getElementById(
                    'salary-modal-submit'
                ),

            createButton:
                document.getElementById(
                    'create-salary-btn'
                ),

            tbody:
                document.getElementById(
                    'salary-tbody'
                ),

            salaryId:
                document.getElementById(
                    'salary-id'
                ),

            employeeCode:
                document.getElementById(
                    'salary-employee-code'
                ),

            salaryPeriod:
                document.getElementById(
                    'salary-period'
                ),

            bonus:
                document.getElementById(
                    'salary-bonus'
                ),

            penalty:
                document.getElementById(
                    'salary-penalty'
                ),

            insurance:
                document.getElementById(
                    'salary-insurance'
                ),

            tax:
                document.getElementById(
                    'salary-tax'
                ),

            month:
                document.getElementById(
                    'salary-month-select'
                ),

            year:
                document.getElementById(
                    'salary-year-input'
                ),
        };

        if (
            !elements.modal ||
            !elements.form
        ) {
            return;
        }

        const state = {
            mode:
                'create',

            salaryId:
                null,
        };

        function number(
            value
        ) {
            const result =
                Number(value);

            return Number.isFinite(
                result
            )
                ? result
                : 0;
        }

        function getPeriod() {
            const month =
                Number(
                    elements.month
                        ?.value
                );

            const year =
                Number(
                    elements.year
                        ?.value
                );

            if (
                !month ||
                !year
            ) {
                return null;
            }

            return `${year}-${String(
                month
            ).padStart(
                2,
                '0'
            )}-01`;
        }

        function periodLabel() {
            const period =
                getPeriod();

            if (!period) {
                return '—';
            }

            const [
                year,
                month,
            ] =
                period.split(
                    '-'
                );

            return `${month}/${year}`;
        }

        function closeModal() {
            if (
                elements.modal.open
            ) {
                elements.modal.close();
            }
        }

        function resetForm() {
            elements.form.reset();

            state.salaryId =
                null;

            elements.salaryId.value =
                '';

            elements.employeeCode.value =
                '';

            elements.salaryPeriod.value =
                periodLabel();

            elements.bonus.value =
                '0';

            elements.penalty.value =
                '0';

            elements.insurance.value =
                '0';

            elements.tax.value =
                '0';
        }

        function setMode(
            mode
        ) {
            state.mode =
                mode;

            const view =
                mode ===
                'view';

            elements.modalTitle.textContent =
                mode ===
                'create'
                    ? 'Thêm thông tin lương'
                    : mode ===
                    'edit'
                        ? 'Cập nhật thông tin lương'
                        : 'Chi tiết thông tin lương';

            [
                elements.bonus,
                elements.penalty,
                elements.insurance,
                elements.tax,
            ].forEach(
                (field) => {
                    field.disabled =
                        view;
                }
            );

            elements.employeeCode.readOnly =
                mode !==
                'create';

            elements.submitButton.hidden =
                view;
        }

        function csrfToken() {
            return document
                .querySelector(
                    'meta[name="csrf-token"]'
                )
                ?.getAttribute('content') || null;
        }

        async function requestJson(
            url,
            options = {}
        ) {
            const method = String(
                options.method || 'GET'
            ).toUpperCase();

            const headers = {
                Accept: 'application/json',
                'Content-Type': 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
                ...(options.headers || {}),
            };

            const token = csrfToken();

            if (
                token &&
                !['GET', 'HEAD'].includes(method)
            ) {
                headers['X-CSRF-TOKEN'] =
                    token;
            }

            const response = await fetch(
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
                    'API trả về HTML/text:',
                    text
                );

                throw new Error(
                    `API không trả JSON. HTTP ${response.status}`
                );
            }

            const result =
                await response.json();

            if (
                !response.ok ||
                result.success === false
            ) {
                const validation =
                    result.errors
                        ? Object.values(
                            result.errors
                        )
                            .flat()
                            .join(' ')
                        : null;

                if (response.status === 419) {
                    throw new Error(
                        'CSRF token đã hết hạn. Vui lòng tải lại trang.'
                    );
                }

                throw new Error(
                    validation ||
                    result.message ||
                    `Request thất bại. HTTP ${response.status}`
                );
            }

            return result;
        }

        function fill(
            salary
        ) {
            state.salaryId =
                salary.ma_luong;

            elements.salaryId.value =
                salary.ma_luong ??
                '';

            elements.employeeCode.value =
                salary.ma_nv ??
                '';

            elements.salaryPeriod.value =
                salary.ky_luong
                    ? String(
                        salary.ky_luong
                    ).replace(
                        /^(\d{4})-(\d{2}).*$/,
                        '$2/$1'
                    )
                    : periodLabel();

            elements.bonus.value =
                number(
                    salary.thuong
                );

            elements.penalty.value =
                number(
                    salary.phat
                );

            elements.insurance.value =
                number(
                    salary.bao_hiem
                );

            elements.tax.value =
                number(
                    salary.thue
                );
        }

        function openCreate(
            employeeCode =
            ''
        ) {
            if (
                !guard(
                    PERMISSION_CODES.INSERT,
                    'thêm thông tin lương'
                )
            ) {
                return;
            }

            resetForm();
            setMode(
                'create'
            );

            if (
                employeeCode
            ) {
                elements.employeeCode.value =
                    employeeCode;
            }

            elements.modal.showModal();
        }

        async function openExisting(
            id,
            mode
        ) {
            const permission =
                mode ===
                'edit'
                    ? PERMISSION_CODES.UPDATE
                    : PERMISSION_CODES.READ;

            if (
                !guard(
                    permission,
                    mode ===
                    'edit'
                        ? 'sửa thông tin lương'
                        : 'xem thông tin lương'
                )
            ) {
                return;
            }

            resetForm();
            setMode(
                mode
            );

            elements.modal.showModal();

            try {
                const result =
                    await requestJson(
                        `${LUONG_API_URL}/${encodeURIComponent(
                            id
                        )}`
                    );

                fill(
                    result.data ??
                    result
                );

                setMode(
                    mode
                );
            } catch (
                error
                ) {
                window.alert(
                    error.message
                );
            }
        }

        async function remove(
            id,
            employeeName
        ) {
            if (
                !guard(
                    PERMISSION_CODES.DELETE,
                    'xóa thông tin lương'
                )
            ) {
                return;
            }

            if (
                !window.confirm(
                    `Xóa thông tin lương của ${employeeName || 'nhân viên này'}?`
                )
            ) {
                return;
            }

            try {
                await requestJson(
                    `${LUONG_API_URL}/${encodeURIComponent(
                        id
                    )}`,
                    {
                        method:
                            'DELETE',
                    }
                );

                document.dispatchEvent(
                    new CustomEvent(
                        'salary:data-changed'
                    )
                );
            } catch (
                error
                ) {
                window.alert(
                    error.message
                );
            }
        }

        elements.createButton
            ?.addEventListener(
                'click',
                () =>
                    openCreate()
            );

        elements.tbody
            ?.addEventListener(
                'click',
                (event) => {
                    const button =
                        event.target.closest(
                            '[data-salary-action]'
                        );

                    if (!button) {
                        return;
                    }

                    const action =
                        button.dataset
                            .salaryAction;

                    if (
                        action ===
                        'view'
                    ) {
                        openExisting(
                            button.dataset.id,
                            'view'
                        );
                    }

                    if (
                        action ===
                        'edit'
                    ) {
                        openExisting(
                            button.dataset.id,
                            'edit'
                        );
                    }

                    if (
                        action ===
                        'delete'
                    ) {
                        remove(
                            button.dataset.id,
                            button.dataset
                                .employeeName
                        );
                    }

                    if (
                        action ===
                        'create-for-employee'
                    ) {
                        openCreate(
                            button.dataset
                                .employeeCode
                        );
                    }
                }
            );

        elements.form
            .addEventListener(
                'submit',
                async (
                    event
                ) => {
                    event.preventDefault();

                    if (
                        state.mode ===
                        'view'
                    ) {
                        return;
                    }

                    const isEdit =
                        state.mode ===
                        'edit';

                    const permission =
                        isEdit
                            ? PERMISSION_CODES.UPDATE
                            : PERMISSION_CODES.INSERT;

                    if (
                        !guard(
                            permission,
                            isEdit
                                ? 'sửa thông tin lương'
                                : 'thêm thông tin lương'
                        )
                    ) {
                        return;
                    }

                    const payload = {
                        ma_nv:
                            elements.employeeCode
                                .value
                                .trim()
                                .toUpperCase(),

                        ky_luong:
                            getPeriod(),

                        thuong:
                            number(
                                elements.bonus
                                    .value
                            ),

                        phat:
                            number(
                                elements.penalty
                                    .value
                            ),

                        bao_hiem:
                            number(
                                elements.insurance
                                    .value
                            ),

                        thue:
                            number(
                                elements.tax
                                    .value
                            ),
                    };

                    try {
                        await requestJson(
                            isEdit
                                ? `${LUONG_API_URL}/${encodeURIComponent(
                                    state.salaryId
                                )}`
                                : LUONG_API_URL,
                            {
                                method:
                                    isEdit
                                        ? 'PUT'
                                        : 'POST',

                                body:
                                    JSON.stringify(
                                        payload
                                    ),
                            }
                        );

                        closeModal();

                        document.dispatchEvent(
                            new CustomEvent(
                                'salary:data-changed'
                            )
                        );
                    } catch (
                        error
                        ) {
                        window.alert(
                            error.message
                        );
                    }
                }
            );

        elements.closeButton
            ?.addEventListener(
                'click',
                closeModal
            );

        elements.cancelButton
            ?.addEventListener(
                'click',
                closeModal
            );
    }
);
