document.addEventListener('DOMContentLoaded', () => {
    const AUTH_ME_API_URL = '/api/v1/auth/me';

    const CHAM_CONG_API_URL = '/api/v1/cham-cong';
    const CHAM_CONG_EXPORT_API_URL = '/api/v1/cham-cong/export';
    const CHAM_CONG_IMPORT_API_URL = '/api/v1/cham-cong/import';
    const CHAM_CONG_IMPORT_TEMPLATE_API_URL = '/api/v1/cham-cong/template';
    const NHAN_VIEN_API_URL = '/api/v1/cham-cong/nhan-vien';
    const PHONG_BAN_API_URL = '/api/v1/cham-cong/phong-ban';

    const PERMISSION_CODES = Object.freeze({
        READ: 'ChamCong.Read',
        INSERT: 'ChamCong.Insert',
        UPDATE: 'ChamCong.Update',
        DELETE: 'ChamCong.Delete',
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
        return permissions.some((permission) => can(permission));
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
                    .map((item) => item?.ky_hieu_quyen)
                    .filter((item) => typeof item === 'string')
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

        if (!contentType.includes('application/json')) {
            const body = await response.text();

            console.error(
                'Auth API trả về HTML/text:',
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
            new Set(normalized.permissions);

        permissionState.initialized = true;

        return normalized;
    }

    function applyPermissionVisibility(root = document) {
        root
            .querySelectorAll(
                '[data-attendance-permission]'
            )
            .forEach((element) => {
                const required = String(
                    element.dataset.attendancePermission || ''
                )
                    .split(',')
                    .map((item) => item.trim())
                    .filter(Boolean);

                const allowed =
                    required.length === 0 ||
                    required.some(
                        (permission) => can(permission)
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
        const message =
            `Bạn không có quyền ${action}.`;

        const toast =
            document.querySelector('.attendance-toast');

        if (toast) {
            toast.textContent = message;
            toast.classList.add('show');

            window.setTimeout(() => {
                toast.classList.remove('show');
            }, 2500);

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


    const elements = {
        authLoading:
            document.getElementById('attendance-auth-loading'),

        accessDenied:
            document.getElementById('attendance-access-denied'),

        accessDeniedMessage:
            document.getElementById('attendance-access-denied-message'),

        readOnlyBadge:
            document.getElementById('attendance-readonly-badge'),

        noReadNotice:
            document.getElementById('attendance-no-read-notice'),

        search: document.getElementById('search-field'),
        month: document.getElementById('month-filter'),
        year: document.getElementById('year-filter'),
        department: document.getElementById('department-filter'),
        clearFilterButton: document.getElementById('clear-filter-btn'),

        importButton: document.getElementById('import-btn'),

        importDialog:
            document.getElementById('attendance-import-dialog'),

        importClose:
            document.getElementById('attendance-import-close'),

        importCancel:
            document.getElementById('attendance-import-cancel'),

        importMessage:
            document.getElementById('attendance-import-message'),

        importSuccess:
            document.getElementById('attendance-import-success'),

        downloadImportTemplateButton:
            document.getElementById('download-import-template-btn'),

        downloadImportTemplateLabel:
            document.getElementById('download-import-template-label'),

        chooseImportFileButton:
            document.getElementById('choose-import-file-btn'),

        importSelected:
            document.getElementById('attendance-import-selected'),

        importFileName:
            document.getElementById('attendance-import-file-name'),

        importFileSize:
            document.getElementById('attendance-import-file-size'),

        importRemoveFile:
            document.getElementById('attendance-import-remove-file'),

        importSubmit:
            document.getElementById('attendance-import-submit'),

        importSubmitLabel:
            document.getElementById('attendance-import-submit-label'),

        exportButton: document.getElementById('export-btn'),

        exportDialog:
            document.getElementById('attendance-export-dialog'),

        exportForm:
            document.getElementById('attendance-export-form'),

        exportClose:
            document.getElementById('attendance-export-close'),

        exportCancel:
            document.getElementById('attendance-export-cancel'),

        exportMessage:
            document.getElementById('attendance-export-message'),

        exportMonth:
            document.getElementById('attendance-export-month'),

        exportYear:
            document.getElementById('attendance-export-year'),

        exportFormat:
            document.getElementById('attendance-export-format'),

        exportSubmit:
            document.getElementById('attendance-export-submit'),

        exportSubmitLabel:
            document.getElementById('attendance-export-submit-label'),

        updateButton: document.getElementById('update-btn'),
        deleteButton: document.getElementById('delete-btn'),
        importFile: document.getElementById('attendance-import-file'),

        employeeTbody: document.getElementById('employee-tbody'),
        employeePageInfo: document.getElementById('employee-page-info'),
        employeePagination: document.getElementById('employee-pagination'),
        employeePerPage: document.getElementById('employee-per-page'),
        selectedEmployeeBadge: document.getElementById('selected-employee-badge'),
        employeeUpdated: document.getElementById('employee-updated'),

        attendanceTbody: document.getElementById('attendance-tbody'),
        attendanceDescription: document.getElementById('attendance-description'),
        attendancePageInfo: document.getElementById('page-info'),
        attendancePagination: document.getElementById('pagination'),
        attendancePerPage: document.getElementById('attendance-per-page'),

        totalHours: document.getElementById('total-hours'),
        lateCount: document.getElementById('late-count'),
        earlyCount: document.getElementById('early-count'),
        avgDays: document.getElementById('avg-days'),
    };

    if (!elements.employeeTbody || !elements.attendanceTbody) return;

    const state = {
        employeePage: 1,
        employeePerPage: Number(elements.employeePerPage?.value || 15),
        filters: {
            tu_khoa: null,
            ma_pb: null,
            thang: null,
            nam: null,
        },

        selectedEmployee: null,

        attendancePage: 1,
        attendancePerPage: Number(elements.attendancePerPage?.value || 15),
        selectedAttendanceId: null,
        selectedAttendanceRow: null,

        employeeAbortController: null,
        attendanceAbortController: null,

        selectedImportFile: null,
    };

    let searchTimer = null;

    function escapeHtml(value) {
        return String(value ?? '')
            .replaceAll('&', '&amp;')
            .replaceAll('<', '&lt;')
            .replaceAll('>', '&gt;')
            .replaceAll('"', '&quot;')
            .replaceAll("'", '&#039;');
    }

    function number(value, digits = 2) {
        const n = Number(value);
        if (!Number.isFinite(n)) return '0';

        return n.toLocaleString('vi-VN', {
            minimumFractionDigits: 0,
            maximumFractionDigits: digits,
        });
    }

    function formatDate(value) {
        if (!value) return '—';

        const match = String(value).match(/^(\d{4})-(\d{2})-(\d{2})/);
        if (!match) return escapeHtml(value);

        return `${match[3]}-${match[2]}-${match[1]}`;
    }

    function weekday(value) {
        const match = String(value ?? '').match(/^(\d{4})-(\d{2})-(\d{2})/);
        if (!match) return '—';

        const date = new Date(
            Number(match[1]),
            Number(match[2]) - 1,
            Number(match[3])
        );

        return [
            'Chủ nhật', 'Thứ hai', 'Thứ ba', 'Thứ tư',
            'Thứ năm', 'Thứ sáu', 'Thứ bảy'
        ][date.getDay()];
    }

    function workday(hours) {
        const h = Number(hours || 0);
        if (h >= 8) return 1;
        if (h >= 4) return 0.5;
        return 0;
    }

    function gender(value) {
        if (value === 1 || value === '1' || value === true || value === 'Nam') return 'Nam';
        if (value === 0 || value === '0' || value === false || value === 'Nữ') return 'Nữ';
        return '—';
    }

    function extractRows(result) {
        if (Array.isArray(result)) return result;
        if (Array.isArray(result?.data)) return result.data;
        if (Array.isArray(result?.data?.data)) return result.data.data;
        return [];
    }

    function paginatorOf(result) {
        if (
            result?.data &&
            typeof result.data === 'object' &&
            !Array.isArray(result.data) &&
            Array.isArray(result.data.data)
        ) {
            return result.data;
        }

        return null;
    }

    function buildUrl(base, params) {
        const url = new URL(base, window.location.origin);

        Object.entries(params).forEach(([key, value]) => {
            if (value !== null && value !== undefined && value !== '') {
                url.searchParams.set(key, String(value));
            }
        });

        return url.toString();
    }

    async function requestJson(url, options = {}) {
        const response = await fetch(url, {
            headers: {
                Accept: 'application/json',
                'Content-Type': 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
                ...(options.headers || {}),
            },
            credentials: 'same-origin',
            ...options,
        });

        const contentType = response.headers.get('content-type') || '';

        if (!contentType.includes('application/json')) {
            const text = await response.text();
            console.error(text);
            throw new Error(`API không trả JSON. HTTP ${response.status}`);
        }

        const result = await response.json();

        if (!response.ok || result.success === false) {
            const validation = result.errors
                ? Object.values(result.errors).flat().join(' ')
                : null;

            throw new Error(
                validation ||
                result.message ||
                `Request thất bại. HTTP ${response.status}`
            );
        }

        return result;
    }

    function initMonthYear() {
        const now = new Date();
        const currentMonth = now.getMonth() + 1;
        const currentYear = now.getFullYear();

        if (elements.month) {
            elements.month.innerHTML = '';

            for (let month = 1; month <= 12; month++) {
                const option = document.createElement('option');
                option.value = String(month);
                option.textContent = `Tháng ${month}`;
                option.selected = month === currentMonth;
                elements.month.appendChild(option);
            }
        }

        if (elements.year) {
            elements.year.value = String(currentYear);
        }
    }

    async function loadDepartments() {
        if (
            !elements.department ||
            !can(PERMISSION_CODES.READ)
        ) {
            return;
        }

        const first =
            elements.department.options[0]?.outerHTML ??
            '<option value="">-- Tất cả phòng ban --</option>';

        elements.department.innerHTML = first;

        try {
            const result = await requestJson(PHONG_BAN_API_URL);

            extractRows(result).forEach((item) => {
                const option = document.createElement('option');
                option.value = String(item.ma_pb);
                option.textContent = item.ten_pb ?? item.ma_pb;
                elements.department.appendChild(option);
            });
        } catch (error) {
            console.error('Load department failed:', error);
        }
    }

    function syncFilters() {
        state.filters = {
            tu_khoa: elements.search?.value.trim() || null,
            ma_pb: elements.department?.value || null,
            thang: elements.month?.value || null,
            nam: elements.year?.value || null,
        };
    }

    function employeeUrl(page = state.employeePage) {
        return buildUrl(NHAN_VIEN_API_URL, {
            ...state.filters,
            page,
            per_page: state.employeePerPage,
        });
    }

    function attendanceUrl(page = state.attendancePage) {
        return buildUrl(CHAM_CONG_API_URL, {
            ma_nv: state.selectedEmployee?.ma_nv,
            thang: state.filters.thang,
            nam: state.filters.nam,
            page,
            per_page: state.attendancePerPage,
        });
    }

    function pageItems(current, last) {
        if (last <= 7) {
            return Array.from({ length: last }, (_, i) => i + 1);
        }

        const pages = [...new Set([
            1,
            last,
            current - 1,
            current,
            current + 1,
        ])]
            .filter((p) => p >= 1 && p <= last)
            .sort((a, b) => a - b);

        const items = [];

        pages.forEach((page, index) => {
            const prev = pages[index - 1];

            if (prev !== undefined && page - prev > 1) {
                items.push('...');
            }

            items.push(page);
        });

        return items;
    }

    function renderPagination(container, paginator, type) {
        if (!container) return;

        container.innerHTML = '';

        const current = Number(paginator?.current_page || 1);
        const last = Number(paginator?.last_page || 1);

        if (last <= 1) return;

        const group = document.createElement('div');
        group.className = 'btn-group btn-group-sm';

        function addButton(label, page, disabled, active = false) {
            const button = document.createElement('button');
            button.type = 'button';
            button.textContent = label;
            button.disabled = disabled;
            button.dataset.page = String(page);
            button.dataset.paginationType = type;
            button.className = active
                ? 'btn btn-primary'
                : 'btn btn-outline-secondary';

            group.appendChild(button);
        }

        addButton('‹', current - 1, current <= 1);

        pageItems(current, last).forEach((item) => {
            if (item === '...') {
                const span = document.createElement('span');
                span.className = 'btn btn-outline-secondary disabled';
                span.textContent = '…';
                group.appendChild(span);
                return;
            }

            addButton(String(item), item, false, item === current);
        });

        addButton('›', current + 1, current >= last);

        container.appendChild(group);
    }

    function renderPageInfo(element, paginator, noun) {
        if (!element) return;

        const total = Number(paginator?.total || 0);
        const from = paginator?.from ?? 0;
        const to = paginator?.to ?? 0;

        element.textContent = total > 0
            ? `Hiển thị ${from}–${to} trên ${total} ${noun}`
            : `Hiển thị 0 trên 0 ${noun}`;
    }

    function normalizeEmployee(item) {
        return {
            ma_nv: item.ma_nv ?? '',
            ho_ten:
                item.ho_ten ??
                item.ten_nv ??
                item.nhan_vien?.ho_ten ??
                item.nhan_vien?.ten_nv ??
                'N/A',
            gioi_tinh: item.gioi_tinh ?? item.nhan_vien?.gioi_tinh,
            sdt: item.sdt ?? item.nhan_vien?.sdt ?? '—',
            email: item.email ?? item.nhan_vien?.email ?? '—',
            ten_pb:
                item.ten_pb ??
                item.phong_ban ??
                item.nhan_vien?.ten_pb ??
                item.nhan_vien?.phong_ban ??
                '—',
            ten_cv:
                item.ten_cv ??
                item.chuc_vu ??
                item.nhan_vien?.ten_cv ??
                item.nhan_vien?.chuc_vu ??
                '—',
            so_lan_vao_muon: Number(item.so_lan_vao_muon ?? item.vao_muon ?? 0),
            so_lan_ve_som: Number(item.so_lan_ve_som ?? item.ve_som ?? 0),
            so_ngay_cham_cong: Number(item.so_ngay_cham_cong ?? item.ngay_cong ?? 0),
        };
    }

    function renderEmployeeRows(rows) {
        if (!rows.length) {
            elements.employeeTbody.innerHTML = `
                <tr>
                    <td colspan="11" class="text-center text-secondary py-5">
                        Không tìm thấy nhân viên.
                    </td>
                </tr>
            `;
            return;
        }

        elements.employeeTbody.innerHTML = rows.map((raw) => {
            const item = normalizeEmployee(raw);
            const selected = state.selectedEmployee?.ma_nv === item.ma_nv;

            return `
                <tr data-employee-row data-ma-nv="${escapeHtml(item.ma_nv)}"
                    class="${selected ? 'table-primary' : ''}">
                    <td>
                        <input class="form-check-input employee-radio"
                               type="radio"
                               name="selected-employee"
                               ${selected ? 'checked' : ''}>
                    </td>
                    <td class="fw-semibold">${escapeHtml(item.ma_nv)}</td>
                    <td>${escapeHtml(item.ho_ten)}</td>
                    <td>${gender(item.gioi_tinh)}</td>
                    <td>${escapeHtml(item.sdt)}</td>
                    <td>${escapeHtml(item.email)}</td>
                    <td>${escapeHtml(item.ten_pb)}</td>
                    <td>${escapeHtml(item.ten_cv)}</td>
                    <td class="text-end">${number(item.so_lan_vao_muon, 0)}</td>
                    <td class="text-end">${number(item.so_lan_ve_som, 0)}</td>
                    <td class="text-end fw-semibold">${number(item.so_ngay_cham_cong)}</td>
                </tr>
            `;
        }).join('');
    }

    async function loadEmployees(page = 1) {
        if (!can(PERMISSION_CODES.READ)) {
            return;
        }

        state.employeePage = Math.max(Number(page) || 1, 1);
        syncFilters();

        state.employeeAbortController?.abort();
        state.employeeAbortController = new AbortController();

        elements.employeeTbody.innerHTML = `
            <tr>
                <td colspan="11" class="text-center text-secondary py-5">
                    <span class="spinner-border spinner-border-sm me-2"></span>
                    Đang tải danh sách nhân viên...
                </td>
            </tr>
        `;

        try {
            const response = await fetch(employeeUrl(), {
                headers: {
                    Accept: 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                },
                signal: state.employeeAbortController.signal,
            });

            const contentType = response.headers.get('content-type') || '';

            if (!contentType.includes('application/json')) {
                throw new Error(`API nhân viên không trả JSON. HTTP ${response.status}`);
            }

            const result = await response.json();

            if (!response.ok || result.success === false) {
                throw new Error(result.message || 'Không tải được danh sách nhân viên.');
            }

            const rows = extractRows(result);
            const paginator = paginatorOf(result);

            renderEmployeeRows(rows);

            if (paginator) {
                state.employeePage = Number(paginator.current_page || 1);
                state.employeePerPage = Number(paginator.per_page || state.employeePerPage);

                if (elements.employeePerPage) {
                    elements.employeePerPage.value = String(state.employeePerPage);
                }

                renderPageInfo(elements.employeePageInfo, paginator, 'nhân viên');
                renderPagination(elements.employeePagination, paginator, 'employee');
            } else {
                elements.employeePageInfo.textContent =
                    `Hiển thị ${rows.length} trên ${rows.length} nhân viên`;
                elements.employeePagination.innerHTML = '';
            }

            elements.employeeUpdated.textContent =
                `Cập nhật lúc ${new Date().toLocaleTimeString('vi-VN', {
                    hour: '2-digit',
                    minute: '2-digit',
                })}`;
        } catch (error) {
            if (error.name === 'AbortError') return;

            console.error(error);

            elements.employeeTbody.innerHTML = `
                <tr>
                    <td colspan="11" class="text-center text-danger py-5">
                        ${escapeHtml(error.message)}
                    </td>
                </tr>
            `;

            elements.employeePageInfo.textContent = 'Hiển thị 0 trên 0 nhân viên';
            elements.employeePagination.innerHTML = '';
        }
    }

    function employeeFromRow(row) {
        return {
            ma_nv: row.dataset.maNv,
            ho_ten: row.children[2]?.textContent.trim() || row.dataset.maNv,
        };
    }

    function selectEmployee(employee) {
        state.selectedEmployee = employee;
        state.attendancePage = 1;
        state.selectedAttendanceId = null;
        state.selectedAttendanceRow = null;

        elements.updateButton.disabled = true;

        elements.selectedEmployeeBadge.textContent =
            `${employee.ma_nv} · ${employee.ho_ten}`;

        const periodLabel =
            `${String(state.filters.thang).padStart(2, '0')}/${state.filters.nam}`;

        const tableTitle =
            document.getElementById('table-title');

        if (tableTitle) {
            tableTitle.textContent =
                `Bảng chấm công tháng ${periodLabel}`;
        }

        elements.attendanceDescription.textContent =
            `Dữ liệu chấm công của ${employee.ho_ten} (${employee.ma_nv}) trong kỳ ${periodLabel}.`;

        elements.employeeTbody
            .querySelectorAll('[data-employee-row]')
            .forEach((row) => {
                const selected = row.dataset.maNv === employee.ma_nv;
                row.classList.toggle('table-primary', selected);

                const radio = row.querySelector('.employee-radio');
                if (radio) radio.checked = selected;
            });

        loadAttendance(1);
    }

    function normalizeAttendance(item) {
        const hours = Number(item.so_gio_lam ?? 0);

        return {
            ma_cc: item.ma_cc ?? item.id ?? item.ma_cham_cong ?? '',
            ma_nv: item.ma_nv ?? state.selectedEmployee?.ma_nv ?? '',
            ngay_lam: item.ngay_lam ?? item.ngay ?? '',
            so_gio_lam: hours,
            vao_muon: Number(item.vao_muon ?? 0),
            ve_som: Number(item.ve_som ?? 0),
            ngay_cong: Number(item.ngay_cong ?? item.so_ngay_cong ?? workday(hours)),
        };
    }

    function statusBadge(item) {
        if (item.so_gio_lam >= 8) {
            return `
                <span
                    class="badge rounded-pill text-bg-success attendance-status-badge"
                    title="Đủ từ 8 giờ làm việc, được quy đổi 1 ngày công."
                >
                    Đủ công
                </span>
            `;
        }

        if (item.so_gio_lam >= 4) {
            return `
                <span
                    class="badge rounded-pill text-bg-warning attendance-status-badge"
                    title="Từ 4 đến dưới 8 giờ, được quy đổi 0,5 ngày công."
                >
                    Nửa công
                </span>
            `;
        }

        return `
            <span
                class="badge rounded-pill text-bg-secondary attendance-status-badge"
                title="Dưới 4 giờ làm việc nên chưa được quy đổi ngày công."
            >
                Thiếu công
            </span>
        `;
    }

    function renderAttendanceRows(rows) {
        if (!rows.length) {
            elements.attendanceTbody.innerHTML = `
                <tr>
                    <td colspan="10" class="text-center text-secondary py-5">
                        Nhân viên này chưa có dữ liệu chấm công trong kỳ.
                    </td>
                </tr>
            `;
            return;
        }

        elements.attendanceTbody.innerHTML = rows.map((raw) => {
            const item = normalizeAttendance(raw);

            return `
                <tr data-attendance-row data-id="${escapeHtml(item.ma_cc)}">
                    <td>
                        <input class="form-check-input attendance-radio"
                               type="radio"
                               name="selected-attendance"
                               ${canAny(
                PERMISSION_CODES.UPDATE,
                PERMISSION_CODES.DELETE
            ) ? '' : 'disabled'}>
                    </td>
                    <td class="fw-semibold">${escapeHtml(item.ma_cc)}</td>
                    <td>${escapeHtml(item.ma_nv)}</td>
                    <td>${formatDate(item.ngay_lam)}</td>
                    <td>${weekday(item.ngay_lam)}</td>
                    <td class="text-end">
                        <input class="form-control form-control-sm text-end attendance-edit-input"
                               type="number" min="0" max="24" step="1"
                               data-field="so_gio_lam"
                               value="${escapeHtml(item.so_gio_lam)}"
                               disabled>
                    </td>
                    <td class="text-center">
                        <input class="form-check-input"
                               type="checkbox"
                               data-field="vao_muon"
                               ${item.vao_muon ? 'checked' : ''}
                               disabled>
                    </td>
                    <td class="text-center">
                        <input class="form-check-input"
                               type="checkbox"
                               data-field="ve_som"
                               ${item.ve_som ? 'checked' : ''}
                               disabled>
                    </td>
                    <td class="text-end fw-semibold">${number(item.ngay_cong)}</td>
                    <td>${statusBadge(item)}</td>
                </tr>
            `;
        }).join('');
    }

    function updateSummary(result, rows) {
        const summary =
            result?.summary ??
            result?.meta?.summary ??
            result?.data?.summary ??
            null;

        if (summary) {
            elements.totalHours.textContent =
                number(summary.tong_gio_lam ?? summary.total_hours ?? 0);

            elements.lateCount.textContent =
                number(summary.so_lan_vao_muon ?? summary.late_count ?? 0, 0);

            elements.earlyCount.textContent =
                number(summary.so_lan_ve_som ?? summary.early_count ?? 0, 0);

            elements.avgDays.textContent =
                number(summary.so_ngay_cham_cong ?? summary.workdays ?? 0);

            return;
        }

        const normalized = rows.map(normalizeAttendance);

        elements.totalHours.textContent = number(
            normalized.reduce((sum, x) => sum + x.so_gio_lam, 0)
        );

        elements.lateCount.textContent = number(
            normalized.filter((x) => x.vao_muon).length,
            0
        );

        elements.earlyCount.textContent = number(
            normalized.filter((x) => x.ve_som).length,
            0
        );

        elements.avgDays.textContent = number(
            normalized.reduce((sum, x) => sum + x.ngay_cong, 0)
        );
    }

    async function loadAttendance(page = 1) {
        if (
            !can(PERMISSION_CODES.READ) ||
            !state.selectedEmployee?.ma_nv
        ) {
            return;
        }

        state.attendancePage = Math.max(Number(page) || 1, 1);
        state.selectedAttendanceId = null;
        state.selectedAttendanceRow = null;
        elements.updateButton.disabled = true;

        state.attendanceAbortController?.abort();
        state.attendanceAbortController = new AbortController();

        elements.attendanceTbody.innerHTML = `
            <tr>
                <td colspan="10" class="text-center text-secondary py-5">
                    <span class="spinner-border spinner-border-sm me-2"></span>
                    Đang tải bảng chấm công...
                </td>
            </tr>
        `;

        try {
            const response = await fetch(attendanceUrl(), {
                headers: {
                    Accept: 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                },
                signal: state.attendanceAbortController.signal,
            });

            const contentType = response.headers.get('content-type') || '';

            if (!contentType.includes('application/json')) {
                throw new Error(`API chấm công không trả JSON. HTTP ${response.status}`);
            }

            const result = await response.json();

            if (!response.ok || result.success === false) {
                throw new Error(result.message || 'Không tải được bảng chấm công.');
            }

            const rows = extractRows(result);
            const paginator = paginatorOf(result);

            renderAttendanceRows(rows);
            updateSummary(result, rows);

            if (paginator) {
                state.attendancePage = Number(paginator.current_page || 1);
                state.attendancePerPage =
                    Number(paginator.per_page || state.attendancePerPage);

                if (elements.attendancePerPage) {
                    elements.attendancePerPage.value =
                        String(state.attendancePerPage);
                }

                renderPageInfo(elements.attendancePageInfo, paginator, 'bản ghi');
                renderPagination(elements.attendancePagination, paginator, 'attendance');
            } else {
                elements.attendancePageInfo.textContent =
                    `Hiển thị ${rows.length} trên ${rows.length} bản ghi`;

                elements.attendancePagination.innerHTML = '';
            }
        } catch (error) {
            if (error.name === 'AbortError') return;

            console.error(error);

            elements.attendanceTbody.innerHTML = `
                <tr>
                    <td colspan="10" class="text-center text-danger py-5">
                        ${escapeHtml(error.message)}
                    </td>
                </tr>
            `;

            elements.attendancePageInfo.textContent = 'Hiển thị 0 trên 0 bản ghi';
            elements.attendancePagination.innerHTML = '';
        }
    }

    function clearSelectedAttendance() {
        state.selectedAttendanceId = null;
        state.selectedAttendanceRow = null;

        if (elements.updateButton) {
            elements.updateButton.disabled = true;
        }

        if (elements.deleteButton) {
            elements.deleteButton.disabled = true;
        }
    }

    function selectAttendance(row) {
        if (
            !canAny(
                PERMISSION_CODES.UPDATE,
                PERMISSION_CODES.DELETE
            )
        ) {
            return;
        }

        state.selectedAttendanceId =
            row.dataset.id;

        state.selectedAttendanceRow =
            row;

        elements.attendanceTbody
            .querySelectorAll('[data-attendance-row]')
            .forEach((currentRow) => {
                const selected =
                    currentRow === row;

                currentRow.classList.toggle(
                    'table-primary',
                    selected
                );

                const radio =
                    currentRow.querySelector(
                        '.attendance-radio'
                    );

                if (radio) {
                    radio.checked = selected;
                }

                currentRow
                    .querySelectorAll('[data-field]')
                    .forEach((control) => {
                        control.disabled = !(
                            selected &&
                            can(PERMISSION_CODES.UPDATE)
                        );
                    });
            });

        if (elements.updateButton) {
            elements.updateButton.disabled =
                !can(PERMISSION_CODES.UPDATE);
        }

        if (elements.deleteButton) {
            elements.deleteButton.disabled =
                !can(PERMISSION_CODES.DELETE);
        }
    }

    async function updateSelectedAttendance() {
        if (
            !guard(
                PERMISSION_CODES.UPDATE,
                'cập nhật chấm công'
            )
        ) {
            return;
        }

        const row = state.selectedAttendanceRow;

        if (!row || !state.selectedAttendanceId) return;

        const hoursInput = row.querySelector('[data-field="so_gio_lam"]');
        const lateInput = row.querySelector('[data-field="vao_muon"]');
        const earlyInput = row.querySelector('[data-field="ve_som"]');

        const hours = Number(hoursInput.value);

        if (!Number.isFinite(hours) || hours < 0 || hours > 24) {
            window.alert('Số giờ làm phải nằm trong khoảng 0 đến 24.');
            return;
        }

        const oldLabel = elements.updateButton.textContent;

        elements.updateButton.disabled = true;
        elements.updateButton.textContent = 'Đang cập nhật...';

        try {
            await requestJson(
                `${CHAM_CONG_API_URL}/${encodeURIComponent(state.selectedAttendanceId)}`,
                {
                    method: 'PUT',
                    body: JSON.stringify({
                        so_gio_lam: hours,
                        vao_muon: lateInput.checked ? 1 : 0,
                        ve_som: earlyInput.checked ? 1 : 0,
                    }),
                }
            );

            await Promise.all([
                loadAttendance(state.attendancePage),
                loadEmployees(state.employeePage),
            ]);
        } catch (error) {
            console.error(error);
            window.alert(error.message);
        } finally {
            elements.updateButton.textContent = oldLabel;
        }
    }

    async function deleteSelectedAttendance() {
        if (
            !guard(
                PERMISSION_CODES.DELETE,
                'xóa chấm công'
            )
        ) {
            return;
        }

        if (!state.selectedAttendanceId) {
            return;
        }

        const confirmed = window.confirm(
            'Bạn có chắc muốn xóa bản ghi chấm công đã chọn không?'
        );

        if (!confirmed) {
            return;
        }

        const oldLabel =
            elements.deleteButton?.textContent ||
            'Xóa chấm công';

        if (elements.deleteButton) {
            elements.deleteButton.disabled = true;
            elements.deleteButton.textContent = 'Đang xóa...';
        }

        try {
            await requestJson(
                `${CHAM_CONG_API_URL}/${encodeURIComponent(
                    state.selectedAttendanceId
                )}`,
                {
                    method: 'DELETE',
                }
            );

            await Promise.all([
                loadAttendance(state.attendancePage),
                loadEmployees(state.employeePage),
            ]);
        } catch (error) {
            console.error(error);
            window.alert(error.message);
        } finally {
            if (elements.deleteButton) {
                elements.deleteButton.textContent = oldLabel;
            }
        }
    }

    function applyFilters() {
        if (!can(PERMISSION_CODES.READ)) {
            return;
        }

        syncFilters();

        state.employeePage = 1;
        state.attendancePage = 1;

        loadEmployees(1);

        if (state.selectedEmployee) {
            loadAttendance(1);
        }
    }

    function clearFilters() {
        clearTimeout(searchTimer);

        if (elements.search) elements.search.value = '';
        if (elements.department) elements.department.value = '';

        initMonthYear();
        applyFilters();
    }

    function paginationClick(event) {
        const button = event.target.closest(
            'button[data-page][data-pagination-type]'
        );

        if (!button || button.disabled) return;

        const page = Number(button.dataset.page);

        if (!Number.isInteger(page) || page < 1) return;

        if (button.dataset.paginationType === 'employee') {
            loadEmployees(page);
        } else {
            loadAttendance(page);
        }
    }


    function getCsrfToken() {
        return document
            .querySelector(
                'meta[name="csrf-token"]'
            )
            ?.getAttribute(
                'content'
            ) || null;
    }

    function formatFileSize(bytes) {
        const size =
            Number(bytes || 0);

        if (size < 1024) {
            return `${size} B`;
        }

        if (size < 1024 * 1024) {
            return `${(
                size / 1024
            ).toFixed(1)} KB`;
        }

        return `${(
            size /
            (1024 * 1024)
        ).toFixed(1)} MB`;
    }

    function clearImportMessage() {
        if (elements.importMessage) {
            elements.importMessage.textContent = '';
            elements.importMessage.hidden = true;
        }

        if (elements.importSuccess) {
            elements.importSuccess.textContent = '';
            elements.importSuccess.hidden = true;
        }
    }

    function showImportError(message) {
        if (!elements.importMessage) {
            return;
        }

        if (elements.importSuccess) {
            elements.importSuccess.hidden = true;
        }

        elements.importMessage.textContent =
            message;

        elements.importMessage.hidden =
            false;
    }

    function showImportSuccess(message) {
        if (!elements.importSuccess) {
            return;
        }

        if (elements.importMessage) {
            elements.importMessage.hidden = true;
        }

        elements.importSuccess.textContent =
            message;

        elements.importSuccess.hidden =
            false;
    }

    function resetImportFile() {
        state.selectedImportFile =
            null;

        if (elements.importFile) {
            elements.importFile.value =
                '';
        }

        if (elements.importSelected) {
            elements.importSelected.hidden =
                true;
        }

        if (elements.importFileName) {
            elements.importFileName.textContent =
                'Chưa chọn file';
        }

        if (elements.importFileSize) {
            elements.importFileSize.textContent =
                '';
        }

        if (elements.importSubmit) {
            elements.importSubmit.disabled =
                true;
        }
    }

    function openImportDialog() {
        if (
            !guard(
                PERMISSION_CODES.INSERT,
                'nhập bảng chấm công'
            )
        ) {
            return;
        }

        clearImportMessage();
        resetImportFile();

        if (
            elements.importDialog &&
            !elements.importDialog.open
        ) {
            elements.importDialog.showModal();
        }
    }

    function closeImportDialog() {
        if (
            elements.importDialog?.open
        ) {
            elements.importDialog.close();
        }

        clearImportMessage();
        resetImportFile();
    }

    function selectImportFile(file) {
        if (!file) {
            resetImportFile();
            return;
        }

        const extension =
            file.name
                .split('.')
                .pop()
                ?.toLowerCase();

        if (
            !['xlsx', 'xls', 'csv'].includes(
                extension
            )
        ) {
            showImportError(
                'Chỉ hỗ trợ file XLSX, XLS hoặc CSV.'
            );

            resetImportFile();
            return;
        }

        if (
            file.size >
            5 * 1024 * 1024
        ) {
            showImportError(
                'File quá lớn. Dung lượng tối đa là 5MB.'
            );

            resetImportFile();
            return;
        }

        clearImportMessage();

        state.selectedImportFile =
            file;

        if (elements.importFileName) {
            elements.importFileName.textContent =
                file.name;
        }

        if (elements.importFileSize) {
            elements.importFileSize.textContent =
                formatFileSize(
                    file.size
                );
        }

        if (elements.importSelected) {
            elements.importSelected.hidden =
                false;
        }

        if (elements.importSubmit) {
            elements.importSubmit.disabled =
                false;
        }
    }

    async function downloadImportTemplate() {
        if (
            !guard(
                PERMISSION_CODES.INSERT,
                'tải file mẫu nhập chấm công'
            )
        ) {
            return;
        }

        clearImportMessage();

        const url =
            new URL(
                CHAM_CONG_IMPORT_TEMPLATE_API_URL,
                window.location.origin
            );

        url.searchParams.set(
            'format',
            'xlsx'
        );

        const oldLabel =
            elements
                .downloadImportTemplateLabel
                ?.textContent ||
            'Tải Excel mẫu';

        if (
            elements
                .downloadImportTemplateButton
        ) {
            elements
                .downloadImportTemplateButton
                .disabled =
                true;
        }

        if (
            elements
                .downloadImportTemplateLabel
        ) {
            elements
                .downloadImportTemplateLabel
                .textContent =
                'Đang tạo file...';
        }

        try {
            const response =
                await fetch(
                    url.toString(),
                    {
                        method:
                            'GET',

                        headers: {
                            Accept:
                                'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet,application/json',

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
                    `Không thể tải file mẫu. HTTP ${response.status}`;

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

                if (
                    response.status ===
                    401
                ) {
                    message =
                        'Phiên đăng nhập đã hết hạn.';
                }

                throw new Error(
                    message
                );
            }

            const blob =
                await response.blob();

            if (!blob.size) {
                throw new Error(
                    'File mẫu rỗng.'
                );
            }

            const filename =
                exportFilenameFromResponse(
                    response,
                    'mau_import_cham_cong.xlsx'
                );

            const objectUrl =
                URL.createObjectURL(
                    blob
                );

            const anchor =
                document.createElement(
                    'a'
                );

            anchor.href =
                objectUrl;

            anchor.download =
                filename;

            anchor.style.display =
                'none';

            document.body.appendChild(
                anchor
            );

            anchor.click();
            anchor.remove();

            window.setTimeout(
                () =>
                    URL.revokeObjectURL(
                        objectUrl
                    ),
                1000
            );

            showImportSuccess(
                'Đã tải file mẫu. Điền dữ liệu rồi chọn “Nhập file chấm công”.'
            );
        } catch (error) {
            console.error(
                'Download attendance import template failed:',
                error
            );

            showImportError(
                error.message
            );
        } finally {
            if (
                elements
                    .downloadImportTemplateButton
            ) {
                elements
                    .downloadImportTemplateButton
                    .disabled =
                    false;
            }

            if (
                elements
                    .downloadImportTemplateLabel
            ) {
                elements
                    .downloadImportTemplateLabel
                    .textContent =
                    oldLabel;
            }
        }
    }

    async function importAttendanceFile() {
        if (
            !guard(
                PERMISSION_CODES.INSERT,
                'nhập bảng chấm công'
            )
        ) {
            return;
        }

        const file =
            state.selectedImportFile;

        if (!file) {
            showImportError(
                'Vui lòng chọn file chấm công trước khi nhập.'
            );

            return;
        }

        clearImportMessage();

        const formData =
            new FormData();

        formData.append(
            'file',
            file
        );

        const csrfToken =
            getCsrfToken();

        const headers = {
            Accept:
                'application/json',

            'X-Requested-With':
                'XMLHttpRequest',
        };

        if (csrfToken) {
            headers['X-CSRF-TOKEN'] =
                csrfToken;
        }

        const oldLabel =
            elements
                .importSubmitLabel
                ?.textContent ||
            'Nhập dữ liệu';

        if (elements.importSubmit) {
            elements.importSubmit.disabled =
                true;
        }

        if (
            elements.chooseImportFileButton
        ) {
            elements.chooseImportFileButton.disabled =
                true;
        }

        if (
            elements
                .downloadImportTemplateButton
        ) {
            elements
                .downloadImportTemplateButton
                .disabled =
                true;
        }

        if (
            elements.importSubmitLabel
        ) {
            elements.importSubmitLabel.textContent =
                'Đang nhập...';
        }

        try {
            /*
             * FormData:
             * Không set Content-Type thủ công.
             * Browser tự tạo multipart boundary.
             */
            const response =
                await fetch(
                    CHAM_CONG_IMPORT_API_URL,
                    {
                        method:
                            'POST',

                        headers,

                        credentials:
                            'same-origin',

                        body:
                        formData,
                    }
                );

            const contentType =
                response.headers.get(
                    'content-type'
                ) || '';

            let result =
                null;

            if (
                contentType.includes(
                    'application/json'
                )
            ) {
                result =
                    await response.json();
            }

            if (
                !response.ok ||
                result?.success ===
                false
            ) {
                if (
                    response.status ===
                    401
                ) {
                    throw new Error(
                        'Phiên đăng nhập đã hết hạn.'
                    );
                }

                if (
                    response.status ===
                    419
                ) {
                    throw new Error(
                        'CSRF token đã hết hạn. Vui lòng tải lại trang.'
                    );
                }

                const errorDetails =
                    result?.errors &&
                    typeof result.errors ===
                    'object'
                        ? Object.entries(
                            result.errors
                        )
                            .slice(0, 3)
                            .map(
                                ([row, messages]) =>
                                    `Dòng ${row}: ${
                                        Array.isArray(messages)
                                            ? messages.join(', ')
                                            : messages
                                    }`
                            )
                            .join(' | ')
                        : '';

                throw new Error(
                    [
                        result?.message ||
                        `Không thể nhập file. HTTP ${response.status}`,
                        errorDetails,
                    ]
                        .filter(Boolean)
                        .join(' — ')
                );
            }

            const data =
                result?.data ||
                {};

            const inserted =
                Number(
                    data.inserted ||
                    0
                );

            const duplicates =
                Number(
                    data.duplicates ||
                    0
                );

            const invalidRows =
                Number(
                    data.invalid_rows ||
                    0
                );

            showImportSuccess(
                [
                    result?.message ||
                    `Nhập thành công ${inserted} bản ghi.`,
                    duplicates > 0
                        ? `Trùng: ${duplicates}`
                        : null,
                    invalidRows > 0
                        ? `Không hợp lệ: ${invalidRows}`
                        : null,
                ]
                    .filter(Boolean)
                    .join(' · ')
            );

            resetImportFile();

            /*
             * Refresh UI ngay sau khi import.
             */
            if (
                can(
                    PERMISSION_CODES.READ
                )
            ) {
                await loadEmployees(
                    state.employeePage
                );

                if (
                    state.selectedEmployee
                ) {
                    await loadAttendance(
                        state.attendancePage
                    );
                }
            }
        } catch (error) {
            console.error(
                'Import attendance failed:',
                error
            );

            showImportError(
                error.message
            );
        } finally {
            if (
                elements.chooseImportFileButton
            ) {
                elements.chooseImportFileButton.disabled =
                    false;
            }

            if (
                elements
                    .downloadImportTemplateButton
            ) {
                elements
                    .downloadImportTemplateButton
                    .disabled =
                    false;
            }

            if (
                elements.importSubmitLabel
            ) {
                elements.importSubmitLabel.textContent =
                    oldLabel;
            }

            if (
                elements.importSubmit &&
                state.selectedImportFile
            ) {
                elements.importSubmit.disabled =
                    false;
            }
        }
    }

    function initializeExportMonthOptions() {
        if (!elements.exportMonth) {
            return;
        }

        elements.exportMonth.innerHTML = '';

        for (let month = 1; month <= 12; month++) {
            const option =
                document.createElement('option');

            option.value =
                String(month);

            option.textContent =
                `Tháng ${month}`;

            elements.exportMonth.appendChild(
                option
            );
        }
    }

    function clearExportMessage() {
        if (!elements.exportMessage) {
            return;
        }

        elements.exportMessage.textContent = '';
        elements.exportMessage.hidden = true;
    }

    function showExportMessage(message) {
        if (!elements.exportMessage) {
            return;
        }

        elements.exportMessage.textContent =
            message;

        elements.exportMessage.hidden =
            false;
    }

    function openExportDialog() {
        if (
            !guard(
                PERMISSION_CODES.READ,
                'xuất bảng chấm công'
            )
        ) {
            return;
        }

        clearExportMessage();

        /*
         * Lấy kỳ hiện tại trên màn hình làm default.
         * User vẫn có thể đổi tháng/năm trong popup.
         */
        const currentMonth =
            Number(elements.month?.value) ||
            new Date().getMonth() + 1;

        const currentYear =
            Number(elements.year?.value) ||
            new Date().getFullYear();

        if (elements.exportMonth) {
            elements.exportMonth.value =
                String(currentMonth);
        }

        if (elements.exportYear) {
            elements.exportYear.value =
                String(currentYear);
        }

        if (elements.exportFormat) {
            elements.exportFormat.value =
                'xlsx';
        }

        if (
            elements.exportDialog &&
            !elements.exportDialog.open
        ) {
            elements.exportDialog.showModal();
        }
    }

    function closeExportDialog() {
        if (
            elements.exportDialog?.open
        ) {
            elements.exportDialog.close();
        }

        clearExportMessage();
    }

    function getExportFormValue() {
        const month =
            Number(
                elements.exportMonth?.value
            );

        const year =
            Number(
                elements.exportYear?.value
            );

        const format =
            elements.exportFormat?.value === 'csv'
                ? 'csv'
                : 'xlsx';

        if (
            !Number.isInteger(month) ||
            month < 1 ||
            month > 12
        ) {
            throw new Error(
                'Vui lòng chọn tháng hợp lệ từ 1 đến 12.'
            );
        }

        if (
            !Number.isInteger(year) ||
            year < 2000 ||
            year > 2100
        ) {
            throw new Error(
                'Năm phải nằm trong khoảng 2000 đến 2100.'
            );
        }

        return {
            month,
            year,
            format,
        };
    }

    function exportFilenameFromResponse(
        response,
        fallbackName
    ) {
        const disposition =
            response.headers.get(
                'content-disposition'
            ) || '';

        const utf8Match =
            disposition.match(
                /filename\*=UTF-8''([^;]+)/i
            );

        if (utf8Match?.[1]) {
            try {
                return decodeURIComponent(
                    utf8Match[1]
                        .trim()
                        .replace(/^["']|["']$/g, '')
                );
            } catch (_) {
                // fallback bên dưới
            }
        }

        const filenameMatch =
            disposition.match(
                /filename="?([^";]+)"?/i
            );

        return filenameMatch?.[1]
                ?.trim() ||
            fallbackName;
    }

    async function exportAttendance(event) {
        event?.preventDefault();

        if (
            !guard(
                PERMISSION_CODES.READ,
                'xuất bảng chấm công'
            )
        ) {
            return;
        }

        let exportValue;

        try {
            exportValue =
                getExportFormValue();
        } catch (error) {
            showExportMessage(
                error.message
            );

            return;
        }

        clearExportMessage();

        const url =
            new URL(
                CHAM_CONG_EXPORT_API_URL,
                window.location.origin
            );

        url.searchParams.set(
            'thang',
            String(exportValue.month)
        );

        url.searchParams.set(
            'nam',
            String(exportValue.year)
        );

        url.searchParams.set(
            'format',
            exportValue.format
        );

        const oldLabel =
            elements.exportSubmitLabel
                ?.textContent ||
            'Xuất file';

        if (elements.exportSubmit) {
            elements.exportSubmit.disabled =
                true;
        }

        if (elements.exportMonth) {
            elements.exportMonth.disabled =
                true;
        }

        if (elements.exportYear) {
            elements.exportYear.disabled =
                true;
        }

        if (elements.exportFormat) {
            elements.exportFormat.disabled =
                true;
        }

        if (elements.exportSubmitLabel) {
            elements.exportSubmitLabel.textContent =
                'Đang xuất...';
        }

        try {
            const response =
                await fetch(
                    url.toString(),
                    {
                        method: 'GET',

                        headers: {
                            Accept:
                                exportValue.format === 'csv'
                                    ? 'text/csv,application/json'
                                    : 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet,application/json',

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
                    `Không thể xuất bảng chấm công. HTTP ${response.status}`;

                if (
                    contentType.includes(
                        'application/json'
                    )
                ) {
                    const errorResult =
                        await response.json();

                    message =
                        errorResult.message ||
                        message;
                }

                if (response.status === 401) {
                    message =
                        'Phiên đăng nhập đã hết hạn. Vui lòng đăng nhập lại.';
                }

                if (response.status === 403) {
                    message =
                        'Bạn không có quyền xuất bảng chấm công.';
                }

                throw new Error(
                    message
                );
            }

            const blob =
                await response.blob();

            if (!blob.size) {
                throw new Error(
                    'File xuất rỗng.'
                );
            }

            const fallbackName =
                `cham_cong_${exportValue.month}_${exportValue.year}.${exportValue.format}`;

            const filename =
                exportFilenameFromResponse(
                    response,
                    fallbackName
                );

            const objectUrl =
                URL.createObjectURL(
                    blob
                );

            const anchor =
                document.createElement(
                    'a'
                );

            anchor.href =
                objectUrl;

            anchor.download =
                filename;

            anchor.style.display =
                'none';

            document.body.appendChild(
                anchor
            );

            anchor.click();
            anchor.remove();

            window.setTimeout(
                () => {
                    URL.revokeObjectURL(
                        objectUrl
                    );
                },
                1000
            );

            closeExportDialog();
        } catch (error) {
            console.error(
                'Export attendance failed:',
                error
            );

            showExportMessage(
                error.message
            );
        } finally {
            if (elements.exportSubmit) {
                elements.exportSubmit.disabled =
                    false;
            }

            if (elements.exportMonth) {
                elements.exportMonth.disabled =
                    false;
            }

            if (elements.exportYear) {
                elements.exportYear.disabled =
                    false;
            }

            if (elements.exportFormat) {
                elements.exportFormat.disabled =
                    false;
            }

            if (elements.exportSubmitLabel) {
                elements.exportSubmitLabel.textContent =
                    oldLabel;
            }
        }
    }

    elements.employeeTbody.addEventListener('click', (event) => {
        const row = event.target.closest('[data-employee-row]');
        if (!row) return;

        selectEmployee(employeeFromRow(row));
    });

    elements.attendanceTbody.addEventListener('click', (event) => {
        const row = event.target.closest('[data-attendance-row]');
        if (!row) return;

        selectAttendance(row);
    });

    elements.employeePagination?.addEventListener('click', paginationClick);
    elements.attendancePagination?.addEventListener('click', paginationClick);

    elements.employeePerPage?.addEventListener('change', () => {
        const value = Number(elements.employeePerPage.value);

        if (!Number.isInteger(value) || value < 1) return;

        state.employeePerPage = value;
        loadEmployees(1);
    });

    elements.attendancePerPage?.addEventListener('change', () => {
        const value = Number(elements.attendancePerPage.value);

        if (!Number.isInteger(value) || value < 1) return;

        state.attendancePerPage = value;

        if (state.selectedEmployee) {
            loadAttendance(1);
        }
    });

    elements.search?.addEventListener('input', () => {
        clearTimeout(searchTimer);
        searchTimer = setTimeout(applyFilters, 350);
    });

    elements.search?.addEventListener('keydown', (event) => {
        if (event.key !== 'Enter') return;

        event.preventDefault();
        clearTimeout(searchTimer);
        applyFilters();
    });

    elements.month?.addEventListener('change', applyFilters);
    elements.year?.addEventListener('change', applyFilters);
    elements.department?.addEventListener('change', applyFilters);
    elements.clearFilterButton?.addEventListener('click', clearFilters);
    elements.updateButton?.addEventListener(
        'click',
        updateSelectedAttendance
    );

    elements.deleteButton?.addEventListener(
        'click',
        deleteSelectedAttendance
    );

    elements.importButton?.addEventListener(
        'click',
        openImportDialog
    );

    elements.importClose?.addEventListener(
        'click',
        closeImportDialog
    );

    elements.importCancel?.addEventListener(
        'click',
        closeImportDialog
    );

    elements.importDialog?.addEventListener(
        'click',
        (event) => {
            if (
                event.target ===
                elements.importDialog
            ) {
                closeImportDialog();
            }
        }
    );

    elements.downloadImportTemplateButton
        ?.addEventListener(
            'click',
            downloadImportTemplate
        );

    elements.chooseImportFileButton
        ?.addEventListener(
            'click',
            () => {
                if (
                    !guard(
                        PERMISSION_CODES.INSERT,
                        'nhập bảng chấm công'
                    )
                ) {
                    return;
                }

                clearImportMessage();
                elements.importFile?.click();
            }
        );

    elements.importFile?.addEventListener(
        'change',
        (event) => {
            if (
                !can(
                    PERMISSION_CODES.INSERT
                )
            ) {
                event.target.value = '';
                return;
            }

            const file =
                event.target.files?.[0];

            if (!file) {
                return;
            }

            selectImportFile(
                file
            );
        }
    );

    elements.importRemoveFile
        ?.addEventListener(
            'click',
            () => {
                clearImportMessage();
                resetImportFile();
            }
        );

    elements.importSubmit?.addEventListener(
        'click',
        importAttendanceFile
    );

    elements.exportButton?.addEventListener(
        'click',
        openExportDialog
    );

    elements.exportForm?.addEventListener(
        'submit',
        exportAttendance
    );

    elements.exportClose?.addEventListener(
        'click',
        closeExportDialog
    );

    elements.exportCancel?.addEventListener(
        'click',
        closeExportDialog
    );

    elements.exportDialog?.addEventListener(
        'click',
        (event) => {
            if (
                event.target ===
                elements.exportDialog
            ) {
                closeExportDialog();
            }
        }
    );

    async function initialize() {
        initMonthYear();
        initializeExportMonthOptions();
        syncFilters();

        try {
            /*
             * Auth user + toàn bộ quyền theo vai trò trước.
             */
            await loadAuthContext();

            if (elements.authLoading) {
                elements.authLoading.hidden = true;
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
                    elements.accessDenied.hidden = false;
                }

                return;
            }

            applyPermissionVisibility();

            const readOnly =
                can(PERMISSION_CODES.READ) &&
                !can(PERMISSION_CODES.INSERT) &&
                !can(PERMISSION_CODES.UPDATE) &&
                !can(PERMISSION_CODES.DELETE);

            if (elements.readOnlyBadge) {
                elements.readOnlyBadge.hidden =
                    !readOnly;
            }

            if (elements.noReadNotice) {
                elements.noReadNotice.hidden =
                    can(PERMISSION_CODES.READ);
            }

            /*
             * Không có Read thì không gọi lookup/list APIs.
             */
            if (!can(PERMISSION_CODES.READ)) {
                return;
            }

            await loadDepartments();

            syncFilters();

            await loadEmployees(1);
        } catch (error) {
            console.error(
                'Initialize attendance failed:',
                error
            );

            if (elements.authLoading) {
                elements.authLoading.hidden = true;
            }

            if (elements.accessDenied) {
                elements.accessDenied.hidden = false;
            }

            if (elements.accessDeniedMessage) {
                elements.accessDeniedMessage.textContent =
                    error.message;
            }
        }
    }

    initialize();
});
