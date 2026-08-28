import {
    COEFFICIENT_PERMISSION_CODES,
    loadAuthContext,
    can,
} from './luongPermissions.js';

document.addEventListener(
    'DOMContentLoaded',
    async () => {
        await loadAuthContext();

        const API =
            '/api/v1/luong/he-so-luong';

        const salaryTbody =
            document.getElementById(
                'salary-tbody'
            );

        const tbody =
            document.getElementById(
                'salary-coefficient-tbody'
            );

        if (
            !can(
                COEFFICIENT_PERMISSION_CODES.READ
            ) ||
            !salaryTbody ||
            !tbody
        ) {
            return;
        }

        const state = {
            employeeCode:
                null,

            employeeName:
                null,

            selectedId:
                null,
        };

        function escapeHtml(value) {
            return String(value ?? '')
                .replaceAll('&', '&amp;')
                .replaceAll('<', '&lt;')
                .replaceAll('>', '&gt;')
                .replaceAll('"', '&quot;')
                .replaceAll("'", '&#039;');
        }

        function iconEye() {
            return `
                <svg viewBox="0 0 16 16" fill="none"
                     stroke="currentColor" stroke-width="1.5">
                    <path d="M1.5 8s2.2-4 6.5-4 6.5 4 6.5 4-2.2 4-6.5 4S1.5 8 1.5 8Z"/>
                    <circle cx="8" cy="8" r="1.8"/>
                </svg>
            `;
        }

        function iconEdit() {
            return `
                <svg viewBox="0 0 16 16" fill="none"
                     stroke="currentColor" stroke-width="1.5">
                    <path d="M10.8 2.2 13.8 5.2"/>
                    <path d="M3 13l1-3.5 7.5-7.5 3 3L7 12.5 3 13Z"/>
                </svg>
            `;
        }

        function iconDelete() {
            return `
                <svg viewBox="0 0 16 16" fill="none"
                     stroke="currentColor" stroke-width="1.5">
                    <path d="M3 4.5h10"/>
                    <path d="M6 2.5h4"/>
                    <path d="M5 4.5l.5 9h5l.5-9"/>
                </svg>
            `;
        }

        function actions(
            item
        ) {
            const result =
                [];

            result.push(`
                <button
                    class="btn btn-outline-secondary coefficient-icon-action"
                    data-coefficient-action="view"
                    data-id="${escapeHtml(item.ma_ls)}"
                    title="Xem"
                >
                    ${iconEye()}
                </button>
            `);

            if (
                can(
                    COEFFICIENT_PERMISSION_CODES.UPDATE
                )
            ) {
                result.push(`
                    <button
                        class="btn btn-outline-primary coefficient-icon-action"
                        data-coefficient-action="edit"
                        data-id="${escapeHtml(item.ma_ls)}"
                        title="Sửa"
                    >
                        ${iconEdit()}
                    </button>
                `);
            }

            if (
                can(
                    COEFFICIENT_PERMISSION_CODES.DELETE
                )
            ) {
                result.push(`
                    <button
                        class="btn btn-outline-danger coefficient-icon-action"
                        data-coefficient-action="delete"
                        data-id="${escapeHtml(item.ma_ls)}"
                        title="Xóa"
                    >
                        ${iconDelete()}
                    </button>
                `);
            }

            return `
                <div class="coefficient-row-actions">
                    ${result.join('')}
                </div>
            `;
        }

        function formatDate(
            value
        ) {
            if (!value) {
                return 'Không thời hạn';
            }

            const match =
                String(value).match(
                    /^(\d{4})-(\d{2})-(\d{2})/
                );

            return match
                ? `${match[3]}/${match[2]}/${match[1]}`
                : value;
        }

        function render(
            rows
        ) {
            if (
                !rows.length
            ) {
                tbody.innerHTML = `
                    <tr>
                        <td colspan="7"
                            class="text-center text-secondary py-5">
                            Chưa có dữ liệu hệ số lương.
                        </td>
                    </tr>
                `;

                return;
            }

            tbody.innerHTML =
                rows.map(
                    (item) => `
                        <tr data-id="${escapeHtml(item.ma_ls)}">
                            <td>
                                <input
                                    class="form-check-input coefficient-radio"
                                    type="radio"
                                    name="coefficient"
                                    value="${escapeHtml(item.ma_ls)}"
                                >
                            </td>

                            <td class="fw-semibold">
                                ${escapeHtml(item.ma_ls)}
                            </td>

                            <td class="text-end">
                                ${Number(item.he_so_luong || 0)
                        .toLocaleString('vi-VN', {
                            minimumFractionDigits: 2,
                            maximumFractionDigits: 2,
                        })}
                            </td>

                            <td>
                                ${formatDate(item.tu_ngay)}
                            </td>

                            <td>
                                ${formatDate(item.den_ngay)}
                            </td>

                            <td>
                                <span class="badge text-bg-light border">
                                    Lịch sử
                                </span>
                            </td>

                            <td class="text-end">
                                ${actions(item)}
                            </td>
                        </tr>
                    `
                ).join('');
        }

        async function load(
            employeeCode,
            employeeName
        ) {
            state.employeeCode =
                employeeCode;

            state.employeeName =
                employeeName;

            const badge =
                document.getElementById(
                    'coefficient-selected-employee'
                );

            if (badge) {
                badge.textContent =
                    `${employeeCode} · ${employeeName || ''}`;
            }

            const response =
                await fetch(
                    `${API}?ma_nv=${encodeURIComponent(employeeCode)}`,
                    {
                        headers: {
                            Accept:
                                'application/json',
                        },

                        credentials:
                            'same-origin',
                    }
                );

            const result =
                await response.json();

            const rows =
                Array.isArray(
                    result.data
                )
                    ? result.data
                    : [];

            render(
                rows
            );

            const addButton =
                document.getElementById(
                    'add-coefficient-btn'
                );

            if (addButton) {
                addButton.disabled =
                    !can(
                        COEFFICIENT_PERMISSION_CODES.INSERT
                    );
            }
        }

        salaryTbody.addEventListener(
            'click',
            (event) => {
                const button =
                    event.target.closest(
                        '[data-salary-action="coefficient"]'
                    );

                if (!button) {
                    return;
                }

                load(
                    button.dataset
                        .employeeCode,

                    button.dataset
                        .employeeName
                );

                document
                    .getElementById(
                        'salary-coefficient-card'
                    )
                    ?.scrollIntoView({
                        behavior:
                            'smooth',

                        block:
                            'start',
                    });
            }
        );

        tbody.addEventListener(
            'click',
            (event) => {
                const button =
                    event.target.closest(
                        '[data-coefficient-action]'
                    );

                if (!button) {
                    return;
                }

                document.dispatchEvent(
                    new CustomEvent(
                        'salary-coefficient:action',
                        {
                            detail: {
                                action:
                                button.dataset
                                    .coefficientAction,

                                coefficientId:
                                button.dataset.id,

                                employeeCode:
                                state.employeeCode,

                                employeeName:
                                state.employeeName,
                            },
                        }
                    )
                );
            }
        );

        document
            .getElementById(
                'add-coefficient-btn'
            )
            ?.addEventListener(
                'click',
                () => {
                    document.dispatchEvent(
                        new CustomEvent(
                            'salary-coefficient:action',
                            {
                                detail: {
                                    action:
                                        'create',

                                    employeeCode:
                                    state.employeeCode,

                                    employeeName:
                                    state.employeeName,
                                },
                            }
                        )
                    );
                }
            );

        document.addEventListener(
            'salary-coefficient:data-changed',
            () => {
                if (
                    state.employeeCode
                ) {
                    load(
                        state.employeeCode,
                        state.employeeName
                    );
                }
            }
        );
    }
);
