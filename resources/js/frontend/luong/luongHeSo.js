document.addEventListener('DOMContentLoaded', () => {
    /*
     * Điền URL thật sau.
     *
     * API dự kiến:
     * GET /api/v1/luong/he-so-luong?ma_nv=NV001
     */
    const HE_SO_LUONG_API_URL =
        '/api/v1/luong/he-so-luong';

    const elements = {
        salaryTbody:
            document.getElementById('salary-tbody'),

        coefficientTbody:
            document.getElementById(
                'salary-coefficient-tbody'
            ),

        title:
            document.getElementById(
                'salary-coefficient-title'
            ),

        description:
            document.getElementById(
                'salary-coefficient-description'
            ),

        selectedEmployee:
            document.getElementById(
                'coefficient-selected-employee'
            ),

        info:
            document.getElementById(
                'coefficient-info'
            ),

        checkAll:
            document.getElementById(
                'coefficient-check-all'
            ),

        addButton:
            document.getElementById(
                'add-coefficient-btn'
            ),

        editButton:
            document.getElementById(
                'edit-coefficient-btn'
            ),

        deleteButton:
            document.getElementById(
                'delete-coefficient-btn'
            ),
    };

    if (
        !elements.salaryTbody ||
        !elements.coefficientTbody
    ) {
        return;
    }

    const state = {
        employeeCode: null,
        employeeName: null,
        selectedCoefficientId: null,
        abortController: null,
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
        if (!value) {
            return 'Không thời hạn';
        }

        const match = String(value).match(
            /^(\d{4})-(\d{2})-(\d{2})/
        );

        if (!match) {
            return escapeHtml(value);
        }

        return `${match[3]}/${match[2]}/${match[1]}`;
    }

    function formatCoefficient(value) {
        const number = Number(value);

        if (!Number.isFinite(number)) {
            return '—';
        }

        return number.toLocaleString('vi-VN', {
            minimumFractionDigits: 2,
            maximumFractionDigits: 2,
        });
    }

    function extractData(result) {
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

    function isCoefficientActive(item) {
        const today = new Date();
        today.setHours(0, 0, 0, 0);

        const startDate = item.tu_ngay
            ? new Date(`${item.tu_ngay}T00:00:00`)
            : null;

        const endDate = item.den_ngay
            ? new Date(`${item.den_ngay}T00:00:00`)
            : null;

        return (
            (!startDate || startDate <= today) &&
            (!endDate || endDate >= today)
        );
    }

    function renderInitial() {
        elements.coefficientTbody.innerHTML = `
            <tr>
                <td
                    colspan="7"
                    class="text-center text-secondary py-5"
                >
                    Chưa chọn nhân viên.
                </td>
            </tr>
        `;

        elements.info.textContent =
            'Hiển thị 0 bản ghi';

        elements.selectedEmployee.textContent =
            'Chưa chọn nhân viên';

        elements.addButton.disabled = true;
        elements.editButton.disabled = true;
        elements.deleteButton.disabled = true;
        elements.checkAll.disabled = true;
    }

    function renderLoading() {
        elements.coefficientTbody.innerHTML = `
            <tr>
                <td
                    colspan="7"
                    class="text-center text-secondary py-5"
                >
                    <span
                        class="spinner-border spinner-border-sm me-2"
                        aria-hidden="true"
                    ></span>
                    Đang tải hệ số lương...
                </td>
            </tr>
        `;

        elements.info.textContent =
            'Đang tải dữ liệu...';

        elements.editButton.disabled = true;
        elements.deleteButton.disabled = true;
        elements.checkAll.disabled = true;
    }

    function renderEmpty() {
        elements.coefficientTbody.innerHTML = `
            <tr>
                <td
                    colspan="7"
                    class="text-center text-secondary py-5"
                >
                    Nhân viên này chưa có lịch sử hệ số lương.
                </td>
            </tr>
        `;

        elements.info.textContent =
            'Hiển thị 0 bản ghi';

        elements.checkAll.disabled = true;
    }

    function renderError(message) {
        elements.coefficientTbody.innerHTML = `
            <tr>
                <td colspan="7" class="text-center py-5">
                    <div class="text-danger fw-semibold">
                        Không thể tải hệ số lương.
                    </div>

                    <div class="small text-secondary mt-1">
                        ${escapeHtml(message)}
                    </div>
                </td>
            </tr>
        `;

        elements.info.textContent =
            'Có lỗi xảy ra';

        elements.checkAll.disabled = true;
    }

    function renderRows(rows) {
        state.selectedCoefficientId = null;

        elements.editButton.disabled = true;
        elements.deleteButton.disabled = true;
        elements.checkAll.checked = false;

        if (!Array.isArray(rows) || rows.length === 0) {
            renderEmpty();
            return;
        }

        elements.coefficientTbody.innerHTML = rows
            .map((item) => {
                const active = isCoefficientActive(item);

                const statusHtml = active
                    ? `
                        <span class="badge rounded-pill
                                     text-bg-success-subtle
                                     text-success-emphasis
                                     border border-success-subtle">
                            Đang hiệu lực
                        </span>
                    `
                    : `
                        <span class="badge rounded-pill
                                     text-bg-light
                                     text-secondary
                                     border">
                            Hết hiệu lực
                        </span>
                    `;

                return `
                    <tr
                        data-coefficient-row
                        data-id="${escapeHtml(item.ma_ls)}"
                    >
                        <td>
                            <input
                                class="form-check-input coefficient-checkbox"
                                type="radio"
                                name="selected-coefficient"
                                value="${escapeHtml(item.ma_ls)}"
                                aria-label="Chọn hệ số ${escapeHtml(
                    item.ma_ls
                )}"
                            >
                        </td>

                        <td>
                            <span class="fw-semibold">
                                ${escapeHtml(item.ma_ls)}
                            </span>
                        </td>

                        <td class="text-end">
                            <span class="fw-semibold">
                                ${formatCoefficient(
                    item.he_so_luong
                )}
                            </span>
                        </td>

                        <td>
                            ${formatDate(item.tu_ngay)}
                        </td>

                        <td>
                            ${formatDate(item.den_ngay)}
                        </td>

                        <td>
                            ${statusHtml}
                        </td>

                        <td class="text-end">
                            <div class="d-inline-flex gap-1">
                                <button
                                    class="btn btn-outline-secondary
                                           btn-sm coefficient-action-btn"
                                    type="button"
                                    data-coefficient-action="edit"
                                    data-id="${escapeHtml(item.ma_ls)}"
                                >
                                    Sửa
                                </button>

                                <button
                                    class="btn btn-outline-danger
                                           btn-sm coefficient-action-btn"
                                    type="button"
                                    data-coefficient-action="delete"
                                    data-id="${escapeHtml(item.ma_ls)}"
                                >
                                    Xóa
                                </button>
                            </div>
                        </td>
                    </tr>
                `;
            })
            .join('');

        elements.info.textContent =
            `Hiển thị ${rows.length} bản ghi`;

        elements.checkAll.disabled = false;
    }

    function highlightSelectedEmployee(employeeCode) {
        elements.salaryTbody
            .querySelectorAll('tr')
            .forEach((row) => {
                row.classList.remove(
                    'salary-row-selected'
                );
            });

        const button = elements.salaryTbody.querySelector(
            `[data-salary-action="coefficient"]` +
            `[data-employee-code="${CSS.escape(employeeCode)}"]`
        );

        button
            ?.closest('tr')
            ?.classList.add('salary-row-selected');
    }

    async function loadEmployeeCoefficients(
        employeeCode,
        employeeName
    ) {
        if (!employeeCode) {
            return;
        }

        state.employeeCode = employeeCode;
        state.employeeName = employeeName;
        state.selectedCoefficientId = null;

        elements.selectedEmployee.textContent =
            `${employeeCode} · ${employeeName || ''}`;

        elements.description.textContent =
            `Lịch sử hệ số lương của ${
                employeeName || employeeCode
            }.`;

        elements.addButton.disabled = false;
        elements.editButton.disabled = true;
        elements.deleteButton.disabled = true;

        highlightSelectedEmployee(employeeCode);
        renderLoading();

        if (state.abortController) {
            state.abortController.abort();
        }

        state.abortController = new AbortController();

        try {
            const url = new URL(
                HE_SO_LUONG_API_URL,
                window.location.origin
            );

            url.searchParams.set(
                'ma_nv',
                employeeCode
            );

            const response = await fetch(
                url.toString(),
                {
                    method: 'GET',
                    headers: {
                        Accept: 'application/json',
                        'X-Requested-With':
                            'XMLHttpRequest',
                    },
                    signal:
                    state.abortController.signal,
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
                const responseText =
                    await response.text();

                console.error(
                    'API trả về HTML/text:',
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
                    `HTTP ${response.status}`
                );
            }

            if (result.success === false) {
                throw new Error(
                    result.message ||
                    'Không thể tải hệ số lương.'
                );
            }

            renderRows(extractData(result));
        } catch (error) {
            if (error.name === 'AbortError') {
                return;
            }

            console.error(
                'Error loading salary coefficients:',
                error
            );

            renderError(error.message);
        }
    }

    /*
     * Bắt nút Hệ số được render động bởi luong.js.
     */
    elements.salaryTbody.addEventListener(
        'click',
        (event) => {
            const button = event.target.closest(
                '[data-salary-action="coefficient"]'
            );

            if (!button) {
                return;
            }

            loadEmployeeCoefficients(
                button.dataset.employeeCode,
                button.dataset.employeeName
            );

            document
                .getElementById(
                    'salary-coefficient-card'
                )
                ?.scrollIntoView({
                    behavior: 'smooth',
                    block: 'start',
                });
        }
    );

    /*
     * Chọn một bản ghi hệ số.
     */
    elements.coefficientTbody.addEventListener(
        'change',
        (event) => {
            const checkbox = event.target.closest(
                '.coefficient-checkbox'
            );

            if (!checkbox) {
                return;
            }

            state.selectedCoefficientId =
                checkbox.value;

            elements.editButton.disabled = false;
            elements.deleteButton.disabled = false;

            elements.coefficientTbody
                .querySelectorAll(
                    '[data-coefficient-row]'
                )
                .forEach((row) => {
                    row.classList.toggle(
                        'coefficient-row-selected',
                        row.dataset.id ===
                        checkbox.value
                    );
                });
        }
    );

    /*
     * Các nút thao tác trong từng dòng.
     */
    elements.coefficientTbody.addEventListener(
        'click',
        (event) => {
            const button = event.target.closest(
                '[data-coefficient-action]'
            );

            if (!button) {
                return;
            }

            const action =
                button.dataset.coefficientAction;

            const coefficientId =
                button.dataset.id;

            document.dispatchEvent(
                new CustomEvent(
                    'salary-coefficient:action',
                    {
                        detail: {
                            action,
                            coefficientId,
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

    elements.addButton.addEventListener(
        'click',
        () => {
            document.dispatchEvent(
                new CustomEvent(
                    'salary-coefficient:action',
                    {
                        detail: {
                            action: 'create',
                            coefficientId: null,
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

    elements.editButton.addEventListener(
        'click',
        () => {
            if (!state.selectedCoefficientId) {
                return;
            }

            document.dispatchEvent(
                new CustomEvent(
                    'salary-coefficient:action',
                    {
                        detail: {
                            action: 'edit',
                            coefficientId:
                            state.selectedCoefficientId,
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

    elements.deleteButton.addEventListener(
        'click',
        () => {
            if (!state.selectedCoefficientId) {
                return;
            }

            document.dispatchEvent(
                new CustomEvent(
                    'salary-coefficient:action',
                    {
                        detail: {
                            action: 'delete',
                            coefficientId:
                            state.selectedCoefficientId,
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

    /*
     * File CRUD khác có thể phát event này
     * để tải lại bảng hệ số.
     */
    document.addEventListener(
        'salary-coefficient:data-changed',
        () => {
            if (!state.employeeCode) {
                return;
            }

            loadEmployeeCoefficients(
                state.employeeCode,
                state.employeeName
            );
        }
    );

    renderInitial();
});
