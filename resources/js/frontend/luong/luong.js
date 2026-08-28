import '../../../css/luong/salary-bootstrap.css';

import {
    PERMISSION_CODES,
    COEFFICIENT_PERMISSION_CODES,
    initializeSalaryPermissionUI,
    can,
    guard,
} from './luongPermissions.js';

document.addEventListener(
    'DOMContentLoaded',
    async () => {
        const LUONG_API_URL =
            '/api/v1/luong';

        const PHONG_BAN_API_URL =
            '/api/v1/luong/phong-ban';

        const CHUC_VU_API_URL =
            '/api/v1/luong/chuc-vu';

        const LUONG_EXPORT_API_URL =
            '/api/v1/luong/export';

        const elements = {
            tbody:
                document.getElementById(
                    'salary-tbody'
                ),

            search:
                document.getElementById(
                    'search-field'
                ),

            department:
                document.getElementById(
                    'department-filter'
                ),

            position:
                document.getElementById(
                    'position-filter'
                ),

            month:
                document.getElementById(
                    'salary-month-select'
                ),

            year:
                document.getElementById(
                    'salary-year-input'
                ),

            clearFilterButton:
                document.getElementById(
                    'clear-filter-btn'
                ),

            perPage:
                document.getElementById(
                    'salary-per-page'
                ),

            tableTitle:
                document.getElementById(
                    'table-title'
                ),

            tableUpdated:
                document.getElementById(
                    'table-updated'
                ),

            tableStat:
                document.getElementById(
                    'table-stat'
                ),

            reconcileButton:
                document.getElementById(
                    'reconcile-btn'
                ),

            pageInfo:
                document.getElementById(
                    'page-info'
                ),

            pagination:
                document.getElementById(
                    'pagination'
                ),

            exportButton:
                document.getElementById('export-btn'),
        };

        const state = {
            page: 1,

            perPage:
                Number(
                    elements.perPage?.value ||
                    15
                ),

            abortController: null,

            filters: {
                ma_nv: null,
                ky_luong: null,
                ma_pb: null,
                ma_cv: null,
            },
        };

        let searchTimeout = null;

        function toNumber(value) {
            const number =
                Number(value);

            return Number.isFinite(number)
                ? number
                : 0;
        }

        function escapeHtml(value) {
            return String(value ?? '')
                .replaceAll('&', '&amp;')
                .replaceAll('<', '&lt;')
                .replaceAll('>', '&gt;')
                .replaceAll('"', '&quot;')
                .replaceAll("'", '&#039;');
        }

        function getInitials(fullName) {
            const words =
                String(fullName || '')
                    .trim()
                    .split(/\s+/)
                    .filter(Boolean);

            if (words.length === 0) {
                return 'NV';
            }

            if (words.length === 1) {
                return words[0]
                    .substring(0, 2)
                    .toUpperCase();
            }

            return (
                words[words.length - 2][0] +
                words[words.length - 1][0]
            ).toUpperCase();
        }

        function formatMoney(value) {
            if (
                value === null ||
                value === undefined ||
                value === ''
            ) {
                return '—';
            }

            return `${toNumber(value)
                .toLocaleString('vi-VN')} ₫`;
        }

        function formatSalaryPeriod(value) {
            if (!value) {
                return '—';
            }

            const match =
                String(value).match(
                    /^(\d{4})-(\d{2})-\d{2}/
                );

            if (!match) {
                return String(value);
            }

            return `${match[2]}/${match[1]}`;
        }

        function formatDateTime(date) {
            const time =
                date.toLocaleTimeString(
                    'vi-VN',
                    {
                        hour:
                            '2-digit',

                        minute:
                            '2-digit',

                        hour12:
                            false,
                    }
                );

            const day =
                date.toLocaleDateString(
                    'vi-VN'
                );

            return `${time}, ${day}`;
        }

        function iconEye() {
            return `
                <svg viewBox="0 0 16 16" fill="none"
                     stroke="currentColor" stroke-width="1.5"
                     stroke-linecap="round" stroke-linejoin="round">
                    <path d="M1.5 8s2.2-4 6.5-4 6.5 4 6.5 4-2.2 4-6.5 4S1.5 8 1.5 8Z"/>
                    <circle cx="8" cy="8" r="1.8"/>
                </svg>
            `;
        }

        function iconEdit() {
            return `
                <svg viewBox="0 0 16 16" fill="none"
                     stroke="currentColor" stroke-width="1.5"
                     stroke-linecap="round" stroke-linejoin="round">
                    <path d="M10.8 2.2 13.8 5.2"/>
                    <path d="M3 13l1-3.5 7.5-7.5 3 3L7 12.5 3 13Z"/>
                </svg>
            `;
        }

        function iconDelete() {
            return `
                <svg viewBox="0 0 16 16" fill="none"
                     stroke="currentColor" stroke-width="1.5"
                     stroke-linecap="round" stroke-linejoin="round">
                    <path d="M3 4.5h10"/>
                    <path d="M6 2.5h4"/>
                    <path d="M5 4.5l.5 9h5l.5-9"/>
                    <path d="M7 7v4M9 7v4"/>
                </svg>
            `;
        }

        function iconCoefficient() {
            return `
                <svg viewBox="0 0 16 16" fill="none"
                     stroke="currentColor" stroke-width="1.5"
                     stroke-linecap="round" stroke-linejoin="round">
                    <path d="M3 4h10"/>
                    <path d="M3 8h10"/>
                    <path d="M3 12h10"/>
                    <circle cx="6" cy="4" r="1.2" fill="currentColor" stroke="none"/>
                    <circle cx="10" cy="8" r="1.2" fill="currentColor" stroke="none"/>
                    <circle cx="7.5" cy="12" r="1.2" fill="currentColor" stroke="none"/>
                </svg>
            `;
        }

        function iconCreate() {
            return `
                <svg viewBox="0 0 16 16" fill="none"
                     stroke="currentColor" stroke-width="1.5"
                     stroke-linecap="round" stroke-linejoin="round">
                    <path d="M8 3v10M3 8h10"/>
                </svg>
            `;
        }

        function loadSalaryPeriods() {
            if (
                !elements.month ||
                !elements.year
            ) {
                return;
            }

            const now =
                new Date();

            const currentMonth =
                now.getMonth() + 1;

            const currentYear =
                now.getFullYear();

            elements.month.innerHTML =
                '';

            for (
                let month = 1;
                month <= 12;
                month++
            ) {
                const option =
                    document.createElement(
                        'option'
                    );

                option.value =
                    String(month)
                        .padStart(2, '0');

                option.textContent =
                    `Tháng ${month}`;

                option.selected =
                    month ===
                    currentMonth;

                elements.month
                    .appendChild(
                        option
                    );
            }

            elements.year.value =
                String(
                    currentYear
                );
        }

        function getSelectedSalaryPeriod() {
            const month =
                Number(
                    elements.month?.value
                );

            const year =
                Number(
                    elements.year?.value
                );

            if (
                !Number.isInteger(month) ||
                month < 1 ||
                month > 12
            ) {
                return null;
            }

            if (
                !Number.isInteger(year) ||
                year < 2000 ||
                year > 2100
            ) {
                return null;
            }

            return `${year}-${String(month)
                .padStart(2, '0')}-01`;
        }

        function syncFiltersFromUI() {
            state.filters = {
                ma_nv:
                    elements.search
                        ?.value
                        .trim() ||
                    null,

                ky_luong:
                    getSelectedSalaryPeriod(),

                ma_pb:
                    elements.department
                        ?.value ||
                    null,

                ma_cv:
                    elements.position
                        ?.value ||
                    null,
            };
        }

        function buildUrl(
            page = state.page
        ) {
            const url =
                new URL(
                    LUONG_API_URL,
                    window.location.origin
                );

            const params = {
                ...state.filters,
                page,
                per_page:
                state.perPage,
            };

            Object.entries(
                params
            ).forEach(
                ([key, value]) => {
                    if (
                        value !== null &&
                        value !== undefined &&
                        value !== ''
                    ) {
                        url.searchParams.set(
                            key,
                            String(value)
                        );
                    }
                }
            );

            return url.toString();
        }

        function renderLoading() {
            if (!elements.tbody) {
                return;
            }

            elements.tbody.innerHTML = `
                <tr>
                    <td colspan="14"
                        class="text-center text-secondary py-5">
                        <span class="spinner-border spinner-border-sm me-2"></span>
                        Đang tải dữ liệu bảng lương...
                    </td>
                </tr>
            `;
        }

        function renderEmpty() {
            if (!elements.tbody) {
                return;
            }

            elements.tbody.innerHTML = `
                <tr>
                    <td colspan="14"
                        class="text-center text-secondary py-5">
                        Không tìm thấy dữ liệu bảng lương phù hợp.
                    </td>
                </tr>
            `;
        }

        function renderError(
            message
        ) {
            if (!elements.tbody) {
                return;
            }

            elements.tbody.innerHTML = `
                <tr>
                    <td colspan="14"
                        class="text-center py-5">
                        <div class="text-danger fw-semibold">
                            Không thể tải dữ liệu.
                        </div>

                        <div class="small text-secondary mt-1">
                            ${escapeHtml(message)}
                        </div>
                    </td>
                </tr>
            `;
        }

        function buildSalaryActions(
            salary,
            employeeName,
            employeeCode
        ) {
            const actions = [];

            const salaryId =
                salary.ma_luong ?? '';

            const hasSalary =
                String(salaryId) !== '';

            if (
                can(PERMISSION_CODES.READ) &&
                hasSalary
            ) {
                actions.push(`
                    <button
                        class="btn salary-icon-action"
                        type="button"
                        data-salary-action="view"
                        data-id="${escapeHtml(salaryId)}"
                        title="Xem chi tiết"
                        aria-label="Xem chi tiết lương của ${escapeHtml(employeeName)}"
                    >
                        ${iconEye()}
                    </button>
                `);
            }

            if (
                can(
                    COEFFICIENT_PERMISSION_CODES.READ
                )
            ) {
                actions.push(`
                    <button
                        class="btn salary-icon-action"
                        type="button"
                        data-salary-action="coefficient"
                        data-employee-code="${escapeHtml(employeeCode)}"
                        data-employee-name="${escapeHtml(employeeName)}"
                        title="Hệ số lương"
                        aria-label="Xem hệ số lương của ${escapeHtml(employeeName)}"
                    >
                        ${iconCoefficient()}
                    </button>
                `);
            }

            if (
                can(PERMISSION_CODES.UPDATE) &&
                hasSalary
            ) {
                actions.push(`
                    <button
                        class="btn salary-icon-action"
                        type="button"
                        data-salary-action="edit"
                        data-id="${escapeHtml(salaryId)}"
                        title="Chỉnh sửa"
                        aria-label="Chỉnh sửa lương của ${escapeHtml(employeeName)}"
                    >
                        ${iconEdit()}
                    </button>
                `);
            }

            if (
                can(PERMISSION_CODES.DELETE) &&
                hasSalary
            ) {
                actions.push(`
                    <button
                        class="btn salary-icon-action"
                        type="button"
                        data-salary-action="delete"
                        data-id="${escapeHtml(salaryId)}"
                        data-employee-name="${escapeHtml(employeeName)}"
                        title="Xóa"
                        aria-label="Xóa lương của ${escapeHtml(employeeName)}"
                    >
                        ${iconDelete()}
                    </button>
                `);
            }

            if (
                can(PERMISSION_CODES.INSERT) &&
                !hasSalary
            ) {
                actions.push(`
                    <button
                        class="btn salary-icon-action"
                        type="button"
                        data-salary-action="create-for-employee"
                        data-employee-code="${escapeHtml(employeeCode)}"
                        data-employee-name="${escapeHtml(employeeName)}"
                        title="Tạo thông tin lương"
                        aria-label="Tạo thông tin lương cho ${escapeHtml(employeeName)}"
                    >
                        ${iconCreate()}
                    </button>
                `);
            }

            return `
                <div class="salary-row-actions">
                    ${actions.join('')}
                </div>
            `;
        }

        function renderRows(
            rows
        ) {
            if (
                !Array.isArray(rows) ||
                rows.length === 0
            ) {
                renderEmpty();
                return;
            }

            elements.tbody.innerHTML =
                rows.map(
                    (salary) => {
                        const employeeName =
                            salary.ho_ten ||
                            'Chưa cập nhật';

                        const employeeCode =
                            salary.ma_nv ||
                            'N/A';

                        const departmentName =
                            salary.ten_pb ||
                            'Chưa có phòng ban';

                        const positionName =
                            salary.ten_cv ||
                            'Chưa có chức vụ';

                        const allowanceRate =
                            salary.phu_cap ===
                            null ||
                            salary.phu_cap ===
                            undefined
                                ? '—'
                                : `${toNumber(
                                    salary.phu_cap *
                                    100
                                ).toLocaleString(
                                    'vi-VN'
                                )}%`;

                        const workdays =
                            toNumber(
                                salary
                                    .so_ngay_cham_cong
                            ).toLocaleString(
                                'vi-VN',
                                {
                                    minimumFractionDigits:
                                        1,

                                    maximumFractionDigits:
                                        1,
                                }
                            );

                        const statusText =
                            salary.thong_bao_tinh_luong ||
                            'Cần kiểm tra dữ liệu';

                        const ready =
                            salary.trang_thai_tinh_luong === 'READY';

                        const statusHtml = ready
                            ? `
                                <span
                                    class="badge rounded-pill text-bg-success"
                                    data-bs-toggle="tooltip"
                                    data-bs-placement="top"
                                    title="Đã hoàn tất tính lương"
                                >
                                    Đã hoàn tất
                                </span>
                            `
                                                    : `
                                <span
                                    class="badge rounded-pill text-bg-warning salary-status-badge"
                                    data-bs-toggle="tooltip"
                                    data-bs-placement="top"
                                    title="${escapeHtml(statusText)}"
                                >
                                    ${escapeHtml(statusText)}
                                </span>
                            `;
                        return `
                            <tr data-salary-id="${escapeHtml(
                            salary.ma_luong ||
                            ''
                        )}">
                                <td>
                                    <div class="employee">
                                        <div class="avatar">
                                            ${escapeHtml(
                            getInitials(
                                employeeName
                            )
                        )}
                                        </div>

                                        <div>
                                            <div class="employee-name">
                                                ${escapeHtml(
                            employeeName
                        )}
                                            </div>

                                            <div class="meta">
                                                ${escapeHtml(
                            employeeCode
                        )}
                                            </div>
                                        </div>
                                    </div>
                                </td>

                                <td>
                                    <div>
                                        ${escapeHtml(
                            departmentName
                        )}
                                    </div>
                                    <div class="sub">
                                        ${escapeHtml(
                            positionName
                        )}
                                    </div>
                                </td>

                                <td class="text-end">
                                    ${formatSalaryPeriod(
                            salary.ky_luong
                        )}
                                </td>

                                <td class="text-end">
                                    ${formatMoney(
                            salary.thuong
                        )}
                                </td>

                                <td class="text-end">
                                    ${formatMoney(
                            salary.phat
                        )}
                                </td>

                                <td class="text-end">
                                    ${formatMoney(
                            salary.bao_hiem
                        )}
                                </td>

                                <td class="text-end">
                                    ${formatMoney(
                            salary.thue
                        )}
                                </td>

                                <td class="text-end">
                                    ${allowanceRate}
                                </td>

                                <td class="text-end">
                                    <strong>
                                        ${formatMoney(
                            salary.thuc_nhan
                        )}
                                    </strong>
                                </td>

                                <td class="text-end">
                                    ${workdays}
                                </td>

                                <td class="text-end">
                                    ${toNumber(
                            salary
                                .so_lan_vao_muon
                        )}
                                </td>

                                <td class="text-end">
                                    ${toNumber(
                            salary
                                .so_lan_ve_som
                        )}
                                </td>

                                <td>
                                    ${statusHtml}
                                </td>

                                <td class="text-end">
                                    ${buildSalaryActions(
                            salary,
                            employeeName,
                            employeeCode
                        )}
                                </td>
                            </tr>
                        `;
                    }
                ).join('');
            initializeSalaryTooltips();
        }

        function initializeSalaryTooltips() {
            if (
                typeof bootstrap === 'undefined' ||
                !bootstrap.Tooltip
            ) {
                return;
            }

            document
                .querySelectorAll(
                    '#salary-tbody [data-bs-toggle="tooltip"]'
                )
                .forEach((element) => {
                    bootstrap.Tooltip.getOrCreateInstance(
                        element,
                        {
                            placement: 'top',
                            container: 'body',
                        }
                    );
                });
        }

        function renderPagination(
            meta
        ) {
            if (!elements.pagination) {
                return;
            }

            elements.pagination.innerHTML =
                '';

            const current =
                Number(
                    meta.current_page ||
                    1
                );

            const last =
                Number(
                    meta.last_page ||
                    1
                );

            if (last <= 1) {
                return;
            }

            const group =
                document.createElement(
                    'div'
                );

            group.className =
                'btn-group btn-group-sm';

            function append(
                label,
                page,
                disabled,
                active = false
            ) {
                const button =
                    document.createElement(
                        'button'
                    );

                button.type =
                    'button';

                button.className =
                    active
                        ? 'btn btn-primary'
                        : 'btn btn-outline-secondary';

                button.textContent =
                    label;

                button.dataset.page =
                    String(page);

                button.disabled =
                    disabled;

                group.appendChild(
                    button
                );
            }

            append(
                '‹',
                current - 1,
                current <= 1
            );

            for (
                let page = 1;
                page <= last;
                page++
            ) {
                if (
                    last > 7 &&
                    page !== 1 &&
                    page !== last &&
                    Math.abs(
                        page -
                        current
                    ) > 1
                ) {
                    continue;
                }

                append(
                    String(page),
                    page,
                    false,
                    page === current
                );
            }

            append(
                '›',
                current + 1,
                current >= last
            );

            elements.pagination
                .appendChild(
                    group
                );
        }

        function renderPaginationInfo(
            meta
        ) {
            if (!elements.pageInfo) {
                return;
            }

            const total =
                Number(
                    meta.total ||
                    0
                );

            elements.pageInfo.textContent =
                total > 0
                    ? `Hiển thị ${meta.from ?? 0}–${meta.to ?? 0} trên ${total} nhân viên`
                    : 'Không có dữ liệu';
        }

        async function fetchOptions(
            url
        ) {
            const response =
                await fetch(
                    url,
                    {
                        headers: {
                            Accept:
                                'application/json',

                            'X-Requested-With':
                                'XMLHttpRequest',
                        },

                        credentials:
                            'same-origin',
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
                    'Không thể tải dữ liệu.'
                );
            }

            if (
                Array.isArray(
                    result.data
                )
            ) {
                return result.data;
            }

            if (
                Array.isArray(
                    result?.data?.data
                )
            ) {
                return result.data.data;
            }

            return [];
        }

        async function loadFilterOptions() {
            if (
                !can(
                    PERMISSION_CODES.READ
                )
            ) {
                return;
            }

            const [
                departments,
                positions,
            ] =
                await Promise.all([
                    fetchOptions(
                        PHONG_BAN_API_URL
                    ),

                    fetchOptions(
                        CHUC_VU_API_URL
                    ),
                ]);

            if (
                elements.department
            ) {
                elements.department.innerHTML =
                    '<option value="">-- Tất cả phòng ban --</option>';

                departments.forEach(
                    (item) => {
                        const option =
                            document.createElement(
                                'option'
                            );

                        option.value =
                            item.ma_pb;

                        option.textContent =
                            item.ten_pb;

                        elements.department
                            .appendChild(
                                option
                            );
                    }
                );
            }

            if (
                elements.position
            ) {
                elements.position.innerHTML =
                    '<option value="">-- Tất cả chức vụ --</option>';

                positions.forEach(
                    (item) => {
                        const option =
                            document.createElement(
                                'option'
                            );

                        option.value =
                            item.ma_cv;

                        option.textContent =
                            item.ten_cv;

                        elements.position
                            .appendChild(
                                option
                            );
                    }
                );
            }
        }

        async function loadSalaryData(
            page = 1
        ) {
            if (
                !can(
                    PERMISSION_CODES.READ
                ) ||
                !elements.tbody
            ) {
                return;
            }

            state.page =
                page;

            state.abortController
                ?.abort();

            state.abortController =
                new AbortController();

            renderLoading();

            try {
                const response =
                    await fetch(
                        buildUrl(
                            page
                        ),
                        {
                            headers: {
                                Accept:
                                    'application/json',

                                'X-Requested-With':
                                    'XMLHttpRequest',
                            },

                            credentials:
                                'same-origin',

                            signal:
                            state
                                .abortController
                                .signal,
                        }
                    );

                const result =
                    await response
                        .json();

                if (
                    !response.ok ||
                    result.success ===
                    false
                ) {
                    throw new Error(
                        result.message ||
                        'Không thể tải bảng lương.'
                    );
                }

                const paginator =
                    result.data;

                const rows =
                    Array.isArray(
                        paginator?.data
                    )
                        ? paginator.data
                        : [];

                renderRows(
                    rows
                );

                if (elements.tableTitle) {
                    const period =
                        state.filters.ky_luong ||
                        rows?.[0]?.ky_luong ||
                        null;

                    elements.tableTitle.textContent =
                        period
                            ? `Chi tiết kỳ lương tháng ${formatSalaryPeriod(period)}`
                            : 'Chi tiết kỳ lương';
                }

                renderPagination(
                    paginator
                );

                renderPaginationInfo(
                    paginator
                );

                if (
                    elements.tableUpdated
                ) {
                    elements.tableUpdated.textContent =
                        `Dữ liệu cập nhật lần cuối lúc ${formatDateTime(
                            new Date()
                        )}.`;
                }

                if (
                    elements.tableStat
                ) {
                    elements.tableStat.textContent =
                        `${rows.length}/${paginator.total ?? rows.length} bản ghi trên trang`;
                }
            } catch (error) {
                if (
                    error.name ===
                    'AbortError'
                ) {
                    return;
                }

                console.error(
                    error
                );

                renderError(
                    error.message
                );
            }
        }

        function applyFilters() {
            syncFiltersFromUI();

            if (
                can(
                    PERMISSION_CODES.READ
                )
            ) {
                loadSalaryData(
                    1
                );
            }
        }

        elements.search
            ?.addEventListener(
                'input',
                () => {
                    clearTimeout(
                        searchTimeout
                    );

                    searchTimeout =
                        setTimeout(
                            applyFilters,
                            350
                        );
                }
            );

        [
            elements.department,
            elements.position,
            elements.month,
            elements.year,
        ].forEach(
            (element) => {
                element
                    ?.addEventListener(
                        'change',
                        applyFilters
                    );
            }
        );

        elements.clearFilterButton
            ?.addEventListener(
                'click',
                () => {
                    if (
                        elements.search
                    ) {
                        elements.search.value =
                            '';
                    }

                    if (
                        elements.department
                    ) {
                        elements.department.value =
                            '';
                    }

                    if (
                        elements.position
                    ) {
                        elements.position.value =
                            '';
                    }

                    loadSalaryPeriods();
                    applyFilters();
                }
            );

        elements.perPage
            ?.addEventListener(
                'change',
                () => {
                    state.perPage =
                        Number(
                            elements
                                .perPage
                                .value ||
                            15
                        );

                    loadSalaryData(
                        1
                    );
                }
            );

        elements.pagination
            ?.addEventListener(
                'click',
                (event) => {
                    const button =
                        event.target.closest(
                            '[data-page]'
                        );

                    if (
                        !button ||
                        button.disabled
                    ) {
                        return;
                    }

                    loadSalaryData(
                        Number(
                            button.dataset
                                .page
                        )
                    );
                }
            );

        elements.exportButton
            ?.addEventListener(
                'click',
                exportSalaryReport
            );

        async function exportSalaryReport() {
            if (
                !guard(
                    PERMISSION_CODES.READ,
                    'xuất báo cáo lương'
                )
            ) {
                return;
            }

            const month =
                Number(
                    elements.month?.value
                );

            const year =
                Number(
                    elements.year?.value
                );

            const kyLuong =
                `${year}-${String(month).padStart(2, '0')}-01`;

            const url = new URL(
                '/api/v1/luong/export',
                window.location.origin
            );

            url.searchParams.set(
                'ky_luong',
                kyLuong
            );

            const keyword =
                elements.search?.value
                    .trim();

            if (keyword) {
                url.searchParams.set(
                    'tu_khoa',
                    keyword
                );
            }

            if (elements.department?.value) {
                url.searchParams.set(
                    'ma_pb',
                    elements.department.value
                );
            }

            if (elements.position?.value) {
                url.searchParams.set(
                    'ma_cv',
                    elements.position.value
                );
            }

            const oldText =
                elements.exportButton.textContent;

            elements.exportButton.disabled = true;
            elements.exportButton.textContent =
                'Đang xuất...';

            try {
                const response = await fetch(
                    url.toString(),
                    {
                        method: 'GET',

                        headers: {
                            Accept:
                                'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',

                            'X-Requested-With':
                                'XMLHttpRequest',
                        },

                        credentials:
                            'same-origin',
                    }
                );

                if (!response.ok) {
                    const contentType =
                        response.headers.get(
                            'content-type'
                        ) || '';

                    let message =
                        `Không thể xuất báo cáo. HTTP ${response.status}`;

                    if (
                        contentType.includes(
                            'application/json'
                        )
                    ) {
                        const result =
                            await response.json();

                        message =
                            result.message ||
                            message;
                    }

                    throw new Error(
                        message
                    );
                }

                /*
                 * Backend trả file Excel.
                 */
                const blob =
                    await response.blob();

                /*
                 * Tạo URL tạm cho file.
                 */
                const objectUrl =
                    URL.createObjectURL(
                        blob
                    );

                /*
                 * Tạo thẻ <a> tạm.
                 */
                const anchor =
                    document.createElement(
                        'a'
                    );

                anchor.href =
                    objectUrl;

                anchor.download =
                    `BaoCaoLuong_${String(month).padStart(2, '0')}_${year}.xlsx`;

                /*
                 * Không cần hiển thị thẻ a.
                 */
                anchor.style.display =
                    'none';

                /*
                 * Add vào DOM.
                 */
                document.body.appendChild(
                    anchor
                );

                /*
                 * Trigger download.
                 */
                anchor.click();

                /*
                 * Xóa thẻ <a> sau khi click.
                 */
                anchor.remove();

                /*
                 * Giải phóng URL blob.
                 */
                setTimeout(() => {
                    URL.revokeObjectURL(
                        objectUrl
                    );
                }, 1000);

            } catch (error) {
                console.error(
                    'Export salary failed:',
                    error
                );

                window.alert(
                    error.message
                );

            } finally {
                elements.exportButton.disabled =
                    false;

                elements.exportButton.textContent =
                    oldText;
            }
        }

        document.addEventListener(
            'salary:data-changed',
            () => {
                loadSalaryData(
                    state.page
                );
            }
        );

        window.salaryFilterManager = {
            getFilters() {
                return {
                    ...state.filters,
                    page:
                    state.page,
                    per_page:
                    state.perPage,
                };
            },

            reload() {
                loadSalaryData(
                    state.page
                );
            },
        };

        loadSalaryPeriods();

        const hasAccess =
            await initializeSalaryPermissionUI();

        if (!hasAccess) {
            return;
        }

        syncFiltersFromUI();

        if (
            can(
                PERMISSION_CODES.READ
            )
        ) {
            await loadFilterOptions();
            syncFiltersFromUI();
            await loadSalaryData(1);
        }
    }
);
