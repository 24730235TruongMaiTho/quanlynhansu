document.addEventListener('DOMContentLoaded', () => {
    const LUONG_API_URL = '/api/v1/luong';
    const PHONG_BAN_API_URL = '/api/v1/luong/phong-ban';
    const CHUC_VU_API_URL = '/api/v1/luong/chuc-vu';


    const elements = {
        tbody: document.getElementById('salary-tbody'),
        search: document.getElementById('search-field'),
        department: document.getElementById('department-filter'),
        position: document.getElementById('position-filter'),
        month: document.getElementById('salary-month-select'),
        year: document.getElementById('salary-year-input'),

        clearFilterButton: document.getElementById('clear-filter-btn'),

        perPage: document.getElementById('salary-per-page'),
        refresh: document.getElementById('salary-refresh'),

        tableTitle: document.getElementById('table-title'),
        tableUpdated: document.getElementById('table-updated'),
        tableStat: document.getElementById('table-stat'),
        reconcileButton: document.getElementById('reconcile-btn'),

        pageInfo: document.getElementById('page-info'),
        pagination: document.getElementById('pagination'),
    };

    if (!elements.tbody) {
        console.warn('Không tìm thấy #salary-tbody');

        return;
    }

    const state = {
        page: 1,
        perPage: Number(elements.perPage?.value || 15),
        abortController: null,
        filters: {
            ma_nv: null,
            ky_luong: null,
            ma_pb: null,
            ma_cv: null,
        },

    };

    const defaultFilters = {
        month: elements.month?.value || String(new Date().getMonth()),
        year: elements.year?.value || String(new Date().getFullYear()),
    };

    let searchTimeout = null;

    /**
     * Chuyển giá trị về số an toàn.
     */
    function toNumber(value) {
        const number = Number(value);

        return Number.isFinite(number) ? number : 0;
    }

    function loadSalaryPeriods() {
        const monthSelect = document.getElementById('salary-month-select');
        const yearInput = document.getElementById('salary-year-input');

        const currentDate = new Date();
        const currentMonth = currentDate.getMonth() + 1;
        const currentYear = currentDate.getFullYear();

        // Đổ 12 tháng
        if (monthSelect) {
            monthSelect.innerHTML = '';

            for (let month = 1; month <= 12; month++) {
                const option = document.createElement('option');

                option.value = String(month).padStart(2, '0');
                option.textContent = month;
                option.selected = month === currentMonth;

                monthSelect.appendChild(option);
            }
        }

        // Đổ danh sách năm: 2 năm trước đến 1 năm sau
        if (yearInput) {
            yearInput.innerHTML = '';
            console.log("currentYear: ", currentYear);
            yearInput.innerHTML = String(currentYear);
            yearInput.value = String(currentYear);
        }
    }

    function formatSalaryPeriod(value) {
        if (!value) {
            return '—';
        }

        // Hỗ trợ cả "2026-07-01" và chuỗi datetime dài hơn.
        const match = String(value).match(/^(\d{4})-(\d{2})-\d{2}/);

        if (!match) {
            return String(value);
        }

        const year = match[1];
        const month = match[2];

        return `${month}/${year}`;
    }

    /**
     * Format tiền Việt Nam.
     */
    function formatMoney(value) {
        if (
            value === null ||
            value === undefined ||
            value === ''
        ) {
            return '—';
        }

        return `${toNumber(value).toLocaleString('vi-VN')} ₫`;
    }

    /**
     * Format số ngày công.
     */
    function formatWorkday(value) {
        return toNumber(value).toLocaleString('vi-VN', {
            minimumFractionDigits: 1,
            maximumFractionDigits: 1,
        });
    }

    /**
     * Escape nội dung trước khi đưa vào innerHTML.
     */
    function escapeHtml(value) {
        return String(value ?? '')
            .replaceAll('&', '&amp;')
            .replaceAll('<', '&lt;')
            .replaceAll('>', '&gt;')
            .replaceAll('"', '&quot;')
            .replaceAll("'", '&#039;');
    }

    /**
     * Lấy chữ cái đầu của họ tên để làm avatar.
     */
    function getInitials(fullName) {
        const words = String(fullName || '')
            .trim()
            .split(/\s+/)
            .filter(Boolean);

        if (words.length === 0) {
            return 'NV';
        }

        if (words.length === 1) {
            return words[0].substring(0, 2).toUpperCase();
        }

        return (
            words[words.length - 2][0] +
            words[words.length - 1][0]
        ).toUpperCase();
    }

    function getSelectedSalaryPeriod() {
        const month = elements.month?.value;
        const year = elements.year?.value;

        if (!month || !year) {
            return null;
        }

        const normalizedMonth = String(month).padStart(2, '0');

        return `${year}-${normalizedMonth}-01`;
    }

    function syncFiltersFromUI() {
        state.filters.ma_nv =
            elements.search?.value.trim() || null;

        state.filters.ky_luong =
            getSelectedSalaryPeriod();

        state.filters.ma_pb =
            elements.department?.value || null;

        state.filters.ma_cv =
            elements.position?.value || null;
    }

    /**
     * Lấy các filter hiện tại.
     */
    function getFilters(page = state.page) {
        return {
            ...state.filters,
            page,
            per_page: state.perPage,
        };
    }

    /**
     * Tạo URL API kèm query parameters.
     */
    function buildUrl(page) {
        const url = new URL(LUONG_API_URL, window.location.origin);
        const filters = getFilters(page);

        console.log("buildUrl filters:", filters);

        Object.entries(filters).forEach(([key, value]) => {
            if (
                value !== null &&
                value !== undefined &&
                value !== ''
            ) {
                url.searchParams.set(key, value);
            }
        });

        return url.toString();
    }

    function renderLoading() {
        elements.tbody.innerHTML = `
        <tr>
            <td colspan="15" class="table-message">
                Đang tải dữ liệu bảng lương...
            </td>
        </tr>
    `;

        if (elements.tableUpdated) {
            elements.tableUpdated.textContent = 'Đang cập nhật dữ liệu...';
        }

        if (elements.tableStat) {
            elements.tableStat.textContent = 'Đang tải...';
        }

        if (elements.reconcileButton) {
            elements.reconcileButton.disabled = true;
        }
    }

    function renderEmpty() {
        elements.tbody.innerHTML = `
            <tr>
                <td colspan="10" style="text-align:center; padding:40px">
                    Không tìm thấy dữ liệu bảng lương phù hợp.
                </td>
            </tr>
        `;
    }

    function renderError(message) {
        elements.tbody.innerHTML = `
            <tr>
                <td colspan="10" style="text-align:center; padding:40px">
                    <strong>Không thể tải dữ liệu.</strong>
                    <div class="sub">
                        ${escapeHtml(message)}
                    </div>
                </td>
            </tr>
        `;
    }

    /**
     * Render danh sách bảng lương.
     */
    function renderRows(rows) {
        if (!Array.isArray(rows) || rows.length === 0) {
            renderEmpty();
            return;
        }

        elements.tbody.innerHTML = rows
            .map((salary) => {
                const employeeName =
                    salary.ho_ten || 'Chưa cập nhật';

                const employeeCode =
                    salary.ma_nv || 'N/A';

                const departmentName =
                    salary.ten_pb || 'Chưa có phòng ban';

                const positionName =
                    salary.ten_cv || 'Chưa có chức vụ';

                /*
                 * Không nên dựa vào thuc_nhan > 0 để xác định
                 * đã có bảng lương hay chưa.
                 *
                 * Một bảng lương có thể thực nhận bằng 0
                 * nhưng vẫn là bản ghi hợp lệ.
                 */
                const hasChamCong =
                    salary.so_ngay_cham_cong !== null && salary.so_ngay_cham_cong > 0

                const hasSalary = salary.ky_luong != null;

                const statusHtml = !hasSalary
                    ? `
                    <span class="label label-attention">
                        Chưa có dữ liệu kỳ lương
                    </span>
                ` : hasChamCong ? `
                    <span class="label label-success">
                        Đã hoàn tất
                    </span>
                `
                    : `
                    <span class="label label-attention">
                        Chưa có dữ liệu chấm công
                    </span>
                `;

                /*
                 * phu_cap hiện là hệ số:
                 * 0.5 → 50%
                 * 0.3 → 30%
                 */
                const allowanceRate =
                    salary.phu_cap === null ||
                    salary.phu_cap === undefined
                        ? '—'
                        : `${toNumber(salary.phu_cap * 100)
                            .toLocaleString('vi-VN', {
                                maximumFractionDigits: 2,
                            })}%`;

                const workdays = toNumber(
                    salary.so_ngay_cham_cong
                ).toLocaleString('vi-VN', {
                    minimumFractionDigits: 1,
                    maximumFractionDigits: 1,
                });

                const lateCount = toNumber(
                    salary.so_lan_vao_muon
                ).toLocaleString('vi-VN');

                const earlyLeaveCount = toNumber(
                    salary.so_lan_ve_som
                ).toLocaleString('vi-VN');

                return `
                <tr
                    data-salary-id="${escapeHtml(
                    salary.ma_luong || ''
                )}"
                >
                    <td>
                        <input
                            class="checkbox"
                            type="checkbox"
                            value="${escapeHtml(
                    salary.ma_luong || ''
                )}"
                            aria-label="Chọn ${escapeHtml(employeeName)}"
                        >
                    </td>

                    <td>
                        <div class="employee">
                            <div class="avatar">
                                ${escapeHtml(
                    getInitials(employeeName)
                )}
                            </div>

                            <div>
                                <div class="employee-name">
                                    ${escapeHtml(employeeName)}
                                </div>

                                <div class="meta">
                                    ${escapeHtml(employeeCode)}
                                </div>
                            </div>
                        </div>
                    </td>

                    <td>
                        <div>
                            ${escapeHtml(departmentName)}
                        </div>

                        <div class="sub">
                            ${escapeHtml(positionName)}
                        </div>
                    </td>

                    <td class="numeric">
                        ${formatSalaryPeriod(salary.ky_luong)}
                    </td>

                    <td class="numeric">
                        ${formatMoney(salary.thuong)}
                    </td>

                    <td class="numeric">
                        ${formatMoney(salary.phat)}
                    </td>

                    <td class="numeric">
                        ${formatMoney(salary.bao_hiem)}
                    </td>

                    <td class="numeric">
                        ${formatMoney(salary.thue)}
                    </td>

                    <td class="numeric">
                        ${allowanceRate}
                    </td>

                    <td class="numeric">
                        <strong>
                            ${formatMoney(salary.thuc_nhan)}
                        </strong>
                    </td>

                    <td class="numeric">
                        ${workdays}
                    </td>

                    <td class="numeric">
                        ${lateCount}
                    </td>

                    <td class="numeric">
                        ${earlyLeaveCount}
                    </td>

                    <td>
                        ${statusHtml}
                    </td>

                    <td>
                        <div class="row-actions">
                            <button
                                class="btn salary-action-btn"
                                type="button"
                                data-salary-action="view"
                                data-id="${escapeHtml(salary.ma_luong || '')}"
                                title="Xem chi tiết"
                                aria-label="Xem chi tiết lương của ${escapeHtml(employeeName)}"
                            >
                                Xem
                            </button>

                            <button
                                class="btn salary-action-btn"
                                type="button"
                                data-salary-action="edit"
                                data-id="${escapeHtml(salary.ma_luong || '')}"
                                title="Chỉnh sửa"
                                aria-label="Chỉnh sửa lương của ${escapeHtml(employeeName)}"
                            >
                                Sửa
                            </button>

                            <button
                                class="btn btn-danger salary-action-btn"
                                type="button"
                                data-salary-action="delete"
                                data-id="${escapeHtml(salary.ma_luong || '')}"
                                data-employee-name="${escapeHtml(employeeName)}"
                                title="Xóa"
                                aria-label="Xóa lương của ${escapeHtml(employeeName)}"
                            >
                                Xóa
                            </button>
                        </div>
                </td>
                </tr>
            `;
            })
            .join('');
    }

    /**
     * Tạo danh sách số trang cần hiển thị.
     */
    function getPageItems(currentPage, lastPage) {
        if (lastPage <= 7) {
            return Array.from(
                { length: lastPage },
                (_, index) => index + 1
            );
        }

        const pages = new Set([
            1,
            lastPage,
            currentPage - 1,
            currentPage,
            currentPage + 1,
        ]);

        const validPages = Array.from(pages)
            .filter((page) => page >= 1 && page <= lastPage)
            .sort((a, b) => a - b);

        const items = [];

        validPages.forEach((page, index) => {
            const previousPage = validPages[index - 1];

            if (
                previousPage !== undefined &&
                page - previousPage > 1
            ) {
                items.push('ellipsis');
            }

            items.push(page);
        });

        return items;
    }

    /**
     * Render phân trang.
     */
    function renderPagination(meta) {
        if (!elements.pagination) {
            return;
        }

        const currentPage = Number(meta.current_page || 1);
        const lastPage = Number(meta.last_page || 1);

        if (lastPage <= 1) {
            elements.pagination.innerHTML = '';

            return;
        }

        const pageItems = getPageItems(currentPage, lastPage);

        const previousButton = `
            <button
                type="button"
                data-page="${currentPage - 1}"
                ${currentPage <= 1 ? 'disabled' : ''}
                aria-label="Trang trước"
            >
                ‹
            </button>
        `;

        const nextButton = `
            <button
                type="button"
                data-page="${currentPage + 1}"
                ${currentPage >= lastPage ? 'disabled' : ''}
                aria-label="Trang sau"
            >
                ›
            </button>
        `;

        const pageButtons = pageItems
            .map((item) => {
                if (item === 'ellipsis') {
                    return `
                        <span
                            class="pagination-ellipsis"
                            aria-hidden="true"
                        >
                            …
                        </span>
                    `;
                }

                return `
                    <button
                        type="button"
                        data-page="${item}"
                        ${
                    item === currentPage
                        ? 'aria-current="page"'
                        : ''
                }
                    >
                        ${item}
                    </button>
                `;
            })
            .join('');

        elements.pagination.innerHTML =
            previousButton +
            pageButtons +
            nextButton;
    }

    /**
     * Render thông tin: đang hiển thị bao nhiêu bản ghi.
     */
    function renderPaginationInfo(meta) {
        if (!elements.pageInfo) {
            return;
        }

        console.log("renderPaginationInfo meta:", meta);
        const total = Number(meta.total || 0);
        const from = meta.from ?? 0;
        const to = meta.to ?? 0;

        elements.pageInfo.textContent =
            total > 0
                ? `Hiển thị ${from}–${to} trên ${total} nhân viên`
                : 'Không có dữ liệu';
    }

    function formatDateTime(date) {
        if (!(date instanceof Date) || Number.isNaN(date.getTime())) {
            return '—';
        }

        const time = date.toLocaleTimeString('vi-VN', {
            hour: '2-digit',
            minute: '2-digit',
            hour12: false,
        });

        const day = date.toLocaleDateString('vi-VN', {
            day: '2-digit',
            month: '2-digit',
            year: 'numeric',
        });

        return `${time}, ${day}`;
    }

    function updateTableHeader(rows, paginator = {}, summary = null) {
        /*
         * Ưu tiên kỳ lương đang lưu trong filter global.
         * Nếu chưa có thì ghép tháng + năm từ giao diện.
         */
        const selectedPeriod =
            state.filters.ky_luong ||
            getSelectedSalaryPeriod();

        /*
         * Nếu không lấy được từ filter thì dùng kỳ lương
         * của bản ghi đầu tiên làm fallback.
         */
        const rowPeriod =
            Array.isArray(rows) && rows.length > 0
                ? rows[0].ky_luong
                : null;

        const period = selectedPeriod || rowPeriod;

        /*
         * Cập nhật tiêu đề bảng.
         */
        if (elements.tableTitle) {
            elements.tableTitle.textContent = period
                ? `Chi tiết kỳ lương tháng ${formatSalaryPeriod(period)}`
                : 'Chi tiết bảng lương';
        }

        /*
         * Cập nhật thời điểm tải dữ liệu thành công.
         */
        if (elements.tableUpdated) {
            elements.tableUpdated.textContent =
                `Dữ liệu cập nhật lần cuối lúc ${formatDateTime(
                    new Date()
                )}.`;
        }

        if (!elements.tableStat) {
            return;
        }

        /*
         * Nếu backend trả summary thì ưu tiên dùng summary.
         */
        if (
            summary &&
            summary.completed !== undefined &&
            summary.total !== undefined
        ) {
            elements.tableStat.textContent =
                `${toNumber(summary.completed)}/${toNumber(
                    summary.total
                )} hoàn tất`;

            return;
        }

        /*
         * Nếu backend chưa trả summary,
         * tính số hoàn tất trong trang hiện tại.
         *
         * Điều kiện này đồng bộ với renderRows():
         * có dữ liệu chấm công lớn hơn 0 thì hoàn tất.
         */
        const completedOnPage = Array.isArray(rows)
            ? rows.filter((salary) => {
                return toNumber(
                    salary.so_ngay_cham_cong
                ) > 0;
            }).length
            : 0;

        const currentPageCount = Array.isArray(rows)
            ? rows.length
            : 0;

        const total = toNumber(paginator.total);

        if (total > 0) {
            elements.tableStat.textContent =
                `${completedOnPage}/${currentPageCount} hoàn tất trên trang · ${total} bản ghi`;
        } else {
            elements.tableStat.textContent =
                `${completedOnPage}/${currentPageCount} hoàn tất trên trang`;
        }
    }

    /**
     * Gọi API lấy bảng lương.
     */
    async function loadSalaryData(page = 1) {
        state.page = page;

        if (state.abortController) {
            state.abortController.abort();
        }

        state.abortController = new AbortController();

        renderLoading();

        try {
            const response = await fetch(buildUrl(page), {
                method: 'GET',
                headers: {
                    Accept: 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                },
                signal: state.abortController.signal,
            });

            const contentType =
                response.headers.get('content-type') || '';

            if (!contentType.includes('application/json')) {
                const responseText = await response.text();

                console.error(
                    'API trả về dữ liệu không phải JSON:',
                    responseText
                );

                throw new Error(
                    `API không trả về JSON. HTTP ${response.status}`
                );
            }

            const result = await response.json();

            if (!response.ok) {
                throw new Error(
                    result.message ||
                    `Request thất bại với HTTP ${response.status}`
                );
            }

            if (!result.success) {
                throw new Error(
                    result.message || 'Không thể tải bảng lương.'
                );
            }

            /*
             * Response từ LengthAwarePaginator:
             *
             * result.data = {
             *   current_page,
             *   data: [],
             *   last_page,
             *   total,
             *   ...
             * }
             */

            const paginator = result.data;

            const rows = Array.isArray(paginator?.data)
                ? paginator.data
                : Array.isArray(paginator)
                    ? paginator
                    : [];

            const isPaginated =
                paginator !== null &&
                typeof paginator === 'object' &&
                !Array.isArray(paginator) &&
                Array.isArray(paginator.data);

            renderRows(rows);

            const paginationMeta = Array.isArray(paginator)
                ? {}
                : paginator;

            /*
             * Hỗ trợ một trong hai cấu trúc:
             *
             * result.summary
             *
             * hoặc:
             *
             * result.data.summary
             */
            const summary =
                result.summary ??
                paginationMeta.summary ??
                null;

            updateTableHeader(
                rows,
                paginationMeta,
                summary
            );

            console.log("isPaginated:", isPaginated);
            if (isPaginated) {
                renderPagination(paginator);
                renderPaginationInfo(paginator);
            }

            if (elements.reconcileButton) {
                elements.reconcileButton.disabled = false;
            }
        } catch (error) {
            if (error.name === 'AbortError') {
                return;
            }

            console.error('Error loading salary data:', error);

            renderError(error.message);

            if (elements.tableUpdated) {
                elements.tableUpdated.textContent =
                    'Không thể cập nhật dữ liệu bảng lương.';
            }

            if (elements.tableStat) {
                elements.tableStat.textContent = 'Có lỗi xảy ra';
            }

            if (elements.reconcileButton) {
                elements.reconcileButton.disabled = false;
            }

            if (elements.pagination) {
                elements.pagination.innerHTML = '';
            }

            if (elements.pageInfo) {
                elements.pageInfo.textContent = '';
            }
        }
    }

    function applyFilters() {
        syncFiltersFromUI();

        state.page = 1;

        loadSalaryData(1);
    }

    function clearFilters() {
        clearTimeout(searchTimeout);

        if (elements.search) {
            elements.search.value = '';
        }

        if (elements.department) {
            elements.department.value = '';
        }

        if (elements.position) {
            elements.position.value = '';
        }

        if (elements.month) {
            elements.month.value = defaultFilters.month;
        }

        if (elements.year) {
            elements.year.value = defaultFilters.year;
        }

        state.perPage =
            Number(elements.perPage?.value || 15);

        syncFiltersFromUI();

        state.page = 1;

        loadSalaryData(1);
    }

    /**
     * Tìm kiếm có debounce để tránh gọi API liên tục.
     */
    elements.search?.addEventListener('input', () => {
        clearTimeout(searchTimeout);

        searchTimeout = setTimeout(() => {
            applyFilters();
        }, 350);
    });

    elements.search?.addEventListener('keydown', (event) => {
        if (event.key !== 'Enter') {
            return;
        }

        event.preventDefault();
        clearTimeout(searchTimeout);

        applyFilters();
    });

    [
        elements.department,
        elements.position,
        elements.month,
        elements.year,
    ].forEach((element) => {
        element?.addEventListener('change', () => {
            applyFilters();
        });
    });

    elements.clearFilterButton?.addEventListener(
        'click',
        clearFilters
    );

    /**
     * Thay đổi số lượng bản ghi mỗi trang.
     */
    elements.perPage?.addEventListener('change', () => {
        state.perPage = Math.max(
            1,
            Number(elements.perPage.value || 15)
        );

        loadSalaryData(1);
    });

    /**
     * Event delegation cho phân trang.
     */
    elements.pagination?.addEventListener('click', (event) => {
        const button = event.target.closest('[data-page]');

        if (!button || button.disabled) {
            return;
        }

        const page = Number(button.dataset.page);

        if (!Number.isInteger(page) || page < 1) {
            return;
        }

        loadSalaryData(page);

        document.querySelector('.table-card')?.scrollIntoView({
            behavior: 'smooth',
            block: 'start',
        });
    });

    /**
     * Nút tải lại.
     */
    elements.refresh?.addEventListener('click', () => {
        loadSalaryData(state.page);
    });

    window.salaryFilterManager = {
        getFilters() {
            return {
                ...state.filters,
                page: state.page,
                per_page: state.perPage,
            };
        },
        apply: applyFilters,
        clear: clearFilters,
        reload() {
            loadSalaryData(state.page);
        },
    };

    function extractApiData(result) {
        if (Array.isArray(result)) {
            return result;
        }

        if (Array.isArray(result?.data)) {
            return result.data;
        }

        if (Array.isArray(result?.data?.data)) {
            return result.data.data;
        }

        return [];
    }

    async function fetchOptions(url) {
        if (!url) {
            return [];
        }

        const response = await fetch(url, {
            method: 'GET',
            headers: {
                Accept: 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
            },
        });

        const contentType =
            response.headers.get('content-type') || '';

        if (!contentType.includes('application/json')) {
            const responseText = await response.text();

            console.error(
                'API không trả về JSON:',
                responseText
            );

            throw new Error(
                `API không trả về JSON. HTTP ${response.status}`
            );
        }

        const result = await response.json();

        if (!response.ok) {
            throw new Error(
                result.message ||
                `Không thể tải dữ liệu. HTTP ${response.status}`
            );
        }

        if (
            result.success !== undefined &&
            result.success === false
        ) {
            throw new Error(
                result.message || 'Không thể tải dữ liệu.'
            );
        }

        return extractApiData(result);
    }

    // Fetch phong ban, chuc vu options

    async function loadAllPhongBan() {
        const departmentSelect =
            document.getElementById('department-filter');

        if (!departmentSelect) {
            console.warn(
                'Không tìm thấy #department-filter'
            );

            return;
        }

        /*
         * Giữ lại option "Tất cả phòng ban".
         */
        departmentSelect.innerHTML = `
        <option value="">
            -- Tất cả phòng ban --
        </option>
    `;

        /*
         * URL chưa cấu hình thì chỉ giữ option mặc định.
         */
        if (!PHONG_BAN_API_URL) {
            return;
        }

        departmentSelect.disabled = true;

        try {
            const phongBans = await fetchOptions(
                PHONG_BAN_API_URL
            );

            const fragment =
                document.createDocumentFragment();

            phongBans.forEach((phongBan) => {
                if (
                    phongBan.ma_pb === null ||
                    phongBan.ma_pb === undefined
                ) {
                    return;
                }

                const option =
                    document.createElement('option');

                // Giá trị dùng để gửi filter
                option.value = String(phongBan.ma_pb);

                // Nội dung hiển thị
                option.textContent =
                    phongBan.ten_pb ||
                    `Phòng ban ${phongBan.ma_pb}`;

                fragment.appendChild(option);
            });

            departmentSelect.appendChild(fragment);
        } catch (error) {
            console.error(
                'Error loading departments:',
                error
            );
        } finally {
            departmentSelect.disabled = false;
        }
    }

    async function loadAllChucVu() {
        const positionSelect =
            document.getElementById('position-filter');

        if (!positionSelect) {
            console.warn(
                'Không tìm thấy #position-filter'
            );

            return;
        }

        /*
         * Giữ lại option mặc định.
         */
        positionSelect.innerHTML = `
        <option value="">
            -- Tất cả chức vụ --
        </option>
    `;

        /*
         * URL chưa cấu hình thì không gọi API.
         */
        if (!CHUC_VU_API_URL) {
            return;
        }

        positionSelect.disabled = true;

        try {
            const chucVus = await fetchOptions(
                CHUC_VU_API_URL
            );

            const fragment =
                document.createDocumentFragment();

            chucVus.forEach((chucVu) => {
                if (
                    chucVu.ma_cv === null ||
                    chucVu.ma_cv === undefined
                ) {
                    return;
                }

                const option =
                    document.createElement('option');

                // Giá trị dùng để gửi filter
                option.value = String(chucVu.ma_cv);

                // Nội dung hiển thị
                option.textContent =
                    chucVu.ten_cv ||
                    `Chức vụ ${chucVu.ma_cv}`;

                fragment.appendChild(option);
            });

            positionSelect.appendChild(fragment);
        } catch (error) {
            console.error(
                'Error loading positions:',
                error
            );
        } finally {
            positionSelect.disabled = false;
        }
    }

    async function loadFilterOptions() {
        await Promise.all([
            loadAllPhongBan(),
            loadAllChucVu(),
        ]);
    }

    async function initializeSalaryPage() {
        loadSalaryPeriods();

        await loadFilterOptions();

        syncFiltersFromUI();

        await loadSalaryData(1);
    }

    document.addEventListener(
        'salary:data-changed',
        () => {
            loadSalaryData(state.page);
        }
    );

    initializeSalaryPage();
});
