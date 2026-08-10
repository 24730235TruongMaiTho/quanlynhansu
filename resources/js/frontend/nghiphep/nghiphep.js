import '../../../css/nghi-phep/nghiphep-modal.css'
document.addEventListener('DOMContentLoaded', () => {
    const NGHI_PHEP_API_URL = '/api/v1/nghi-phep';
    const NHAN_VIEN_API_URL = '/api/v1/nghi-phep/nhan-vien';
    const PHONG_BAN_API_URL = '/api/v1/nghi-phep/phong-ban';
    const CHUC_VU_API_URL = '/api/v1/nghi-phep/chuc-vu';
    const LOAI_PHEP_API_URL = '/api/v1/nghi-phep/loai-phep';

    const elements = {
        search: document.getElementById('search-field'),
        department: document.getElementById('department-filter'),
        position: document.getElementById('position-filter'),
        clearFilterButton: document.getElementById('clear-filter-btn'),

        employeeTbody: document.getElementById('employee-tbody'),
        employeePageInfo: document.getElementById('employee-page-info'),
        employeePagination: document.getElementById('employee-pagination'),
        selectedEmployeeBadge: document.getElementById('selected-employee-badge'),

        leaveTbody: document.getElementById('leave-tbody'),
        pageInfo: document.getElementById('page-info'),
        pagination: document.getElementById('pagination'),
        leaveDescription: document.getElementById('leave-table-description'),

        pendingTab: document.getElementById('pending-tab'),
        historyTab: document.getElementById('history-tab'),
        pendingCount: document.getElementById('pending-count'),
        historyCount: document.getElementById('history-count'),

        createButton: document.getElementById('create-btn'),
        editButton: document.getElementById('edit-leave-btn'),
        deleteButton: document.getElementById('delete-leave-btn'),
        approveButton: document.getElementById('approve-leave-btn'),

        modal: document.getElementById('leave-modal'),
        form: document.getElementById('leave-form'),
        modalTitle: document.getElementById('leave-modal-title'),
        modalDescription: document.getElementById('leave-modal-description'),
        modalMessage: document.getElementById('leave-modal-message'),
        modalClose: document.getElementById('leave-modal-close'),
        modalCancel: document.getElementById('leave-modal-cancel'),
        modalSubmit: document.getElementById('leave-modal-submit'),

        leaveId: document.getElementById('leave-id'),
        leaveEmployeeCode: document.getElementById('leave-employee-code'),
        leaveFromDate: document.getElementById('leave-from-date'),
        leaveToDate: document.getElementById('leave-to-date'),
        leaveType: document.getElementById('leave-type'),
        leaveReason: document.getElementById('leave-reason'),
    };

    if (!elements.employeeTbody || !elements.leaveTbody) {
        return;
    }

    const state = {
        /*
         * Paging + filter của danh sách nhân viên.
         * Các field này map trực tiếp vào:
         *
         * sp_nhan_vien_danh_sach_phan_trang(
         *     p_tu_khoa,
         *     p_ma_pb,
         *     p_ma_cv,
         *     p_page,
         *     p_per_page
         * )
         */
        employeePage: 1,
        employeePerPage: 15,

        employeeFilters: {
            tu_khoa: null,
            ma_pb: null,
            ma_cv: null,
        },

        selectedEmployee: null,

        leaveRows: [],
        activeTab: 'pending',
        selectedLeaveId: null,

        employeeAbortController: null,
        leaveAbortController: null,

        modalMode: 'create',
    };

    let searchTimeout = null;

    function escapeHtml(value) {
        return String(value ?? '')
            .replaceAll('&', '&amp;')
            .replaceAll('<', '&lt;')
            .replaceAll('>', '&gt;')
            .replaceAll('"', '&quot;')
            .replaceAll("'", '&#039;');
    }

    function extractData(result) {
        if (Array.isArray(result)) return result;
        if (Array.isArray(result?.data)) return result.data;
        if (Array.isArray(result?.data?.data)) return result.data.data;
        return [];
    }

    function formatDate(value) {
        if (!value) return '—';

        const match = String(value).match(/^(\d{4})-(\d{2})-(\d{2})/);
        if (!match) return escapeHtml(value);

        return `${match[3]}/${match[2]}/${match[1]}`;
    }

    function genderLabel(value) {
        return value;
    }

    function normalizeEmployee(employee) {
        return {
            ma_nv: employee.ma_nv ?? employee.nhan_vien?.ma_nv ?? '',
            ho_ten:
                employee.ho_ten ??
                employee.ten_nv ??
                employee.nhan_vien?.ho_ten ??
                employee.nhan_vien?.ten_nv ??
                'Chưa cập nhật',
            gioi_tinh: employee.gioi_tinh ?? employee.nhan_vien?.gioi_tinh,
            sdt: employee.sdt ?? employee.nhan_vien?.sdt ?? '—',
            email: employee.email ?? employee.nhan_vien?.email ?? '—',
            ma_pb: employee.ma_pb ?? employee.nhan_vien?.ma_pb ?? null,
            ten_pb:
                employee.ten_pb ??
                employee.phong_ban ??
                employee.nhan_vien?.ten_pb ??
                employee.nhan_vien?.phong_ban ??
                '—',
            ma_cv: employee.ma_cv ?? employee.nhan_vien?.ma_cv ?? null,
            ten_cv:
                employee.ten_cv ??
                employee.chuc_vu ??
                employee.nhan_vien?.ten_cv ??
                employee.nhan_vien?.chuc_vu ??
                '—',
            ten_tt:
                employee.ten_tt ??
                employee.trang_thai ??
                employee.nhan_vien?.ten_tt ??
                'Đang làm việc',
        };
    }

    function normalizeLeave(leave) {
        return {
            ma_np: leave.ma_np,
            ma_nv: leave.ma_nv ?? state.selectedEmployee?.ma_nv ?? '',
            tu_ngay: leave.tu_ngay,
            den_ngay: leave.den_ngay,
            ma_lp: leave.ma_lp ?? leave.loai_phep?.ma_lp ?? null,
            ten_lp: leave.ten_lp ?? leave.loai_phep?.ten_lp ?? 'Nghỉ phép',
            ly_do: leave.ly_do ?? '',
            trang_thai_duyet: Number(leave.trang_thai_duyet ?? 0),
        };
    }

    async function requestJson(url, options = {}) {
        const response = await fetch(url, {
            headers: {
                Accept: 'application/json',
                'Content-Type': 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
                ...(options.headers || {}),
            },
            ...options,
        });

        const contentType = response.headers.get('content-type') || '';

        if (!contentType.includes('application/json')) {
            const text = await response.text();
            console.error('API trả về HTML/text:', text);
            throw new Error(`API không trả về JSON. HTTP ${response.status}`);
        }

        const result = await response.json();

        if (!response.ok || result.success === false) {
            const validationMessage = result.errors
                ? Object.values(result.errors).flat().join(' ')
                : null;

            throw new Error(
                validationMessage ||
                result.message ||
                `Request thất bại. HTTP ${response.status}`
            );
        }

        return result;
    }

    async function loadSimpleSelect(url, select, valueField, textField) {
        if (!select) return;

        const firstOption =
            select.options[0]?.outerHTML ??
            '<option value="">-- Tất cả --</option>';

        select.innerHTML = firstOption;

        try {
            const result = await requestJson(url);
            const rows = extractData(result);
            const fragment = document.createDocumentFragment();

            rows.forEach((row) => {
                if (row[valueField] === null || row[valueField] === undefined) {
                    return;
                }

                const option = document.createElement('option');
                option.value = String(row[valueField]);
                option.textContent = row[textField] ?? String(row[valueField]);
                fragment.appendChild(option);
            });

            select.appendChild(fragment);
        } catch (error) {
            console.error(`Không tải được lookup ${url}:`, error);
        }
    }

    async function loadLookups() {
        await Promise.all([
            loadSimpleSelect(
                PHONG_BAN_API_URL,
                elements.department,
                'ma_pb',
                'ten_pb'
            ),
            loadSimpleSelect(
                CHUC_VU_API_URL,
                elements.position,
                'ma_cv',
                'ten_cv'
            ),
            loadSimpleSelect(
                LOAI_PHEP_API_URL,
                elements.leaveType,
                'ma_lp',
                'ten_lp'
            ),
        ]);
    }

    /**
     * Đồng bộ filter trên UI vào state.
     */
    function syncEmployeeFiltersFromUI() {
        state.employeeFilters = {
            tu_khoa:
                elements.search?.value.trim() || null,

            ma_pb:
                elements.department?.value || null,

            ma_cv:
                elements.position?.value || null,
        };

        return {
            ...state.employeeFilters,
        };
    }

    /**
     * Lấy filter hiện tại để gửi xuống API.
     *
     * API Laravel sẽ map các query parameter này
     * vào stored procedure:
     *
     * CALL sp_nhan_vien_danh_sach_phan_trang(
     *     tu_khoa,
     *     ma_pb,
     *     ma_cv,
     *     page,
     *     per_page
     * )
     */
    function getEmployeeFilters(page = state.employeePage) {
        return {
            ...state.employeeFilters,
            page,
            per_page: state.employeePerPage,
        };
    }

    /**
     * Tạo URL danh sách nhân viên có filter + pagination.
     */
    function buildEmployeeUrl(page = state.employeePage) {
        const url = new URL(
            NHAN_VIEN_API_URL,
            window.location.origin
        );

        const filters =
            getEmployeeFilters(page);

        Object.entries(filters)
            .forEach(([key, value]) => {
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
            });

        return url.toString();
    }

    /**
     * Mỗi khi filter thay đổi:
     * - sync filter
     * - quay về page 1
     * - gọi lại API
     */
    function applyEmployeeFilters() {
        syncEmployeeFiltersFromUI();

        state.employeePage = 1;

        loadEmployees(1);
    }

    function renderEmployeeLoading() {
        elements.employeeTbody.innerHTML = `
            <tr>
                <td colspan="9" class="text-center text-secondary py-5">
                    <span class="spinner-border spinner-border-sm me-2" aria-hidden="true"></span>
                    Đang tải danh sách nhân viên...
                </td>
            </tr>
        `;
    }

    function renderEmployeeRows(rows) {
        if (!rows.length) {
            elements.employeeTbody.innerHTML = `
                <tr>
                    <td colspan="9" class="text-center text-secondary py-5">
                        Không tìm thấy nhân viên phù hợp.
                    </td>
                </tr>
            `;
            return;
        }

        elements.employeeTbody.innerHTML = rows
            .map((rawEmployee) => {
                const employee = normalizeEmployee(rawEmployee);
                const selected =
                    state.selectedEmployee?.ma_nv === employee.ma_nv;

                return `
                    <tr
                        data-employee-row
                        data-ma-nv="${escapeHtml(employee.ma_nv)}"
                        class="${selected ? 'table-primary' : ''}"
                        style="cursor:pointer;"
                    >
                        <td>
                            <input
                                class="form-check-input employee-radio"
                                type="radio"
                                name="selected-employee"
                                value="${escapeHtml(employee.ma_nv)}"
                                ${selected ? 'checked' : ''}
                                aria-label="Chọn ${escapeHtml(employee.ho_ten)}"
                            >
                        </td>
                        <td class="fw-semibold">${escapeHtml(employee.ma_nv)}</td>
                        <td>${escapeHtml(employee.ho_ten)}</td>
                        <td>${genderLabel(employee.gioi_tinh)}</td>
                        <td>${escapeHtml(employee.sdt)}</td>
                        <td>${escapeHtml(employee.email)}</td>
                        <td>${escapeHtml(employee.ten_pb)}</td>
                        <td>${escapeHtml(employee.ten_cv)}</td>
                        <td>
                            <span class="badge text-bg-light border fw-normal">
                                ${escapeHtml(employee.ten_tt)}
                            </span>
                        </td>
                    </tr>
                `;
            })
            .join('');
    }

    /**
     * Tạo danh sách page hiển thị gọn:
     * 1 ... 4 5 6 ... 20
     */
    function getPageItems(
        currentPage,
        lastPage
    ) {
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

        const validPages = [...pages]
            .filter(
                (page) =>
                    page >= 1 &&
                    page <= lastPage
            )
            .sort((a, b) => a - b);

        const items = [];

        validPages.forEach(
            (page, index) => {
                const previous =
                    validPages[index - 1];

                if (
                    previous !== undefined &&
                    page - previous > 1
                ) {
                    items.push('ellipsis');
                }

                items.push(page);
            }
        );

        return items;
    }

    /**
     * Render pagination cho bảng nhân viên.
     */
    function renderEmployeePagination(meta) {
        if (!elements.employeePagination) {
            return;
        }

        const currentPage =
            Number(meta?.current_page || 1);

        const lastPage =
            Number(meta?.last_page || 1);

        elements.employeePagination.innerHTML = '';

        if (lastPage <= 1) {
            return;
        }

        const wrapper =
            document.createElement('div');

        wrapper.className =
            'btn-group btn-group-sm';

        const previousButton =
            document.createElement('button');

        previousButton.type = 'button';
        previousButton.className =
            'btn btn-outline-secondary';

        previousButton.textContent = '‹';
        previousButton.disabled =
            currentPage <= 1;

        previousButton.dataset.page =
            String(currentPage - 1);

        wrapper.appendChild(
            previousButton
        );

        const pageItems =
            getPageItems(
                currentPage,
                lastPage
            );

        pageItems.forEach((item) => {
            if (item === 'ellipsis') {
                const ellipsis =
                    document.createElement(
                        'span'
                    );

                ellipsis.className =
                    'btn btn-outline-secondary disabled';

                ellipsis.textContent = '…';

                wrapper.appendChild(
                    ellipsis
                );

                return;
            }

            const button =
                document.createElement(
                    'button'
                );

            button.type = 'button';

            button.className =
                item === currentPage
                    ? 'btn btn-primary'
                    : 'btn btn-outline-secondary';

            button.textContent =
                String(item);

            button.dataset.page =
                String(item);

            wrapper.appendChild(button);
        });

        const nextButton =
            document.createElement('button');

        nextButton.type = 'button';
        nextButton.className =
            'btn btn-outline-secondary';

        nextButton.textContent = '›';
        nextButton.disabled =
            currentPage >= lastPage;

        nextButton.dataset.page =
            String(currentPage + 1);

        wrapper.appendChild(nextButton);

        elements.employeePagination.appendChild(
            wrapper
        );
    }

    /**
     * Render dòng:
     * Hiển thị 1–15 trên 128 nhân viên
     */
    function renderEmployeePaginationInfo(
        meta = {}
    ) {
        if (!elements.employeePageInfo) {
            return;
        }

        const total =
            Number(meta.total || 0);

        const from =
            meta.from ?? 0;

        const to =
            meta.to ?? 0;

        elements.employeePageInfo.textContent =
            total > 0
                ? `Hiển thị ${from}–${to} trên ${total} nhân viên`
                : 'Hiển thị 0 trên 0 nhân viên';
    }

    async function loadEmployees(page = 1) {
        state.employeePage =
            Math.max(Number(page) || 1, 1);

        state.employeeAbortController
            ?.abort();

        state.employeeAbortController =
            new AbortController();

        renderEmployeeLoading();

        /*
         * Nếu loadEmployees được gọi trực tiếp
         * sau khi người dùng thay filter,
         * luôn đảm bảo state chứa filter mới nhất.
         */
        syncEmployeeFiltersFromUI();

        try {
            const requestUrl =
                buildEmployeeUrl(
                    state.employeePage
                );

            console.log(
                'Employee paging API:',
                requestUrl
            );

            const response = await fetch(
                requestUrl,
                {
                    method: 'GET',
                    headers: {
                        Accept:
                            'application/json',
                        'X-Requested-With':
                            'XMLHttpRequest',
                    },
                    signal:
                    state
                        .employeeAbortController
                        .signal,
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
                const body =
                    await response.text();

                console.error(
                    'Employee API trả HTML/text:',
                    body
                );

                throw new Error(
                    `API nhân viên không trả JSON. HTTP ${response.status}`
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
                    'Không thể tải nhân viên.'
                );
            }

            /*
             * Expected response:
             *
             * {
             *   success: true,
             *   data: {
             *      current_page: 1,
             *      data: [...],
             *      from: 1,
             *      to: 15,
             *      last_page: 9,
             *      per_page: 15,
             *      total: 128
             *   }
             * }
             */
            const paginator =
                result.data;

            const isPaginated =
                paginator !== null &&
                typeof paginator === 'object' &&
                !Array.isArray(paginator) &&
                Array.isArray(
                    paginator.data
                );

            const rows = isPaginated
                ? paginator.data
                : Array.isArray(paginator)
                    ? paginator
                    : [];

            renderEmployeeRows(rows);

            if (isPaginated) {
                state.employeePage =
                    Number(
                        paginator.current_page ||
                        state.employeePage
                    );

                state.employeePerPage =
                    Number(
                        paginator.per_page ||
                        state.employeePerPage
                    );

                renderEmployeePaginationInfo(
                    paginator
                );

                renderEmployeePagination(
                    paginator
                );
            } else {
                /*
                 * Fallback khi backend chưa wrap
                 * LengthAwarePaginator.
                 */
                elements.employeePageInfo.textContent =
                    `Hiển thị ${rows.length} trên ${rows.length} nhân viên`;

                if (
                    elements.employeePagination
                ) {
                    elements.employeePagination.innerHTML =
                        '';
                }
            }
        } catch (error) {
            if (
                error.name === 'AbortError'
            ) {
                return;
            }

            console.error(
                'Error loading employees:',
                error
            );

            elements.employeeTbody.innerHTML = `
                <tr>
                    <td
                        colspan="9"
                        class="text-center text-danger py-5"
                    >
                        ${escapeHtml(error.message)}
                    </td>
                </tr>
            `;

            if (
                elements.employeePageInfo
            ) {
                elements.employeePageInfo.textContent =
                    'Hiển thị 0 trên 0 nhân viên';
            }

            if (
                elements.employeePagination
            ) {
                elements.employeePagination.innerHTML =
                    '';
            }
        }
    }

    function findEmployeeFromRenderedRow(row) {
        if (!row) return null;

        const maNv = row.dataset.maNv;

        return {
            ma_nv: maNv,
            ho_ten: row.children[2]?.textContent?.trim() || maNv,
            gioi_tinh: row.children[3]?.textContent?.trim(),
            sdt: row.children[4]?.textContent?.trim(),
            email: row.children[5]?.textContent?.trim(),
            ten_pb: row.children[6]?.textContent?.trim(),
            ten_cv: row.children[7]?.textContent?.trim(),
            ten_tt: row.children[8]?.textContent?.trim(),
        };
    }

    function selectEmployee(employee) {
        if (!employee) return;

        state.selectedEmployee = employee;
        state.selectedLeaveId = null;

        elements.selectedEmployeeBadge.textContent =
            `${employee.ma_nv} · ${employee.ho_ten}`;

        elements.leaveDescription.textContent =
            `Dữ liệu nghỉ phép của ${employee.ho_ten} (${employee.ma_nv}).`;

        elements.createButton.disabled = false;
        elements.editButton.disabled = true;
        elements.deleteButton.disabled = true;
        elements.approveButton.disabled = true;

        elements.employeeTbody
            .querySelectorAll('[data-employee-row]')
            .forEach((row) => {
                const selected = row.dataset.maNv === employee.ma_nv;
                row.classList.toggle('table-primary', selected);

                const radio = row.querySelector('.employee-radio');
                if (radio) radio.checked = selected;
            });

        loadLeaves();
    }

    function leaveStatusLabel(status) {
        if (status === 0) {
            return '<span class="badge rounded-pill text-bg-warning">Chờ duyệt</span>';
        }

        if (status === 1) {
            return '<span class="badge rounded-pill text-bg-success">Đã duyệt</span>';
        }

        return '<span class="badge rounded-pill text-bg-danger">Từ chối</span>';
    }

    function filterLeavesForActiveTab(rows) {
        if (state.activeTab === 'pending') {
            return rows.filter((item) => item.trang_thai_duyet === 0);
        }

        return rows.filter((item) => item.trang_thai_duyet !== 0);
    }

    function updateLeaveCounts(rows) {
        const pending = rows.filter(
            (item) => item.trang_thai_duyet === 0
        ).length;

        const history = rows.length - pending;

        elements.pendingCount.textContent = pending;
        elements.historyCount.textContent = history;

        const approved = rows.filter(
            (item) => item.trang_thai_duyet === 1
        ).length;

        const oldApprovedCount =
            document.getElementById('approved-count');

        if (oldApprovedCount) {
            oldApprovedCount.textContent = approved;
        }
    }

    function renderLeaves() {
        const rows = filterLeavesForActiveTab(state.leaveRows);

        state.selectedLeaveId = null;
        elements.editButton.disabled = true;
        elements.deleteButton.disabled = true;
        elements.approveButton.disabled = true;

        if (!rows.length) {
            elements.leaveTbody.innerHTML = `
                <tr>
                    <td colspan="8" class="text-center text-secondary py-5">
                        ${
                state.activeTab === 'pending'
                    ? 'Không có đơn nghỉ phép chờ duyệt.'
                    : 'Chưa có lịch sử nghỉ phép.'
            }
                    </td>
                </tr>
            `;

            elements.pageInfo.textContent = 'Hiển thị 0 trên 0 yêu cầu';
            return;
        }

        elements.leaveTbody.innerHTML = rows
            .map((leave) => `
                <tr
                    data-leave-row
                    data-id="${escapeHtml(leave.ma_np)}"
                    style="cursor:pointer;"
                >
                    <td>
                        <input
                            class="form-check-input leave-radio"
                            type="radio"
                            name="selected-leave"
                            value="${escapeHtml(leave.ma_np)}"
                        >
                    </td>
                    <td class="fw-semibold">${escapeHtml(leave.ma_np)}</td>
                    <td>${escapeHtml(leave.ma_nv)}</td>
                    <td>${formatDate(leave.tu_ngay)}</td>
                    <td>${formatDate(leave.den_ngay)}</td>
                    <td>${escapeHtml(leave.ten_lp)}</td>
                    <td>${escapeHtml(leave.ly_do || '—')}</td>
                    <td>${leaveStatusLabel(leave.trang_thai_duyet)}</td>
                </tr>
            `)
            .join('');

        elements.pageInfo.textContent =
            `Hiển thị 1–${rows.length} trên ${rows.length} yêu cầu`;
    }

    async function loadLeaves() {
        if (!state.selectedEmployee?.ma_nv) return;

        state.leaveAbortController?.abort();
        state.leaveAbortController = new AbortController();

        elements.leaveTbody.innerHTML = `
            <tr>
                <td colspan="8" class="text-center text-secondary py-5">
                    <span class="spinner-border spinner-border-sm me-2" aria-hidden="true"></span>
                    Đang tải dữ liệu nghỉ phép...
                </td>
            </tr>
        `;

        try {
            const url = new URL(
                NGHI_PHEP_API_URL,
                window.location.origin
            );

            url.searchParams.set(
                'ma_nv',
                state.selectedEmployee.ma_nv
            );

            const response = await fetch(url.toString(), {
                headers: {
                    Accept: 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                },
                signal: state.leaveAbortController.signal,
            });

            const contentType =
                response.headers.get('content-type') || '';

            if (!contentType.includes('application/json')) {
                throw new Error(
                    `API nghỉ phép không trả JSON. HTTP ${response.status}`
                );
            }

            const result = await response.json();

            if (!response.ok || result.success === false) {
                throw new Error(
                    result.message ||
                    'Không thể tải dữ liệu nghỉ phép.'
                );
            }

            state.leaveRows = extractData(result)
                .map(normalizeLeave);

            updateLeaveCounts(state.leaveRows);
            renderLeaves();
        } catch (error) {
            if (error.name === 'AbortError') return;

            console.error('Error loading leave data:', error);

            elements.leaveTbody.innerHTML = `
                <tr>
                    <td colspan="8" class="text-center text-danger py-5">
                        ${escapeHtml(error.message)}
                    </td>
                </tr>
            `;
        }
    }

    function selectLeave(leaveId) {
        state.selectedLeaveId = String(leaveId);

        const leave = state.leaveRows.find(
            (item) => String(item.ma_np) === state.selectedLeaveId
        );

        elements.leaveTbody
            .querySelectorAll('[data-leave-row]')
            .forEach((row) => {
                const selected =
                    row.dataset.id === state.selectedLeaveId;

                row.classList.toggle(
                    'table-primary',
                    selected
                );

                const radio =
                    row.querySelector('.leave-radio');

                if (radio) radio.checked = selected;
            });

        elements.editButton.disabled = !leave;
        elements.deleteButton.disabled = !leave;
        elements.approveButton.disabled =
            !leave || leave.trang_thai_duyet !== 0;
    }

    function switchTab(tab) {
        state.activeTab = tab;
        state.selectedLeaveId = null;

        elements.pendingTab.classList.toggle(
            'active',
            tab === 'pending'
        );

        elements.pendingTab.setAttribute(
            'aria-selected',
            tab === 'pending' ? 'true' : 'false'
        );

        elements.historyTab.classList.toggle(
            'active',
            tab === 'history'
        );

        elements.historyTab.setAttribute(
            'aria-selected',
            tab === 'history' ? 'true' : 'false'
        );

        renderLeaves();
    }

    function showModalMessage(message) {
        elements.modalMessage.textContent = message;
        elements.modalMessage.hidden = false;
    }

    function clearModalMessage() {
        elements.modalMessage.textContent = '';
        elements.modalMessage.hidden = true;
    }

    function closeModal() {
        if (elements.modal?.open) {
            elements.modal.close();
        }

        clearModalMessage();
    }

    function resetModal() {
        elements.form.reset();
        elements.leaveId.value = '';
        elements.leaveEmployeeCode.value =
            state.selectedEmployee?.ma_nv || '';

        clearModalMessage();
    }

    function openCreateModal() {
        if (!state.selectedEmployee) return;

        state.modalMode = 'create';
        resetModal();

        elements.modalTitle.textContent =
            'Thêm nghỉ phép';

        elements.modalDescription.textContent =
            `Tạo đơn nghỉ phép cho ${state.selectedEmployee.ho_ten} (${state.selectedEmployee.ma_nv}).`;

        elements.modalSubmit.textContent =
            'Thêm nghỉ phép';

        elements.modal.showModal();
    }

    function openEditModal() {
        const leave = state.leaveRows.find(
            (item) =>
                String(item.ma_np) ===
                String(state.selectedLeaveId)
        );

        if (!leave) return;

        state.modalMode = 'edit';
        resetModal();

        elements.leaveId.value = leave.ma_np ?? '';
        elements.leaveEmployeeCode.value =
            leave.ma_nv ??
            state.selectedEmployee?.ma_nv ??
            '';

        elements.leaveFromDate.value =
            formatDate(leave.tu_ngay) ?? '';

        elements.leaveToDate.value =
            formatDate(leave.den_ngay) ?? '';

        elements.leaveType.value =
            leave.ma_lp ?? '';

        elements.leaveReason.value =
            leave.ly_do ?? '';

        elements.modalTitle.textContent =
            'Sửa nghỉ phép';

        elements.modalDescription.textContent =
            `Cập nhật đơn nghỉ phép #${leave.ma_np}.`;

        elements.modalSubmit.textContent =
            'Lưu thay đổi';

        elements.modal.showModal();
    }

    function buildLeavePayload() {
        return {
            ma_nv:
                elements.leaveEmployeeCode.value.trim(),
            tu_ngay:
            elements.leaveFromDate.value,
            den_ngay:
            elements.leaveToDate.value,
            ma_lp:
                elements.leaveType.value || null,
            ly_do:
                elements.leaveReason.value.trim() || null,
            trang_thai_duyet: 0,
        };
    }

    function validateLeavePayload(payload) {
        if (!payload.ma_nv) return 'Thiếu mã nhân viên.';
        if (!payload.tu_ngay) return 'Từ ngày không được để trống.';
        if (!payload.den_ngay) return 'Đến ngày không được để trống.';

        if (payload.den_ngay < payload.tu_ngay) {
            return 'Đến ngày không được nhỏ hơn từ ngày.';
        }

        if (!payload.ma_lp) {
            return 'Vui lòng chọn loại phép.';
        }

        return null;
    }

    async function submitLeaveForm(event) {
        event.preventDefault();

        const payload = buildLeavePayload();
        const validationMessage =
            validateLeavePayload(payload);

        if (validationMessage) {
            showModalMessage(validationMessage);
            return;
        }

        const isEdit =
            state.modalMode === 'edit';

        const leaveId =
            elements.leaveId.value;

        elements.modalSubmit.disabled = true;
        elements.modalSubmit.textContent =
            isEdit ? 'Đang lưu...' : 'Đang thêm...';

        try {
            await requestJson(
                isEdit
                    ? `${NGHI_PHEP_API_URL}/${encodeURIComponent(leaveId)}`
                    : NGHI_PHEP_API_URL,
                {
                    method: isEdit ? 'PUT' : 'POST',
                    body: JSON.stringify(payload),
                }
            );

            closeModal();
            await loadLeaves();
        } catch (error) {
            console.error(error);
            showModalMessage(error.message);
        } finally {
            elements.modalSubmit.disabled = false;
            elements.modalSubmit.textContent =
                isEdit ? 'Lưu thay đổi' : 'Thêm nghỉ phép';
        }
    }

    async function deleteSelectedLeave() {
        if (!state.selectedLeaveId) return;

        if (
            !window.confirm(
                'Bạn có chắc muốn xóa đơn nghỉ phép này không?'
            )
        ) {
            return;
        }

        try {
            await requestJson(
                `${NGHI_PHEP_API_URL}/${encodeURIComponent(
                    state.selectedLeaveId
                )}`,
                { method: 'DELETE' }
            );

            await loadLeaves();
        } catch (error) {
            console.error(error);
            window.alert(error.message);
        }
    }

    async function approveSelectedLeave() {
        if (
            !state.selectedLeaveId ||
            !state.selectedEmployee
        ) {
            return;
        }

        try {
            await requestJson(
                `${NGHI_PHEP_API_URL}/${encodeURIComponent(
                    state.selectedLeaveId
                )}/duyet`,
                {
                    method: 'PATCH',
                    body: JSON.stringify({
                        ma_nv:
                        state.selectedEmployee.ma_nv,
                        trang_thai_duyet: 1,
                    }),
                }
            );

            await loadLeaves();
        } catch (error) {
            console.error(error);
            window.alert(error.message);
        }
    }

    function clearEmployeeFilters() {
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

        state.employeeFilters = {
            tu_khoa: null,
            ma_pb: null,
            ma_cv: null,
        };

        state.employeePage = 1;

        loadEmployees(1);
    }

    elements.employeeTbody.addEventListener(
        'click',
        (event) => {
            const row = event.target.closest(
                '[data-employee-row]'
            );

            if (!row) return;

            selectEmployee(
                findEmployeeFromRenderedRow(row)
            );
        }
    );

    elements.leaveTbody.addEventListener(
        'click',
        (event) => {
            const row = event.target.closest(
                '[data-leave-row]'
            );

            if (!row) return;

            selectLeave(row.dataset.id);
        }
    );

    /*
     * Search server-side có debounce.
     */
    elements.search?.addEventListener(
        'input',
        () => {
            clearTimeout(searchTimeout);

            searchTimeout = setTimeout(
                applyEmployeeFilters,
                350
            );
        }
    );

    /*
     * Enter => search ngay.
     */
    elements.search?.addEventListener(
        'keydown',
        (event) => {
            if (
                event.key !== 'Enter'
            ) {
                return;
            }

            event.preventDefault();

            clearTimeout(
                searchTimeout
            );

            applyEmployeeFilters();
        }
    );

    /*
     * Filter phòng ban / chức vụ
     * đều server-side và quay về page 1.
     */
    elements.department?.addEventListener(
        'change',
        applyEmployeeFilters
    );

    elements.position?.addEventListener(
        'change',
        applyEmployeeFilters
    );

    elements.clearFilterButton?.addEventListener(
        'click',
        clearEmployeeFilters
    );

    /*
     * Event delegation pagination nhân viên.
     */
    elements.employeePagination?.addEventListener(
        'click',
        (event) => {
            const button =
                event.target.closest(
                    'button[data-page]'
                );

            if (
                !button ||
                button.disabled
            ) {
                return;
            }

            const page =
                Number(
                    button.dataset.page
                );

            if (
                !Number.isInteger(page) ||
                page < 1
            ) {
                return;
            }

            loadEmployees(page);
        }
    );

    elements.pendingTab?.addEventListener(
        'click',
        () => switchTab('pending')
    );

    elements.historyTab?.addEventListener(
        'click',
        () => switchTab('history')
    );

    elements.createButton?.addEventListener(
        'click',
        openCreateModal
    );

    elements.editButton?.addEventListener(
        'click',
        openEditModal
    );

    elements.deleteButton?.addEventListener(
        'click',
        deleteSelectedLeave
    );

    elements.approveButton?.addEventListener(
        'click',
        approveSelectedLeave
    );

    elements.form?.addEventListener(
        'submit',
        submitLeaveForm
    );

    elements.modalClose?.addEventListener(
        'click',
        closeModal
    );

    elements.modalCancel?.addEventListener(
        'click',
        closeModal
    );

    elements.modal?.addEventListener(
        'click',
        (event) => {
            if (event.target === elements.modal) {
                closeModal();
            }
        }
    );

    async function initialize() {
        await loadLookups();

        /*
         * Khởi tạo filter state trước request đầu tiên.
         */
        syncEmployeeFiltersFromUI();

        await loadEmployees(1);
    }

    initialize();
});
