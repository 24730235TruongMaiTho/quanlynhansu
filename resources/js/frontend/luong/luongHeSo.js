import {
    COEFFICIENT_PERMISSION_CODES,
    loadAuthContext,
    can,
} from './luongPermissions.js';
import { formatDisplayDate } from '../shared/date-field.js';
import { renderSharedPagination } from '../shared/pagination.js';
import { normalizePaginator } from '../shared/json-paginator.js';

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

        const pagination =
            document.getElementById(
                'coefficient-pagination'
            );

        const pageInfo =
            document.getElementById(
                'coefficient-info'
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

            coefficientPage:
                1,

            coefficientPerPage:
                10,

            coefficientSort:
                'ma_ls',

            coefficientDirection:
                'desc',
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
            return '<i class="bi bi-eye" aria-hidden="true"></i>';
        }

        function iconEdit() {
            return '<i class="bi bi-pencil-square" aria-hidden="true"></i>';
        }

        function iconDelete() {
            return '<i class="bi bi-trash" aria-hidden="true"></i>';
        }

        function actions(
            item
        ) {
            const result =
                [];

            result.push(`
                <button
                    class="btn btn-outline-secondary coefficient-icon-action btn-icon-action"
                    data-coefficient-action="view"
                    type="button"
                    data-id="${escapeHtml(item.ma_ls)}"
                    aria-label="Xem hệ số lương ${escapeHtml(item.ma_ls)}"
                    title="Xem hệ số lương ${escapeHtml(item.ma_ls)}"
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
                        class="btn btn-outline-primary coefficient-icon-action btn-icon-action"
                        data-coefficient-action="edit"
                        type="button"
                        data-id="${escapeHtml(item.ma_ls)}"
                        aria-label="Sửa hệ số lương ${escapeHtml(item.ma_ls)}"
                        title="Sửa hệ số lương ${escapeHtml(item.ma_ls)}"
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
                        class="btn btn-outline-danger coefficient-icon-action btn-icon-action"
                        data-coefficient-action="delete"
                        type="button"
                        data-id="${escapeHtml(item.ma_ls)}"
                        aria-label="Xóa hệ số lương ${escapeHtml(item.ma_ls)}"
                        title="Xóa hệ số lương ${escapeHtml(item.ma_ls)}"
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

            return formatDisplayDate(String(value).substring(0, 10)) || value;
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
            employeeName,
            page = 1
        ) {
            const normalizedEmployeeCode =
                String(employeeCode ?? '').trim();

            if (!normalizedEmployeeCode) {
                return;
            }

            state.employeeCode =
                normalizedEmployeeCode;

            state.employeeName =
                String(employeeName ?? '');

            state.coefficientPage =
                Math.max(Number(page) || 1, 1);

            const badge =
                document.getElementById(
                    'coefficient-selected-employee'
                );

            if (badge) {
                badge.textContent =
                    `${state.employeeCode} · ${state.employeeName}`;
            }

            const response =
                await fetch(
                    `${API}?ma_nv=${encodeURIComponent(state.employeeCode)}&page=${state.coefficientPage}&per_page=${state.coefficientPerPage}&sort=${encodeURIComponent(state.coefficientSort)}&direction=${state.coefficientDirection}`,
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

            const paginator = normalizePaginator(result.data);

            const rows = Array.isArray(paginator.data)
                ? paginator.data
                : [];

            render(
                rows
            );

            if (pageInfo) {
                const total = Number(paginator.total || 0);
                pageInfo.textContent = total > 0
                    ? `Hiển thị ${paginator.from ?? 0}–${paginator.to ?? 0} trên ${total} bản ghi`
                    : 'Hiển thị 0 bản ghi';
            }

            renderSharedPagination(pagination, paginator, {
                pageAttribute: 'coefficientPage',
            });

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

        function updateSortControls() {
            document.querySelectorAll('[data-coefficient-sort]').forEach((button) => {
                const active = button.dataset.coefficientSort === state.coefficientSort;
                const nextDirection = active && state.coefficientDirection === 'asc' ? 'desc' : 'asc';
                const label = button.dataset.sortLabel || button.textContent.trim();
                const icon = button.querySelector('i');
                const header = button.closest('th');

                button.classList.toggle('is-active', active);
                button.dataset.sortDirection = active ? state.coefficientDirection : 'asc';
                button.setAttribute('aria-label', active
                    ? `Sắp xếp ${label}; đang ${state.coefficientDirection === 'asc' ? 'tăng dần' : 'giảm dần'}; nhấn để sắp xếp ${nextDirection === 'asc' ? 'tăng dần' : 'giảm dần'}`
                    : `Sắp xếp ${label} tăng dần`);
                header?.setAttribute(
                    'aria-sort',
                    active
                        ? (state.coefficientDirection === 'asc' ? 'ascending' : 'descending')
                        : 'none',
                );
                icon?.classList.remove('bi-arrow-down-up', 'bi-arrow-up-short', 'bi-arrow-down-short');
                icon?.classList.add(active
                    ? (state.coefficientDirection === 'asc' ? 'bi-arrow-up-short' : 'bi-arrow-down-short')
                    : 'bi-arrow-down-up');
            });
        }

        updateSortControls();
        document.querySelectorAll('[data-coefficient-sort]').forEach((button) => {
            button.addEventListener('click', () => {
                const column = button.dataset.coefficientSort;
                state.coefficientDirection = state.coefficientSort === column && state.coefficientDirection === 'asc'
                    ? 'desc'
                    : 'asc';
                state.coefficientSort = column;
                state.coefficientPage = 1;
                updateSortControls();
                if (state.employeeCode) {
                    load(state.employeeCode, state.employeeName, 1);
                }
            });
        });

        function isNonRowInteractiveTarget(target) {
            return target instanceof Element && target.closest(
                'button, a, input, select, textarea, label, [role="button"]'
            );
        }

        function setSelectedEmployeeRow(row) {
            salaryTbody.querySelectorAll('[data-salary-row]').forEach((candidate) => {
                const selected = candidate === row;
                candidate.classList.toggle('salary-row-selected', selected);
                candidate.setAttribute('aria-selected', selected ? 'true' : 'false');
            });
        }

        function selectEmployeeRow(row) {
            const employeeCode = String(row.dataset.employeeCode ?? '').trim();
            if (!employeeCode) {
                return;
            }

            const employeeName = row.dataset.employeeName ?? '';
            setSelectedEmployeeRow(row);

            document.dispatchEvent(
                new CustomEvent('salary:employee-selected', {
                    detail: {
                        employeeCode,
                        employeeName,
                    },
                })
            );

            void load(employeeCode, employeeName);

            document
                .getElementById('salary-coefficient-card')
                ?.scrollIntoView({ behavior: 'smooth', block: 'start' });
        }

        function rowFromEvent(event) {
            const target = event.target instanceof Element
                ? event.target
                : null;

            if (!target || isNonRowInteractiveTarget(target)) {
                return null;
            }

            const row = target.closest('[data-salary-row]');
            return row && salaryTbody.contains(row) ? row : null;
        }

        salaryTbody.addEventListener('click', (event) => {
            const row = rowFromEvent(event);
            if (row) selectEmployeeRow(row);
        });

        salaryTbody.addEventListener('keydown', (event) => {
            if (event.key !== 'Enter' && event.key !== ' ') {
                return;
            }

            const row = rowFromEvent(event);
            if (!row) {
                return;
            }

            event.preventDefault();
            selectEmployeeRow(row);
        });

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

        pagination?.addEventListener(
            'click',
            (event) => {
                const button = event.target.closest(
                    'button[data-coefficient-page]'
                );

                if (
                    !button ||
                    button.disabled ||
                    !state.employeeCode
                ) {
                    return;
                }

                load(
                    state.employeeCode,
                    state.employeeName,
                    button.dataset.coefficientPage
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
