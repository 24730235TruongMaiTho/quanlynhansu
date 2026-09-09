import '../../../css/nghi-phep/nghiphep-modal.css'
import {
    extractData,
    genderLabel,
    normalizeEmployee,
} from './employee-response.js';
import { renderSharedPagination } from '../shared/pagination.js';
import { createDeleteAction } from '../shared/delete-action.js';
import { canonicalServerDate, formatDisplayDate, toIsoDate } from '../shared/date-field.js';

document.addEventListener('DOMContentLoaded', () => {
    const AUTH_ME_API_URL = '/api/v1/auth/me';

    const NGHI_PHEP_API_URL = '/api/v1/nghi-phep';
    const NHAN_VIEN_API_URL = '/api/v1/nghi-phep/nhan-vien';
    const PHONG_BAN_API_URL = '/api/v1/nghi-phep/phong-ban';
    const CHUC_VU_API_URL = '/api/v1/nghi-phep/chuc-vu';
    const LOAI_PHEP_API_URL = '/api/v1/nghi-phep/loai-phep';

    const PERMISSION_CODES = Object.freeze({
        READ: 'NghiPhep.Read',
        INSERT: 'NghiPhep.Insert',
        UPDATE: 'NghiPhep.Update',
        DELETE: 'NghiPhep.Delete',
        APPROVE: 'NghiPhep.Approve',
    });

    const permissionState = {
        initialized: false,
        user: null,
        permissions: new Set(),
    };

    function can(permission) {
        return permissionState.permissions.has(permission);
    }

    function canAny(...permissions) {
        return permissions.some(
            (permission) => can(permission)
        );
    }

    function canApproveLeaves() {
        return can(PERMISSION_CODES.APPROVE)
            && document.querySelector('.leave-page')?.dataset.nghiPhepCanApprove === '1';
    }

    function normalizeAuthResult(result) {
        const data = result?.data || {};

        if (
            data.user ||
            Array.isArray(data.permissions)
        ) {
            return {
                user: data.user || null,
                permissions: Array.isArray(data.permissions)
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

            permissions: Array.isArray(data.quyen)
                ? data.quyen
                    .map(
                        (item) =>
                            item?.ky_hieu_quyen
                    )
                    .filter(
                        (item) =>
                            typeof item === 'string'
                    )
                : [],
        };
    }

    async function loadAuthContext() {
        const response = await fetch(
            AUTH_ME_API_URL,
            {
                method: 'GET',

                headers: {
                    Accept: 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                },

                credentials: 'same-origin',
            }
        );

        const contentType =
            response.headers.get('content-type') || '';

        if (
            !contentType.includes('application/json')
        ) {
            const body = await response.text();

            console.error(
                'Auth API trả HTML/text:',
                body
            );

            throw new Error(
                `API xác thực không trả JSON. HTTP ${response.status}`
            );
        }

        const result = await response.json();

        if (
            !response.ok ||
            result.success === false
        ) {
            throw new Error(
                result.message ||
                'Không thể xác thực người dùng.'
            );
        }

        const normalized =
            normalizeAuthResult(result);

        permissionState.user =
            normalized.user;

        permissionState.permissions =
            new Set(
                normalized.permissions
            );

        permissionState.initialized =
            true;

        return normalized;
    }

    function applyPermissionVisibility(
        root = document
    ) {
        root
            .querySelectorAll(
                '[data-leave-permission]'
            )
            .forEach((element) => {
                const required = String(
                    element.dataset.leavePermission || ''
                )
                    .split(',')
                    .map(
                        (item) => item.trim()
                    )
                    .filter(Boolean);

                const allowed =
                    required.length === 0 ||
                    required.some(
                        (permission) =>
                            can(permission)
                    );

                element.hidden = !allowed;

                element.classList.toggle(
                    'd-none',
                    !allowed
                );
            });
    }

    function notifyDenied(
        action = 'thực hiện thao tác này'
    ) {
        const message = action === 'xóa đơn nghỉ phép'
            ? 'Bạn không có quyền xóa đơn nghỉ phép.'
            : `Bạn không có quyền ${action}.`;

        const toast =
            document.querySelector('.leave-toast');

        if (toast) {
            toast.textContent = message;
            toast.classList.add('show');

            window.setTimeout(
                () => {
                    toast.classList.remove('show');
                },
                2500
            );

            return;
        }

        window.alert(message);
    }

    function guard(permission, action) {
        if (can(permission)) {
            return true;
        }

        notifyDenied(action);
        return false;
    }

    function csrfToken() {
        return document
            .querySelector(
                'meta[name="csrf-token"]'
            )
            ?.getAttribute('content') || null;
    }


    const elements = {
        authLoading:
            document.getElementById('leave-auth-loading'),

        accessDenied:
            document.getElementById('leave-access-denied'),

        accessDeniedMessage:
            document.getElementById('leave-access-denied-message'),

        readOnlyBadge:
            document.getElementById('leave-readonly-badge'),

        noReadNotice:
            document.getElementById('leave-no-read-notice'),

        search: document.getElementById('search-field'),
        filterForm: document.getElementById('leave-filter-form'),
        department: document.getElementById('department-filter'),
        position: document.getElementById('position-filter'),
        clearFilterButton: document.getElementById('clear-filter-btn'),
        historyFilterForm: document.getElementById('leave-history-filter-form'),
        historyFromDate: document.getElementById('history-from-date'),
        historyToDate: document.getElementById('history-to-date'),
        historyFromDateError: document.getElementById('history-from-date-error'),
        historyToDateError: document.getElementById('history-to-date-error'),
        allLeavesButton: document.getElementById('all-leaves-btn'),

        employeeTbody: document.getElementById('employee-tbody'),
        employeePageInfo: document.getElementById('employee-page-info'),
        employeePagination: document.getElementById('employee-pagination'),
        selectedEmployeeBadge: document.getElementById('selected-employee-badge'),

        leaveTbody: document.getElementById('leave-tbody'),
        pageInfo: document.getElementById('page-info'),
        pagination: document.getElementById('pagination'),
        leavePerPage: document.getElementById('leave-per-page'),
        leaveDescription: document.getElementById('leave-table-description'),

        pendingTab: document.getElementById('pending-tab'),
        historyTab: document.getElementById('history-tab'),
        pendingCount: document.getElementById('pending-count'),
        historyCount: document.getElementById('history-count'),

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
        leaveFromDateError: document.getElementById('leave-from-date-error'),
        leaveToDateError: document.getElementById('leave-to-date-error'),
        leaveType: document.getElementById('leave-type'),
        leaveReason: document.getElementById('leave-reason'),
    };

    if (!elements.employeeTbody || !elements.leaveTbody) {
        return;
    }

    const state = {
        /*
         * Paging + filter của danh sách nhân viên.
         * Các field này map vào API Laravel; repository dùng projection
         * Query Builder trên hợp đồng 15 bảng.
         */
        employeePage: 1,
        employeePerPage: 15,

        employeeFilters: {
            tu_khoa: null,
            ma_pb: null,
            ma_cv: null,
        },

        selectedEmployee: null,

        pendingRows: [],
        historyRows: [],
        pendingPaginator: null,
        historyPaginator: null,
        counts: { pending: null, history: null },

        activeTab: 'pending',
        historyFilters: {
            tu_ngay: elements.historyFromDate?.value || null,
            den_ngay: elements.historyToDate?.value || null,
        },

        leavePage: 1,
        leavePerPage:
            Number(
                document.getElementById('leave-per-page')?.value || 10
            ),

        employeeAbortController: null,
        pendingAbortController: null,
        historyAbortController: null,

        modalMode: 'edit',
    };

    function escapeHtml(value) {
        return String(value ?? '')
            .replaceAll('&', '&amp;')
            .replaceAll('<', '&lt;')
            .replaceAll('>', '&gt;')
            .replaceAll('"', '&quot;')
            .replaceAll("'", '&#039;');
    }

    function formatDate(value) {
        if (!value) return '—';
        return formatDisplayDate(String(value).substring(0, 10)) || escapeHtml(value);
    }

    function normalizeLeave(leave) {
        const maNv =
            leave.ma_nv ??
            '';

        return {
            /*
             * ma_np vẫn giữ nội bộ để action
             * sửa / xóa / duyệt hoạt động.
             * Chỉ không hiển thị cột này trên UI.
             */
            ma_np:
            leave.ma_np,

            ma_nv:
            maNv,

            ho_ten:
                leave.ho_ten ??
                leave.ten_nv ??
                leave.nhan_vien?.ho_ten ??
                (
                    String(
                        state.selectedEmployee?.ma_nv ??
                        ''
                    ) ===
                    String(maNv)
                        ? state.selectedEmployee?.ho_ten
                        : null
                ) ??
                '—',

            tu_ngay:
            leave.tu_ngay,

            den_ngay:
            leave.den_ngay,

            ma_lp:
                leave.ma_lp ??
                leave.loai_phep?.ma_lp ??
                null,

            ten_lp:
                leave.ten_lp ??
                leave.loai_phep?.ten_lp ??
                'Nghỉ phép',

            ly_do:
                leave.ly_do ??
                '',

            trang_thai_duyet:
                Number(
                    leave.trang_thai_duyet ??
                    0
                ),
        };
    }

    async function requestJson(url, options = {}) {
        const method =
            String(
                options.method || 'GET'
            ).toUpperCase();

        const token =
            csrfToken();

        const headers = {
            Accept: 'application/json',
            'Content-Type': 'application/json',
            'X-Requested-With': 'XMLHttpRequest',
            ...(options.headers || {}),
        };

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
                credentials: 'same-origin',
            }
        );

        const contentType =
            response.headers.get('content-type') || '';

        if (
            !contentType.includes('application/json')
        ) {
            const text =
                await response.text();

            console.error(
                'API trả về HTML/text:',
                text
            );

            throw new Error(
                `API không trả về JSON. HTTP ${response.status}`
            );
        }

        const result =
            await response.json();

        if (
            !response.ok ||
            result.success === false
        ) {
            const validationMessage =
                result.errors
                    ? Object.values(result.errors)
                        .flat()
                        .join(' ')
                    : null;

            if (response.status === 401) {
                throw new Error(
                    'Phiên đăng nhập đã hết hạn.'
                );
            }

            if (response.status === 403) {
                throw new Error(
                    'Bạn không có quyền thực hiện thao tác này.'
                );
            }

            if (response.status === 419) {
                throw new Error(
                    'CSRF token đã hết hạn. Vui lòng tải lại trang.'
                );
            }

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
        const requests = [];

        if (
            can(PERMISSION_CODES.READ)
        ) {
            requests.push(
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
                )
            );
        }

        if (
            canAny(
                PERMISSION_CODES.INSERT,
                PERMISSION_CODES.UPDATE
            )
        ) {
            requests.push(
                loadSimpleSelect(
                    LOAI_PHEP_API_URL,
                    elements.leaveType,
                    'ma_lp',
                    'ten_lp'
                )
            );
        }

        await Promise.all(requests);
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
     * API Laravel sẽ map các query parameter này vào projection employee
     * bằng Query Builder trên hợp đồng 15 bảng.
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
        if (
            !can(PERMISSION_CODES.READ)
        ) {
            return;
        }

        syncEmployeeFiltersFromUI();

        state.employeePage = 1;

        loadEmployees(1);
    }

    function syncHistoryFiltersFromUI() {
        state.historyFilters = {
            tu_ngay: elements.historyFromDate?.value || null,
            den_ngay: elements.historyToDate?.value || null,
        };

        return { ...state.historyFilters };
    }

    function clearHistoryFilterErrors() {
        [
            [elements.historyFromDate, elements.historyFromDateError],
            [elements.historyToDate, elements.historyToDateError],
        ].forEach(([input, error]) => {
            input?.classList.remove('is-invalid');
            if (error) error.textContent = '';
        });
    }

    function validateHistoryFilters(filters = state.historyFilters) {
        clearHistoryFilterErrors();

        const from = filters.tu_ngay || '';
        const to = filters.den_ngay || '';

        if (from && !/^\d{4}-\d{2}-\d{2}$/.test(from)) {
            elements.historyFromDate?.classList.add('is-invalid');
            if (elements.historyFromDateError) {
                elements.historyFromDateError.textContent = 'Từ ngày không hợp lệ.';
            }
            return false;
        }

        if (to && !/^\d{4}-\d{2}-\d{2}$/.test(to)) {
            elements.historyToDate?.classList.add('is-invalid');
            if (elements.historyToDateError) {
                elements.historyToDateError.textContent = 'Đến ngày không hợp lệ.';
            }
            return false;
        }

        if (from && to && from > to) {
            elements.historyFromDate?.classList.add('is-invalid');
            elements.historyToDate?.classList.add('is-invalid');
            if (elements.historyToDateError) {
                elements.historyToDateError.textContent = 'Đến ngày không được nhỏ hơn từ ngày.';
            }
            return false;
        }

        return true;
    }

    function appendHistoryFilters(url) {
        Object.entries(state.historyFilters).forEach(([key, value]) => {
            if (value) url.searchParams.set(key, value);
        });
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
     * Render pagination cho bảng nhân viên.
     */
    function renderEmployeePagination(meta) {
        if (!elements.employeePagination) {
            return;
        }

        renderSharedPagination(elements.employeePagination, meta, {
            pageAttribute: 'page',
        });
    }

    function restoreLeaveTableAnchor() {
        if (window.location.hash !== '#leave-table-card') return;

        const target = document.getElementById('leave-table-card');
        if (!target || target.hidden || typeof target.scrollIntoView !== 'function') return;

        window.requestAnimationFrame(() => {
            target.scrollIntoView({ behavior: 'auto', block: 'start' });
        });
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
        if (
            !can(PERMISSION_CODES.READ)
        ) {
            return;
        }

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
                    credentials:
                        'same-origin',
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
        state.leavePage = 1;

        elements.selectedEmployeeBadge.textContent =
            `${employee.ma_nv} · ${employee.ho_ten}`;

        if (state.activeTab === 'history') {
            elements.leaveDescription.textContent =
                `Lịch sử nghỉ phép đã xử lý của ${employee.ho_ten} (${employee.ma_nv}).`;
        } else {
            elements.leaveDescription.textContent =
                'Hiển thị toàn bộ đơn nghỉ phép đang chờ duyệt.';
        }

        elements.employeeTbody
            .querySelectorAll('[data-employee-row]')
            .forEach((row) => {
                const selected = row.dataset.maNv === employee.ma_nv;
                row.classList.toggle('table-primary', selected);

                const radio = row.querySelector('.employee-radio');
                if (radio) radio.checked = selected;
            });

        // Click nhân viên chỉ reload lịch sử đã xử lý của nhân viên đó.
        loadProcessedLeavesForEmployee();
    }

    function leaveStatusLabel(status) {
        if (status === 0) {
            return `
                <span
                    class="badge rounded-pill text-bg-warning leave-status-badge"
                    title="Đơn nghỉ phép đang chờ phê duyệt."
                >
                    Chờ duyệt
                </span>
            `;
        }

        if (status === 1) {
            return `
                <span
                    class="badge rounded-pill text-bg-success leave-status-badge"
                    title="Đơn nghỉ phép đã được phê duyệt."
                >
                    Đã duyệt
                </span>
            `;
        }

        return `
            <span
                class="badge rounded-pill text-bg-danger leave-status-badge"
                title="Đơn nghỉ phép đã bị từ chối."
            >
                Từ chối
            </span>
        `;
    }

    function rowsForActiveTab() {
        return state.activeTab === 'pending'
            ? state.pendingRows
            : state.historyRows;
    }

    function updateLeaveCounts() {
        const pendingTotal = Number(state.counts?.pending);
        const pendingFallback = Number(state.pendingPaginator?.total);
        const historyTotal = Number(state.counts?.history);
        const historyFallback = Number(state.historyPaginator?.total);

        if (elements.pendingCount) {
            elements.pendingCount.textContent =
                String(Number.isFinite(pendingTotal) && pendingTotal >= 0
                    ? pendingTotal
                    : Number.isFinite(pendingFallback) && pendingFallback >= 0
                        ? pendingFallback
                        : state.pendingRows.length);
        }

        if (elements.historyCount) {
            elements.historyCount.textContent =
                String(Number.isFinite(historyTotal) && historyTotal >= 0
                    ? historyTotal
                    : Number.isFinite(historyFallback) && historyFallback >= 0
                        ? historyFallback
                        : state.historyRows.length);
        }

        const approved = state.historyRows.filter(
            (item) => item.trang_thai_duyet === 1
        ).length;

        const oldApprovedCount =
            document.getElementById('approved-count');

        if (oldApprovedCount) {
            oldApprovedCount.textContent = String(approved);
        }
    }

    function leavePaginator() {
        return state.activeTab === 'pending'
            ? state.pendingPaginator
            : state.historyPaginator;
    }

    function renderLeavePagination(paginator) {
        if (!elements.pagination) return;

        renderSharedPagination(elements.pagination, paginator, {
            pageAttribute: 'leavePage',
        });
    }

    function renderLeaveActions(leave) {
        const leaveId = escapeHtml(leave.ma_np);
        const actions = [];

        if (can(PERMISSION_CODES.UPDATE)) {
            actions.push(`
                <button
                    class="btn btn-outline-primary btn-icon-action"
                    type="button"
                    data-leave-action="edit"
                    data-leave-id="${leaveId}"
                    aria-label="Sửa đơn nghỉ phép ${leaveId}"
                    title="Sửa đơn nghỉ phép ${leaveId}"
                ><i class="bi bi-pencil-square" aria-hidden="true"></i></button>
            `);
        }

        if (can(PERMISSION_CODES.DELETE)) {
            actions.push(`
                <button
                    class="btn btn-outline-danger btn-icon-action"
                    type="button"
                    data-leave-action="delete"
                    data-leave-id="${leaveId}"
                    aria-label="Xóa đơn nghỉ phép ${leaveId}"
                    title="Xóa đơn nghỉ phép ${leaveId}"
                ><i class="bi bi-trash" aria-hidden="true"></i></button>
            `);
        }

        if (leave.trang_thai_duyet === 0 && canApproveLeaves()) {
            actions.push(`
                <button
                    class="btn btn-outline-success btn-icon-action"
                    type="button"
                    data-leave-action="approve"
                    data-leave-id="${leaveId}"
                    aria-label="Duyệt đơn nghỉ phép ${leaveId}"
                    title="Duyệt đơn nghỉ phép ${leaveId}"
                ><i class="bi bi-check2" aria-hidden="true"></i></button>
            `);
        }

        return actions.length > 0
            ? `<div class="table-actions">${actions.join('')}</div>`
            : '—';
    }

    function renderLeaves() {
        const paginator = leavePaginator();
        const rows = rowsForActiveTab();

        if (!rows.length) {
            elements.leaveTbody.innerHTML = `
                <tr>
                    <td colspan="8" class="text-center text-secondary py-5">
                        ${
                state.activeTab === 'pending'
                    ? 'Không có đơn nghỉ phép chờ duyệt.'
                    : 'Không có lịch sử nghỉ phép đã xử lý trong khoảng ngày này.'
            }
                    </td>
                </tr>
            `;
            elements.pageInfo.textContent = 'Hiển thị 0 trên 0 yêu cầu';
            elements.pagination.innerHTML = '';
            return;
        }

        elements.leaveTbody.innerHTML = rows.map((leave) => `
            <tr
                data-leave-row
                data-id="${escapeHtml(leave.ma_np)}"
            >
                <td class="fw-semibold">
                    ${escapeHtml(leave.ma_nv)}
                </td>

                <td>
                    ${escapeHtml(leave.ho_ten)}
                </td>

                <td>${formatDate(leave.tu_ngay)}</td>
                <td>${formatDate(leave.den_ngay)}</td>
                <td>${escapeHtml(leave.ten_lp)}</td>
                <td class="leave-reason-cell">
                    <span
                        class="leave-reason-text"
                        title="${escapeHtml(leave.ly_do || 'Không có lý do')}"
                    >
                        ${escapeHtml(leave.ly_do || '—')}
                    </span>
                </td>
                <td>${leaveStatusLabel(leave.trang_thai_duyet)}</td>
                <td>${renderLeaveActions(leave)}</td>
            </tr>
        `).join('');

        elements.pageInfo.textContent = paginator
            ? `Hiển thị ${paginator.from ?? 0}–${paginator.to ?? 0} trên ${paginator.total ?? rows.length} yêu cầu`
            : `Hiển thị ${rows.length} yêu cầu`;

        if (paginator) renderLeavePagination(paginator);
    }

    function renderLeaveLoading(message = 'Đang tải dữ liệu nghỉ phép...') {
        elements.leaveTbody.innerHTML = `
            <tr>
                <td colspan="8" class="text-center text-secondary py-5">
                    <span class="spinner-border spinner-border-sm me-2" aria-hidden="true"></span>
                    ${escapeHtml(message)}
                </td>
            </tr>
        `;
    }

    async function loadPendingLeaves({ render = true } = {}) {
        if (!can(PERMISSION_CODES.READ)) return;

        state.pendingAbortController?.abort();
        state.pendingAbortController = new AbortController();

        if (render && state.activeTab === 'pending') {
            renderLeaveLoading('Đang tải toàn bộ đơn chờ duyệt...');
        }

        try {
            const url = new URL(NGHI_PHEP_API_URL, window.location.origin);
            url.searchParams.set('trang_thai_duyet', '0');
            url.searchParams.set('page', String(state.leavePage));
            url.searchParams.set('per_page', String(state.leavePerPage));
            url.searchParams.set('tab', 'pending');
            const filters = state.employeeFilters;
            if (filters.tu_khoa) url.searchParams.set('tu_khoa', filters.tu_khoa);
            if (filters.ma_pb) url.searchParams.set('ma_pb', filters.ma_pb);
            if (filters.ma_cv) url.searchParams.set('ma_cv', filters.ma_cv);

            const response = await fetch(url.toString(), {
                headers: {
                    Accept: 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                },
                credentials: 'same-origin',
                signal: state.pendingAbortController.signal,
            });

            const contentType = response.headers.get('content-type') || '';
            if (!contentType.includes('application/json')) {
                throw new Error(`API nghỉ phép không trả JSON. HTTP ${response.status}`);
            }

            const result = await response.json();
            if (!response.ok || result.success === false) {
                throw new Error(result.message || 'Không thể tải danh sách chờ duyệt.');
            }

            state.pendingPaginator = result.data && !Array.isArray(result.data)
                ? result.data
                : null;
            state.counts = {
                ...state.counts,
                pending: result.counts?.pending ?? state.counts.pending,
            };
            state.pendingRows = extractData(result)
                .map(normalizeLeave)
                .filter((item) => item.trang_thai_duyet === 0);

            updateLeaveCounts();

            if (render && state.activeTab === 'pending') {
                renderLeaves();
            }
        } catch (error) {
            if (error.name === 'AbortError') return;

            console.error('Error loading pending leaves:', error);

            if (render && state.activeTab === 'pending') {
                elements.leaveTbody.innerHTML = `
                    <tr>
                        <td colspan="8" class="text-center text-danger py-5">
                            ${escapeHtml(error.message)}
                        </td>
                    </tr>
                `;
            }
        }
    }

    async function loadProcessedLeavesForEmployee({ render = true } = {}) {
        state.historyAbortController?.abort();

        if (!can(PERMISSION_CODES.READ)) {
            state.historyRows = [];
            updateLeaveCounts();

            if (render && state.activeTab === 'history') {
                renderLeaves();
            }
            return;
        }

        state.historyAbortController = new AbortController();

        if (render && state.activeTab === 'history') {
            renderLeaveLoading(
                state.selectedEmployee
                    ? `Đang tải lịch sử nghỉ phép của ${state.selectedEmployee.ho_ten}...`
                    : 'Đang tải lịch sử nghỉ phép toàn công ty...'
            );
        }

        try {
            const url = new URL(NGHI_PHEP_API_URL, window.location.origin);
            if (state.selectedEmployee?.ma_nv) {
                url.searchParams.set('ma_nv', state.selectedEmployee.ma_nv);
            }
            url.searchParams.set('page', String(state.leavePage));
            url.searchParams.set('per_page', String(state.leavePerPage));
            url.searchParams.set('tab', 'history');
            appendHistoryFilters(url);

            const response = await fetch(url.toString(), {
                headers: {
                    Accept: 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                },
                credentials: 'same-origin',
                signal: state.historyAbortController.signal,
            });

            const contentType = response.headers.get('content-type') || '';
            if (!contentType.includes('application/json')) {
                throw new Error(`API nghỉ phép không trả JSON. HTTP ${response.status}`);
            }

            const result = await response.json();
            if (!response.ok || result.success === false) {
                throw new Error(result.message || 'Không thể tải lịch sử nghỉ phép.');
            }

            state.historyPaginator = result.data && !Array.isArray(result.data)
                ? result.data
                : null;
            state.counts = {
                ...state.counts,
                history: result.counts?.history ?? state.counts.history,
            };
            state.historyRows = extractData(result)
                .map(normalizeLeave)
                .filter((item) => item.trang_thai_duyet !== 0);

            updateLeaveCounts();

            if (render && state.activeTab === 'history') {
                renderLeaves();
            }
        } catch (error) {
            if (error.name === 'AbortError') return;

            console.error('Error loading processed leave data:', error);

            if (render && state.activeTab === 'history') {
                elements.leaveTbody.innerHTML = `
                    <tr>
                        <td colspan="8" class="text-center text-danger py-5">
                            ${escapeHtml(error.message)}
                        </td>
                    </tr>
                `;
            }
        }
    }

    async function refreshLeaveData() {
        const tasks = [
            loadPendingLeaves({
                render: state.activeTab === 'pending',
            }),
        ];

        tasks.push(
            loadProcessedLeavesForEmployee({
                render: state.activeTab === 'history',
            })
        );

        await Promise.all(tasks);
    }

    function switchTab(tab) {
        state.activeTab = tab;
        state.leavePage = 1;

        if (elements.historyFilterForm) {
            elements.historyFilterForm.hidden = tab !== 'history';
        }

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

        if (tab === 'pending') {
            elements.leaveDescription.textContent =
                'Hiển thị toàn bộ đơn nghỉ phép đang chờ duyệt.';
        } else if (state.selectedEmployee) {
            elements.leaveDescription.textContent =
                `Lịch sử nghỉ phép đã xử lý của ${state.selectedEmployee.ho_ten} (${state.selectedEmployee.ma_nv}).`;
        } else {
            elements.leaveDescription.textContent =
                'Hiển thị lịch sử nghỉ phép đã xử lý của toàn công ty trong khoảng ngày đã chọn.';
        }

        if (tab === 'pending') {
            loadPendingLeaves();
        } else {
            loadProcessedLeavesForEmployee();
        }
    }

    function showAllLeaveHistory() {
        const filters = syncHistoryFiltersFromUI();
        if (!validateHistoryFilters(filters)) return;

        state.selectedEmployee = null;
        state.leavePage = 1;
        if (elements.selectedEmployeeBadge) {
            elements.selectedEmployeeBadge.textContent = 'Chưa chọn nhân viên';
        }

        elements.employeeTbody
            .querySelectorAll('[data-employee-row]')
            .forEach((row) => {
                row.classList.remove('table-primary');
                const radio = row.querySelector('.employee-radio');
                if (radio) radio.checked = false;
            });

        switchTab('history');
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

    function openEditModal(leaveId) {
        if (
            !guard(
                PERMISSION_CODES.UPDATE,
                'sửa nghỉ phép'
            )
        ) {
            return;
        }

        const leave =
            rowsForActiveTab().find(
                (item) =>
                    String(item.ma_np) ===
                    String(leaveId)
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
            canonicalServerDate(leave.tu_ngay) ?? '';

        elements.leaveToDate.value =
            canonicalServerDate(leave.den_ngay) ?? '';

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
            tu_ngay: toIsoDate(elements.leaveFromDate.value) || null,
            den_ngay: toIsoDate(elements.leaveToDate.value) || null,
            ma_lp:
                elements.leaveType.value || null,
            ly_do:
                elements.leaveReason.value.trim() || null,
        };
    }

    function validateLeavePayload(payload) {
        const fromRaw = elements.leaveFromDate?.value || '';
        const toRaw = elements.leaveToDate?.value || '';
        const fromIso = fromRaw ? toIsoDate(fromRaw) : null;
        const toIso = toRaw ? toIsoDate(toRaw) : null;
        if (elements.leaveFromDateError) elements.leaveFromDateError.textContent = '';
        if (elements.leaveToDateError) elements.leaveToDateError.textContent = '';
        if (fromRaw && !fromIso) {
            if (elements.leaveFromDateError) elements.leaveFromDateError.textContent = 'Từ ngày không hợp lệ.';
            return 'Từ ngày không hợp lệ.';
        }
        if (toRaw && !toIso) {
            if (elements.leaveToDateError) elements.leaveToDateError.textContent = 'Đến ngày không hợp lệ.';
            return 'Đến ngày không hợp lệ.';
        }
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

        if (
            !guard(
                PERMISSION_CODES.UPDATE,
                'sửa nghỉ phép'
            )
        ) {
            return;
        }

        const payload = buildLeavePayload();
        const validationMessage =
            validateLeavePayload(payload);

        if (validationMessage) {
            showModalMessage(validationMessage);
            return;
        }

        const leaveId =
            elements.leaveId.value;

        if (!leaveId) {
            showModalMessage(
                'Không xác định được mã nghỉ phép cần sửa.'
            );
            return;
        }

        elements.modalSubmit.disabled = true;
        elements.modalSubmit.textContent =
            'Đang lưu...';

        try {
            await requestJson(
                `${NGHI_PHEP_API_URL}/${encodeURIComponent(leaveId)}`,
                {
                    method: 'PUT',
                    body: JSON.stringify(payload),
                }
            );

            closeModal();
            await refreshLeaveData();
        } catch (error) {
            console.error(error);
            showModalMessage(error.message);
        } finally {
            elements.modalSubmit.disabled = false;
            elements.modalSubmit.textContent =
                'Lưu thay đổi';
        }
    }

    const leaveDeleteActions = new WeakMap();

    function deleteLeave(leaveId, button) {
        if (!button || !guard(PERMISSION_CODES.DELETE, 'xóa đơn nghỉ phép')) {
            return Promise.resolve(false);
        }

        let action = leaveDeleteActions.get(button);
        if (!action) {
            action = createDeleteAction({
                button,
                getSelection: () => ({
                    id: leaveId,
                    persisted: rowsForActiveTab().some(
                        (item) => String(item.ma_np) === String(leaveId)
                    ),
                    canDelete: can(PERMISSION_CODES.DELETE),
                }),
                confirmAction: () => window.confirm('Bạn có chắc muốn xóa đơn nghỉ phép này không?'),
                requestDelete: (id) => requestJson(
                    `${NGHI_PHEP_API_URL}/${encodeURIComponent(id)}`,
                    { method: 'DELETE' },
                ),
                onSuccess: () => refreshLeaveData(),
                onError: (error) => {
                    console.error(error);
                    window.alert(error.message);
                },
                sync: () => {
                    if (!button.isConnected) return;
                    button.disabled = false;
                    button.title = `Xóa đơn nghỉ phép ${leaveId}`;
                    button.setAttribute('aria-label', `Xóa đơn nghỉ phép ${leaveId}`);
                },
                busyLabel: 'Đang xóa...',
            });
            leaveDeleteActions.set(button, action);
        }

        return action();
    }

    async function approveLeave(leaveId, button) {
        if (!canApproveLeaves()) {
            notifyDenied('duyệt nghỉ phép');
            return;
        }

        const leave =
            state.pendingRows.find(
                (item) =>
                    String(item.ma_np) ===
                    String(leaveId)
            );

        if (!leave || leave.trang_thai_duyet !== 0) {
            return;
        }

        const originalContent = button?.innerHTML || '';
        if (button) {
            button.disabled = true;
            button.setAttribute('aria-busy', 'true');
            button.innerHTML = '<span class="spinner-border spinner-border-sm" aria-hidden="true"></span><span class="visually-hidden">Đang duyệt...</span>';
        }

        try {
            await requestJson(
                `${NGHI_PHEP_API_URL}/${encodeURIComponent(
                    leaveId
                )}/duyet`,
                {
                    method: 'PATCH',
                    body: JSON.stringify({
                        trang_thai_duyet: 1,
                    }),
                }
            );

            await refreshLeaveData();
        } catch (error) {
            console.error(error);
            window.alert(error.message);
        } finally {
            if (button?.isConnected) {
                button.disabled = false;
                button.removeAttribute('aria-busy');
                button.innerHTML = originalContent;
            }
        }
    }

    function clearEmployeeFilters() {
        if (
            !can(PERMISSION_CODES.READ)
        ) {
            return;
        }

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
            const button = event.target.closest(
                '[data-leave-action][data-leave-id]'
            );

            if (!button) return;

            const leaveId = button.dataset.leaveId;
            const action = button.dataset.leaveAction;

            if (action === 'edit') {
                openEditModal(leaveId);
            } else if (action === 'delete') {
                deleteLeave(leaveId, button);
            } else if (action === 'approve') {
                approveLeave(leaveId, button);
            }
        }
    );

    elements.filterForm?.addEventListener('submit', (event) => {
        event.preventDefault();
        applyEmployeeFilters();
    });

    elements.clearFilterButton?.addEventListener(
        'click',
        clearEmployeeFilters
    );

    elements.historyFilterForm?.addEventListener('submit', (event) => {
        event.preventDefault();
        const filters = syncHistoryFiltersFromUI();
        if (!validateHistoryFilters(filters)) return;

        state.leavePage = 1;
        if (state.activeTab !== 'history') {
            switchTab('history');
        } else {
            refreshLeaveData();
        }
    });

    elements.allLeavesButton?.addEventListener(
        'click',
        showAllLeaveHistory
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

    elements.pagination?.addEventListener(
        'click',
        (event) => {
            const button =
                event.target.closest(
                    'button[data-leave-page]'
                );

            if (!button || button.disabled) {
                return;
            }

            const page = Number(
                button.dataset.leavePage
            );

            if (!Number.isInteger(page) || page < 1) {
                return;
            }

            state.leavePage = page;
            if (state.activeTab === 'pending') {
                loadPendingLeaves();
            } else {
                loadProcessedLeavesForEmployee();
            }
        }
    );

    elements.leavePerPage?.addEventListener(
        'change',
        () => {
            const value = Number(
                elements.leavePerPage.value
            );

            if (!Number.isInteger(value) || value < 1) {
                return;
            }

            state.leavePerPage = value;
            state.leavePage = 1;
            if (state.activeTab === 'pending') {
                loadPendingLeaves();
            } else {
                loadProcessedLeavesForEmployee();
            }
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
        try {
            await loadAuthContext();

            if (elements.authLoading) {
                elements.authLoading.hidden =
                    true;
            }

            const hasAccess =
                canAny(
                    PERMISSION_CODES.READ,
                    PERMISSION_CODES.INSERT,
                    PERMISSION_CODES.UPDATE,
                    PERMISSION_CODES.DELETE
                );

            if (!hasAccess) {
                if (elements.accessDenied) {
                    elements.accessDenied.hidden =
                        false;
                }

                return;
            }

            applyPermissionVisibility();
            restoreLeaveTableAnchor();

            const readOnly =
                can(PERMISSION_CODES.READ) &&
                !can(PERMISSION_CODES.INSERT) &&
                !can(PERMISSION_CODES.UPDATE) &&
                !can(PERMISSION_CODES.DELETE) &&
                !canApproveLeaves();

            if (elements.readOnlyBadge) {
                elements.readOnlyBadge.hidden =
                    !readOnly;
            }

            if (elements.noReadNotice) {
                elements.noReadNotice.hidden =
                    can(PERMISSION_CODES.READ);
            }

            await loadLookups();

            if (
                !can(PERMISSION_CODES.READ)
            ) {
                return;
            }

            syncEmployeeFiltersFromUI();

            // Pending global được load ngay, không cần chọn nhân viên.
            await Promise.all([
                loadEmployees(1),
                loadPendingLeaves(),
                loadProcessedLeavesForEmployee({ render: false }),
            ]);

            // Bảng nhân viên có thể thay đổi chiều cao sau khi fragment đã được reveal.
            // Cuộn lại sau khi dữ liệu đã render để anchor landing đúng vị trí.
            restoreLeaveTableAnchor();
        } catch (error) {
            console.error(
                'Initialize leave module failed:',
                error
            );

            if (elements.authLoading) {
                elements.authLoading.hidden =
                    true;
            }

            if (elements.accessDenied) {
                elements.accessDenied.hidden =
                    false;
            }

            if (elements.accessDeniedMessage) {
                elements.accessDeniedMessage.textContent =
                    error.message;
            }
        }
    }

    initialize();
});
