document.addEventListener('DOMContentLoaded', () => {
    const AUTH_ME_API_URL =
        '/api/v1/auth/me';

    const NGHI_PHEP_API_URL =
        '/api/v1/nghi-phep';

    const LOAI_PHEP_API_URL =
        '/api/v1/nghi-phep/loai-phep';

    const PHONG_BAN_API_URL =
        '/api/v1/nghi-phep/phong-ban';

    const CREATE_PERMISSIONS = Object.freeze([
        'NghiPhep.Insert',
        'NhanVien.Insert',
    ]);

    const READ_PERMISSIONS = Object.freeze([
        'NghiPhep.Read',
        'NhanVien.Read',
    ]);

    const UPDATE_PERMISSIONS = Object.freeze([
        'NghiPhep.Update',
        'NhanVien.Update',
    ]);

    const DELETE_PERMISSIONS = Object.freeze([
        'NghiPhep.Delete',
        'NhanVien.Delete',
    ]);

    const elements = {
        authLoading:
            document.getElementById(
                'leave-create-auth-loading'
            ),

        accessDenied:
            document.getElementById(
                'leave-create-access-denied'
            ),

        accessDeniedMessage:
            document.getElementById(
                'leave-create-access-denied-message'
            ),

        content:
            document.getElementById(
                'leave-create-content'
            ),

        form:
            document.getElementById(
                'leave-create-form'
            ),

        message:
            document.getElementById(
                'leave-create-message'
            ),

        success:
            document.getElementById(
                'leave-create-success'
            ),

        employeeCode:
            document.getElementById(
                'leave-employee-code'
            ),

        department:
            document.getElementById(
                'leave-department'
            ),

        departmentCode:
            document.getElementById(
                'leave-department-code'
            ),

        leaveType:
            document.getElementById(
                'leave-type'
            ),

        reason:
            document.getElementById(
                'leave-reason'
            ),

        reasonCounter:
            document.getElementById(
                'leave-reason-counter'
            ),

        fromDate:
            document.getElementById(
                'leave-from-date'
            ),

        toDate:
            document.getElementById(
                'leave-to-date'
            ),

        submit:
            document.getElementById(
                'leave-create-submit'
            ),

        submitLabel:
            document.getElementById(
                'leave-create-submit-label'
            ),

        formTitle:
            document.getElementById(
                'leave-form-title'
            ),

        formDescription:
            document.getElementById(
                'leave-form-description'
            ),

        editId:
            document.getElementById(
                'leave-edit-id'
            ),

        logCard:
            document.getElementById(
                'leave-create-log-card'
            ),

        logEmployee:
            document.getElementById(
                'leave-create-log-employee'
            ),

        logPendingTab:
            document.getElementById(
                'leave-create-pending-tab'
            ),

        logHistoryTab:
            document.getElementById(
                'leave-create-history-tab'
            ),

        logPendingCount:
            document.getElementById(
                'leave-create-pending-count'
            ),

        logHistoryCount:
            document.getElementById(
                'leave-create-history-count'
            ),

        logTbody:
            document.getElementById(
                'leave-create-log-tbody'
            ),

        logPageInfo:
            document.getElementById(
                'leave-create-log-page-info'
            ),

        logRefresh:
            document.getElementById(
                'leave-create-log-refresh'
            ),

        logFromDate:
            document.getElementById(
                'leave-log-from-date'
            ),

        logToDate:
            document.getElementById(
                'leave-log-to-date'
            ),

        logClearFilter:
            document.getElementById(
                'leave-log-clear-filter'
            ),

        clearButton:
            document.getElementById(
                'leave-create-clear'
            ),
    };

    const state = {
        user: null,
        permissions: new Set(),

        ownLeaveRows: [],
        ownLeaveTab: 'pending',

        formMode: 'create',
        editingLeaveId: null,
    };

    function csrfToken() {
        return document
            .querySelector(
                'meta[name="csrf-token"]'
            )
            ?.getAttribute(
                'content'
            ) || null;
    }

    function normalizeAuthResult(result) {
        const data =
            result?.data || {};

        if (
            data.user ||
            Array.isArray(
                data.permissions
            )
        ) {
            return {
                user:
                    data.user || null,

                permissions:
                    Array.isArray(
                        data.permissions
                    )
                        ? data.permissions
                        : [],
            };
        }

        return {
            user: {
                ma_nv:
                    data.ma_nv ?? null,

                ho_ten:
                    data.ho_ten ?? null,

                email:
                    data.email ?? null,

                ma_vt:
                    data.ma_vt ?? null,

                ma_pb:
                    data.ma_pb ?? null,

                ten_pb:
                    data.ten_pb ?? null,

                vai_tro:
                    data.vai_tro ?? null,
            },

            permissions:
                Array.isArray(
                    data.quyen
                )
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

    async function loadCurrentUserDepartment() {
        if (
            !state.user?.ma_pb ||
            !elements.department
        ) {
            elements.department.value =
                'Chưa xác định phòng ban';

            return;
        }

        try {
            const result =
                await requestJson(
                    PHONG_BAN_API_URL
                );

            const rows =
                extractRows(
                    result
                );

            const department =
                rows.find(
                    (item) =>
                        String(item.ma_pb) ===
                        String(state.user.ma_pb)
                );

            if (!department) {
                elements.department.value =
                    `Phòng ban #${state.user.ma_pb}`;

                return;
            }

            elements.department.value =
                department.ten_pb;

            if (elements.departmentCode) {
                elements.departmentCode.value =
                    String(
                        department.ma_pb
                    );
            }

        } catch (error) {
            console.error(
                'Không tải được phòng ban:',
                error
            );

            elements.department.value =
                `Phòng ban #${state.user.ma_pb}`;
        }
    }

    async function loadAuthContext() {
        const response =
            await fetch(
                AUTH_ME_API_URL,
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
            throw new Error(
                `API xác thực không trả JSON. HTTP ${response.status}`
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
                'Không thể xác thực người dùng.'
            );
        }

        const normalized =
            normalizeAuthResult(
                result
            );

        state.user =
            normalized.user;

        state.permissions =
            new Set(
                normalized.permissions
            );

        return normalized;
    }

    function canAnyPermission(permissions) {
        return permissions.some(
            (permission) =>
                state.permissions.has(
                    permission
                )
        );
    }

    function canCreateLeave() {
        return canAnyPermission(
            CREATE_PERMISSIONS
        );
    }

    function canReadOwnLeaveLog() {
        return canAnyPermission(
            READ_PERMISSIONS
        );
    }

    function canUpdateOwnLeave() {
        /*
         * Self-service:
         * user được phép sửa chính đơn Chờ duyệt của mình
         * nếu có quyền Update hoặc quyền tạo đơn.
         *
         * Backend vẫn phải kiểm tra:
         * - ma_nv = current user
         * - trang_thai_duyet = 0
         */
        return (
            canAnyPermission(
                UPDATE_PERMISSIONS
            ) ||
            canCreateLeave()
        );
    }

    function canDeleteOwnLeave() {
        /*
         * Self-service:
         * ưu tiên permission Delete.
         *
         * Fallback quyền tạo đơn giữ tương thích với role nhân viên
         * hiện tại đang dùng cho self-service.
         *
         * Backend bắt buộc vẫn phải kiểm tra ownership + pending.
         */
        return (
            canAnyPermission(
                DELETE_PERMISSIONS
            ) ||
            canCreateLeave()
        );
    }

    function showError(message) {
        if (elements.success) {
            elements.success.hidden =
                true;
        }

        if (!elements.message) {
            return;
        }

        elements.message.textContent =
            message;

        elements.message.hidden =
            false;
    }

    function showSuccess(message) {
        if (elements.message) {
            elements.message.hidden =
                true;
        }

        if (!elements.success) {
            return;
        }

        elements.success.textContent =
            message;

        elements.success.hidden =
            false;
    }

    function clearMessages() {
        if (elements.message) {
            elements.message.textContent =
                '';

            elements.message.hidden =
                true;
        }

        if (elements.success) {
            elements.success.textContent =
                '';

            elements.success.hidden =
                true;
        }
    }

    async function requestJson(
        url,
        options = {}
    ) {
        const method =
            String(
                options.method ||
                'GET'
            ).toUpperCase();

        const headers = {
            Accept:
                'application/json',

            'Content-Type':
                'application/json',

            'X-Requested-With':
                'XMLHttpRequest',

            ...(options.headers || {}),
        };

        const token =
            csrfToken();

        if (
            token &&
            !['GET', 'HEAD'].includes(
                method
            )
        ) {
            headers['X-CSRF-TOKEN'] =
                token;
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

            if (
                response.status === 401
            ) {
                throw new Error(
                    'Phiên đăng nhập đã hết hạn.'
                );
            }

            if (
                response.status === 403
            ) {
                throw new Error(
                    'Bạn không có quyền tạo đơn nghỉ phép.'
                );
            }

            if (
                response.status === 419
            ) {
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

    function extractRows(result) {
        if (
            Array.isArray(
                result
            )
        ) {
            return result;
        }

        if (
            Array.isArray(
                result?.data
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

    async function loadSelect(
        url,
        select,
        valueField,
        textField
    ) {
        if (!select) {
            return;
        }

        const firstOption =
            select.options[0]
                ?.outerHTML ||
            '<option value="">-- Chọn --</option>';

        select.innerHTML =
            firstOption;

        select.disabled =
            true;

        try {
            const result =
                await requestJson(
                    url
                );

            const rows =
                extractRows(
                    result
                );

            const fragment =
                document
                    .createDocumentFragment();

            rows.forEach(
                (item) => {
                    const value =
                        item?.[
                            valueField
                            ];

                    if (
                        value === null ||
                        value === undefined
                    ) {
                        return;
                    }

                    const option =
                        document
                            .createElement(
                                'option'
                            );

                    option.value =
                        String(
                            value
                        );

                    option.textContent =
                        item?.[
                            textField
                            ] ??
                        String(
                            value
                        );

                    fragment.appendChild(
                        option
                    );
                }
            );

            select.appendChild(
                fragment
            );
        } finally {
            select.disabled =
                false;
        }
    }

    async function loadLookups() {
        await loadSelect(
            LOAI_PHEP_API_URL,
            elements.leaveType,
            'ma_lp',
            'ten_lp'
        );
    }

    function populateCurrentEmployeeInfo() {
        if (elements.employeeCode) {
            elements.employeeCode.value =
                state.user?.ma_nv ||
                '';
        }

        if (elements.departmentCode) {
            elements.departmentCode.value =
                state.user?.ma_pb !== null &&
                state.user?.ma_pb !== undefined
                    ? String(
                        state.user.ma_pb
                    )
                    : '';
        }
    }

    function updateReasonCounter() {
        if (
            !elements.reason ||
            !elements.reasonCounter
        ) {
            return;
        }

        elements.reasonCounter.textContent =
            `${elements.reason.value.length} / 255`;
    }

    function buildPayload() {
        return {
            ma_nv:
                String(
                    state.user?.ma_nv ||
                    ''
                ),

            tu_ngay:
                elements.fromDate
                    ?.value ||
                null,

            den_ngay:
                elements.toDate
                    ?.value ||
                null,

            ma_lp:
                elements.leaveType
                    ?.value ||
                null,

            ly_do:
                elements.reason
                    ?.value
                    .trim() ||
                '',

            trang_thai_duyet:
                0,
        };
    }

    function validatePayload(payload) {
        if (!payload.ma_nv) {
            return 'Không xác định được mã nhân viên hiện tại.';
        }

        if (!payload.ma_lp) {
            return 'Vui lòng chọn loại nghỉ.';
        }

        if (!payload.ly_do) {
            return 'Vui lòng nhập lý do nghỉ.';
        }

        if (!payload.tu_ngay) {
            return 'Vui lòng chọn ngày bắt đầu.';
        }

        if (!payload.den_ngay) {
            return 'Vui lòng chọn ngày kết thúc.';
        }


        if (
            payload.den_ngay <
            payload.tu_ngay
        ) {
            return 'Ngày kết thúc không được nhỏ hơn ngày bắt đầu.';
        }

        return null;
    }

    function normalizeLeave(item) {
        return {
            ma_np: item.ma_np ?? item.id ?? '',
            ma_nv: item.ma_nv ?? '',
            tu_ngay: item.tu_ngay ?? '',
            den_ngay: item.den_ngay ?? '',
            ma_lp:
                item.ma_lp ??
                item.loai_phep?.ma_lp ??
                null,
            ten_lp: item.ten_lp ?? item.loai_phep?.ten_lp ?? 'Nghỉ phép',
            ly_do: item.ly_do ?? '',
            trang_thai_duyet: Number(item.trang_thai_duyet ?? 0),
        };
    }

    function formatDate(value) {
        if (!value) return '—';

        const match = String(value).match(
            /^(\d{4})-(\d{2})-(\d{2})/
        );

        if (!match) return String(value);

        return `${match[2]}/${match[3]}/${match[1]}`;
    }

    function escapeHtml(value) {
        return String(value ?? '')
            .replaceAll('&', '&amp;')
            .replaceAll('<', '&lt;')
            .replaceAll('>', '&gt;')
            .replaceAll('"', '&quot;')
            .replaceAll("'", '&#039;');
    }

    function ownLeaveStatusBadge(status) {
        if (status === 0) {
            return `<span class="badge rounded-pill text-bg-warning leave-create-log-status" title="Đơn nghỉ phép đang chờ phê duyệt.">Chờ duyệt</span>`;
        }

        if (status === 1) {
            return `<span class="badge rounded-pill text-bg-success leave-create-log-status" title="Đơn nghỉ phép đã được phê duyệt.">Đã duyệt</span>`;
        }

        return `<span class="badge rounded-pill text-bg-danger leave-create-log-status" title="Đơn nghỉ phép đã bị từ chối.">Từ chối</span>`;
    }

    function clearLogDateFilter() {
        if (elements.logFromDate) {
            elements.logFromDate.value =
                '';
        }

        if (elements.logToDate) {
            elements.logToDate.value =
                '';

            elements.logToDate.removeAttribute(
                'min'
            );
        }
    }

    function selectedLogDateRange() {
        const from =
            elements.logFromDate?.value ||
            null;

        const to =
            elements.logToDate?.value ||
            null;

        return {
            from,
            to,
            invalid:
                !!(
                    from &&
                    to &&
                    to < from
                ),
        };
    }

    function ownLeaveRowsInDateRange() {
        const range =
            selectedLogDateRange();

        if (range.invalid) {
            return [];
        }

        /*
         * Không chọn ngày => hiển thị toàn bộ log.
         */
        if (
            !range.from &&
            !range.to
        ) {
            return state.ownLeaveRows;
        }

        return state.ownLeaveRows.filter(
            (leave) => {
                const leaveFrom =
                    String(
                        leave.tu_ngay ||
                        ''
                    ).slice(0, 10);

                const leaveTo =
                    String(
                        leave.den_ngay ||
                        leave.tu_ngay ||
                        ''
                    ).slice(0, 10);

                if (
                    !leaveFrom ||
                    !leaveTo
                ) {
                    return false;
                }

                /*
                 * Khoảng nghỉ và khoảng filter có giao nhau.
                 *
                 * Nếu chỉ nhập Từ ngày:
                 *   leaveTo >= filterFrom
                 *
                 * Nếu chỉ nhập Đến ngày:
                 *   leaveFrom <= filterTo
                 */
                if (
                    range.from &&
                    leaveTo <
                    range.from
                ) {
                    return false;
                }

                if (
                    range.to &&
                    leaveFrom >
                    range.to
                ) {
                    return false;
                }

                return true;
            }
        );
    }

    function ownLeaveRowsForTab() {
        const periodRows =
            ownLeaveRowsInDateRange();

        if (
            state.ownLeaveTab ===
            'pending'
        ) {
            return periodRows.filter(
                (leave) =>
                    leave.trang_thai_duyet ===
                    0
            );
        }

        return periodRows.filter(
            (leave) =>
                leave.trang_thai_duyet !==
                0
        );
    }

    function updateOwnLeaveCounts() {
        const periodRows =
            ownLeaveRowsInDateRange();

        const pending =
            periodRows.filter(
                (leave) =>
                    leave.trang_thai_duyet ===
                    0
            ).length;

        const history =
            periodRows.length -
            pending;

        if (elements.logPendingCount) {
            elements.logPendingCount.textContent = String(pending);
        }

        if (elements.logHistoryCount) {
            elements.logHistoryCount.textContent = String(history);
        }
    }

    function renderOwnLeaveLog() {
        if (!elements.logTbody) return;

        const range =
            selectedLogDateRange();

        if (range.invalid) {
            if (elements.logPendingCount) {
                elements.logPendingCount.textContent =
                    '0';
            }

            if (elements.logHistoryCount) {
                elements.logHistoryCount.textContent =
                    '0';
            }

            if (elements.logPageInfo) {
                elements.logPageInfo.textContent =
                    'Khoảng ngày không hợp lệ';
            }

            elements.logTbody.innerHTML =
                `
                    <tr>
                        <td
                            colspan="7"
                            class="text-center text-danger py-5"
                        >
                            Đến ngày không được nhỏ hơn Từ ngày.
                        </td>
                    </tr>
                `;

            return;
        }

        updateOwnLeaveCounts();
        const rows = ownLeaveRowsForTab();

        if (elements.logPageInfo) {
            elements.logPageInfo.textContent = rows.length
                ? `Hiển thị ${rows.length} yêu cầu`
                : 'Hiển thị 0 yêu cầu';
        }

        if (!rows.length) {
            elements.logTbody.innerHTML = `
                <tr>
                    <td colspan="7" class="text-center text-secondary py-5">
                        ${state.ownLeaveTab === 'pending'
                ? 'Không có đơn nghỉ phép chờ duyệt.'
                : 'Chưa có đơn nghỉ phép được xử lý.'}
                    </td>
                </tr>
            `;
            return;
        }

        elements.logTbody.innerHTML = rows.map((leave) => `
            <tr>
                <td class="fw-semibold">${escapeHtml(leave.ma_np)}</td>
                <td>${formatDate(leave.tu_ngay)}</td>
                <td>${formatDate(leave.den_ngay)}</td>
                <td>${escapeHtml(leave.ten_lp)}</td>
                <td>
                    <span class="leave-create-log-reason"
                          title="${escapeHtml(leave.ly_do || 'Không có lý do')}">
                        ${escapeHtml(leave.ly_do || '—')}
                    </span>
                </td>
                <td>${ownLeaveStatusBadge(leave.trang_thai_duyet)}</td>
                <td class="text-end">
                    ${
            leave.trang_thai_duyet === 0 &&
            String(leave.ma_nv) === String(state.user?.ma_nv)
                ? `
                                <div class="leave-log-row-actions">
                                    ${
                    canUpdateOwnLeave()
                        ? `
                                                <button
                                                    class="btn btn-outline-secondary btn-icon-action leave-log-edit-btn"
                                                    type="button"
                                                    data-edit-leave-id="${escapeHtml(leave.ma_np)}"
                                                    aria-label="Sửa đơn nghỉ phép"
                                                    title="Sửa đơn nghỉ phép"
                                                >
                                                    <svg
                                                        aria-hidden="true"
                                                        width="13"
                                                        height="13"
                                                        viewBox="0 0 16 16"
                                                        fill="none"
                                                        stroke="currentColor"
                                                        stroke-width="1.5"
                                                        stroke-linecap="round"
                                                        stroke-linejoin="round"
                                                    >
                                                        <path d="M10.8 2.2 13.8 5.2"></path>
                                                        <path d="M3 13l1-3.5 7.5-7.5 3 3L7 12.5 3 13Z"></path>
                                                    </svg>
                                                </button>
                                            `
                        : ''
                }

                                    ${
                    canDeleteOwnLeave()
                        ? `
                                                <button
                                                    class="btn btn-outline-danger btn-icon-action leave-log-delete-btn"
                                                    type="button"
                                                    data-delete-leave-id="${escapeHtml(leave.ma_np)}"
                                                    aria-label="Xóa đơn nghỉ phép"
                                                    title="Xóa đơn nghỉ phép"
                                                >
                                                    <svg
                                                        aria-hidden="true"
                                                        width="13"
                                                        height="13"
                                                        viewBox="0 0 16 16"
                                                        fill="none"
                                                        stroke="currentColor"
                                                        stroke-width="1.5"
                                                        stroke-linecap="round"
                                                        stroke-linejoin="round"
                                                    >
                                                        <path d="M3 4.5h10"></path>
                                                        <path d="M6 2.5h4"></path>
                                                        <path d="M5 4.5l.5 9h5l.5-9"></path>
                                                        <path d="M7 7v4M9 7v4"></path>
                                                    </svg>
                                                </button>
                                            `
                        : ''
                }
                                </div>
                            `
                : '—'
        }
                </td>
            </tr>
        `).join('');
    }

    async function loadOwnLeaveLog() {
        if (!canReadOwnLeaveLog() || !state.user?.ma_nv) {
            if (elements.logCard) elements.logCard.hidden = true;
            return;
        }

        if (elements.logCard) elements.logCard.hidden = false;

        if (elements.logEmployee) {
            elements.logEmployee.textContent =
                `${state.user.ma_nv} · ${state.user.ho_ten || 'Nhân viên'}`;
        }

        if (elements.logTbody) {
            elements.logTbody.innerHTML = `
                <tr>
                    <td colspan="7" class="text-center text-secondary py-5">
                        <span class="spinner-border spinner-border-sm me-2" aria-hidden="true"></span>
                        Đang tải dữ liệu nghỉ phép...
                    </td>
                </tr>
            `;
        }

        const url = new URL(
            NGHI_PHEP_API_URL,
            window.location.origin
        );

        url.searchParams.set(
            'ma_nv',
            String(state.user.ma_nv)
        );

        const response = await fetch(
            url.toString(),
            {
                method: 'GET',
                headers: {
                    Accept: 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                },
                credentials: 'same-origin',
            }
        );

        const contentType = response.headers.get('content-type') || '';
        if (!contentType.includes('application/json')) {
            throw new Error(`API nghỉ phép không trả JSON. HTTP ${response.status}`);
        }

        const result = await response.json();
        if (!response.ok || result.success === false) {
            throw new Error(result.message || 'Không thể tải lịch sử nghỉ phép.');
        }

        state.ownLeaveRows = extractRows(result)
            .map(normalizeLeave)
            .filter(
                (leave) => String(leave.ma_nv) === String(state.user.ma_nv)
            );

        renderOwnLeaveLog();
    }

    function switchOwnLeaveTab(tab) {
        state.ownLeaveTab = tab;

        elements.logPendingTab?.classList.toggle(
            'active',
            tab === 'pending'
        );
        elements.logPendingTab?.setAttribute(
            'aria-selected',
            tab === 'pending' ? 'true' : 'false'
        );

        elements.logHistoryTab?.classList.toggle(
            'active',
            tab === 'history'
        );
        elements.logHistoryTab?.setAttribute(
            'aria-selected',
            tab === 'history' ? 'true' : 'false'
        );

        renderOwnLeaveLog();
    }

    function setCreateMode() {
        state.formMode =
            'create';

        state.editingLeaveId =
            null;

        if (elements.editId) {
            elements.editId.value =
                '';
        }

        if (elements.formTitle) {
            elements.formTitle.textContent =
                'Thông tin đơn nghỉ phép';
        }

        if (elements.formDescription) {
            elements.formDescription.innerHTML =
                'Các trường có dấu <span class="text-danger">*</span> là bắt buộc.';
        }

        if (elements.submitLabel) {
            elements.submitLabel.textContent =
                'Gửi đơn nghỉ phép';
        }
    }

    async function deleteOwnLeave(leaveId) {
        if (!canDeleteOwnLeave()) {
            showError(
                'Bạn không có quyền xóa đơn nghỉ phép.'
            );
            return;
        }

        const leave =
            state.ownLeaveRows.find(
                (item) =>
                    String(item.ma_np) ===
                    String(leaveId)
            );

        if (!leave) {
            showError(
                'Không tìm thấy đơn nghỉ phép cần xóa.'
            );
            return;
        }

        if (
            String(leave.ma_nv) !==
            String(state.user?.ma_nv)
        ) {
            showError(
                'Bạn chỉ được xóa đơn nghỉ phép của chính mình.'
            );
            return;
        }

        if (
            leave.trang_thai_duyet !==
            0
        ) {
            showError(
                'Chỉ đơn đang chờ duyệt mới được xóa.'
            );
            return;
        }

        const confirmed =
            window.confirm(
                `Bạn có chắc muốn xóa đơn nghỉ phép #${leave.ma_np} không?`
            );

        if (!confirmed) {
            return;
        }

        clearMessages();

        try {
            const result =
                await requestJson(
                    `${NGHI_PHEP_API_URL}/${encodeURIComponent(leave.ma_np)}`,
                    {
                        method:
                            'DELETE',
                    }
                );

            showSuccess(
                result.message ||
                'Xóa đơn nghỉ phép thành công.'
            );

            /*
             * Nếu form đang sửa đúng record vừa xóa
             * thì quay về create mode.
             */
            if (
                state.formMode ===
                'edit' &&
                String(
                    state.editingLeaveId
                ) ===
                String(
                    leave.ma_np
                )
            ) {
                clearLeaveForm();
            }

            await loadOwnLeaveLog();
        } catch (error) {
            console.error(
                'Delete leave failed:',
                error
            );

            showError(
                error.message
            );
        }
    }

    function openEditLeave(leaveId) {
        if (!canUpdateOwnLeave()) {
            showError(
                'Bạn không có quyền sửa đơn nghỉ phép.'
            );
            return;
        }

        const leave =
            state.ownLeaveRows.find(
                (item) =>
                    String(item.ma_np) ===
                    String(leaveId)
            );

        if (!leave) {
            showError(
                'Không tìm thấy đơn nghỉ phép cần sửa.'
            );
            return;
        }

        if (
            String(leave.ma_nv) !==
            String(state.user?.ma_nv)
        ) {
            showError(
                'Bạn chỉ được sửa đơn nghỉ phép của chính mình.'
            );
            return;
        }

        if (
            leave.trang_thai_duyet !==
            0
        ) {
            showError(
                'Chỉ đơn đang chờ duyệt mới được sửa.'
            );
            return;
        }

        clearMessages();

        state.formMode =
            'edit';

        state.editingLeaveId =
            String(leave.ma_np);

        if (elements.editId) {
            elements.editId.value =
                String(leave.ma_np);
        }

        if (elements.leaveType) {
            elements.leaveType.value =
                String(
                    leave.ma_lp ??
                    ''
                );
        }

        if (elements.reason) {
            elements.reason.value =
                leave.ly_do ??
                '';
        }

        if (elements.fromDate) {
            elements.fromDate.value =
                String(
                    leave.tu_ngay ??
                    ''
                ).slice(0, 10);
        }

        if (elements.toDate) {
            elements.toDate.value =
                String(
                    leave.den_ngay ??
                    ''
                ).slice(0, 10);

            if (
                elements.fromDate?.value
            ) {
                elements.toDate.min =
                    elements.fromDate.value;
            }
        }

        if (elements.formTitle) {
            elements.formTitle.textContent =
                `Sửa đơn nghỉ phép #${leave.ma_np}`;
        }

        if (elements.formDescription) {
            elements.formDescription.textContent =
                'Cập nhật thông tin đơn đang chờ duyệt.';
        }

        if (elements.submitLabel) {
            elements.submitLabel.textContent =
                'Lưu thay đổi';
        }

        updateReasonCounter();

        elements.content
            ?.scrollIntoView({
                behavior: 'smooth',
                block: 'start',
            });
    }

    function clearLeaveForm() {
        clearMessages();

        /*
         * Không reset:
         * - Mã nhân viên
         * - Phòng ban
         *
         * Vì đây là dữ liệu current user.
         */

        if (elements.leaveType) {
            elements.leaveType.value =
                '';
        }

        if (elements.reason) {
            elements.reason.value =
                '';
        }

        if (elements.fromDate) {
            elements.fromDate.value =
                '';
        }

        if (elements.toDate) {
            elements.toDate.value =
                '';

            elements.toDate.removeAttribute(
                'min'
            );
        }

        setCreateMode();
        updateReasonCounter();

        elements.leaveType
            ?.focus();
    }

    async function submitLeaveRequest(
        event
    ) {
        event.preventDefault();

        clearMessages();

        const isEdit =
            state.formMode ===
            'edit';

        if (
            isEdit &&
            !canUpdateOwnLeave()
        ) {
            showError(
                'Bạn không có quyền sửa đơn nghỉ phép.'
            );
            return;
        }

        if (
            !isEdit &&
            !canCreateLeave()
        ) {
            showError(
                'Bạn không có quyền tạo đơn nghỉ phép.'
            );
            return;
        }

        const payload =
            buildPayload();

        const validation =
            validatePayload(
                payload
            );

        if (validation) {
            showError(
                validation
            );
            return;
        }

        const leaveId =
            state.editingLeaveId;

        if (
            isEdit &&
            !leaveId
        ) {
            showError(
                'Không xác định được đơn nghỉ phép cần sửa.'
            );
            return;
        }

        if (elements.submit) {
            elements.submit.disabled =
                true;
        }

        if (elements.submitLabel) {
            elements.submitLabel.textContent =
                isEdit
                    ? 'Đang lưu...'
                    : 'Đang gửi...';
        }

        try {
            const result =
                await requestJson(
                    isEdit
                        ? `${NGHI_PHEP_API_URL}/${encodeURIComponent(leaveId)}`
                        : NGHI_PHEP_API_URL,
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

            showSuccess(
                result.message ||
                (
                    isEdit
                        ? 'Cập nhật đơn nghỉ phép thành công.'
                        : 'Tạo đơn nghỉ phép thành công.'
                )
            );

            clearLeaveForm();

            if (
                canReadOwnLeaveLog()
            ) {
                await loadOwnLeaveLog();
            }
        } catch (error) {
            console.error(
                isEdit
                    ? 'Update leave failed:'
                    : 'Create leave failed:',
                error
            );

            showError(
                error.message
            );
        } finally {
            if (elements.submit) {
                elements.submit.disabled =
                    false;
            }

            if (elements.submitLabel) {
                elements.submitLabel.textContent =
                    state.formMode ===
                    'edit'
                        ? 'Lưu thay đổi'
                        : 'Gửi đơn nghỉ phép';
            }
        }
    }

    async function initialize() {
        try {
            await loadAuthContext();

            if (elements.authLoading) {
                elements.authLoading.hidden =
                    true;
            }

            if (!canCreateLeave()) {
                if (elements.accessDenied) {
                    elements.accessDenied.hidden =
                        false;
                }

                return;
            }

            populateCurrentEmployeeInfo();


            if (
                !state.user?.ma_nv
            ) {
                throw new Error(
                    'Không lấy được mã nhân viên từ tài khoản hiện tại.'
                );
            }

            await Promise.all([
                loadLookups(),
                loadCurrentUserDepartment(),
            ]);

            if (elements.content) {
                elements.content.hidden =
                    false;
            }

            await loadOwnLeaveLog();
        } catch (error) {
            console.error(
                'Initialize leave create page failed:',
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

            if (
                elements.accessDeniedMessage
            ) {
                elements
                    .accessDeniedMessage
                    .textContent =
                    error.message;
            }
        }
    }

    elements.reason
        ?.addEventListener(
            'input',
            updateReasonCounter
        );

    elements.fromDate
        ?.addEventListener(
            'change',
            () => {
                if (
                    elements.toDate &&
                    elements.fromDate?.value
                ) {
                    elements.toDate.min =
                        elements.fromDate.value;
                }
            }
        );

    elements.form
        ?.addEventListener(
            'submit',
            submitLeaveRequest
        );

    elements.logPendingTab
        ?.addEventListener(
            'click',
            () => switchOwnLeaveTab('pending')
        );

    elements.logHistoryTab
        ?.addEventListener(
            'click',
            () => switchOwnLeaveTab('history')
        );

    elements.logRefresh
        ?.addEventListener(
            'click',
            () => loadOwnLeaveLog()
        );

    elements.logTbody
        ?.addEventListener(
            'click',
            (event) => {
                const editButton =
                    event.target.closest(
                        '[data-edit-leave-id]'
                    );

                if (editButton) {
                    openEditLeave(
                        editButton.dataset
                            .editLeaveId
                    );
                    return;
                }

                const deleteButton =
                    event.target.closest(
                        '[data-delete-leave-id]'
                    );

                if (deleteButton) {
                    deleteOwnLeave(
                        deleteButton.dataset
                            .deleteLeaveId
                    );
                }
            }
        );

    elements.logFromDate
        ?.addEventListener(
            'change',
            () => {
                if (
                    elements.logToDate
                ) {
                    if (
                        elements.logFromDate?.value
                    ) {
                        elements.logToDate.min =
                            elements.logFromDate.value;
                    } else {
                        elements.logToDate.removeAttribute(
                            'min'
                        );
                    }
                }

                renderOwnLeaveLog();
            }
        );

    elements.logToDate
        ?.addEventListener(
            'change',
            renderOwnLeaveLog
        );

    elements.logClearFilter
        ?.addEventListener(
            'click',
            () => {
                clearLogDateFilter();
                renderOwnLeaveLog();
            }
        );

    elements.clearButton
        ?.addEventListener(
            'click',
            clearLeaveForm
        );

    setCreateMode();
    updateReasonCounter();
    initialize();
});
