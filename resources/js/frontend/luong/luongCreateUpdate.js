document.addEventListener('DOMContentLoaded', () => {
    const LUONG_API_URL = '/api/v1/luong';

    const elements = {
        modal: document.getElementById('salary-modal'),
        form: document.getElementById('salary-form'),

        modalTitle: document.getElementById('salary-modal-title'),
        modalDescription: document.getElementById(
            'salary-modal-description'
        ),
        modalMessage: document.getElementById(
            'salary-modal-message'
        ),

        closeButton: document.getElementById(
            'salary-modal-close'
        ),
        cancelButton: document.getElementById(
            'salary-modal-cancel'
        ),
        submitButton: document.getElementById(
            'salary-modal-submit'
        ),

        createButton: document.getElementById(
            'create-salary-btn'
        ),

        tbody: document.getElementById('salary-tbody'),

        salaryId: document.getElementById('salary-id'),
        employeeCode: document.getElementById(
            'salary-employee-code'
        ),
        salaryPeriod: document.getElementById(
            'salary-period'
        ),
        bonus: document.getElementById('salary-bonus'),
        penalty: document.getElementById('salary-penalty'),
        insurance: document.getElementById(
            'salary-insurance'
        ),
        tax: document.getElementById('salary-tax'),

        month: document.getElementById(
            'salary-month-select'
        ),
        year:
            document.getElementById('salary-year-input') ??
            document.getElementById('salary-year-select'),
    };

    if (!elements.modal || !elements.form) {
        return;
    }

    const state = {
        mode: 'create',
        salaryId: null,
    };

    function toNumber(value) {
        const number = Number(value);

        return Number.isFinite(number) ? number : 0;
    }

    function getSelectedSalaryPeriod() {
        const month = Number(elements.month?.value);
        const year = Number(elements.year?.value);

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

        return `${year}-${String(month).padStart(2, '0')}-01`;
    }

    function getSelectedSalaryPeriodLabel() {
        const month = Number(elements.month?.value);
        const year = Number(elements.year?.value);

        if (
            !Number.isInteger(month) ||
            month < 1 ||
            month > 12 ||
            !Number.isInteger(year)
        ) {
            return '—';
        }

        return `${String(month).padStart(2, '0')}/${year}`;
    }

    function showMessage(message) {
        if (!elements.modalMessage) {
            return;
        }

        elements.modalMessage.textContent = message;
        elements.modalMessage.hidden = false;
    }

    function clearMessage() {
        if (!elements.modalMessage) {
            return;
        }

        elements.modalMessage.textContent = '';
        elements.modalMessage.hidden = true;
    }

    function setFieldsDisabled(disabled) {
        [
            elements.employeeCode,
            elements.bonus,
            elements.penalty,
            elements.insurance,
            elements.tax,
        ].forEach((field) => {
            if (field) {
                field.disabled = disabled;
            }
        });
    }

    function resetForm() {
        elements.form.reset();

        state.salaryId = null;

        elements.salaryId.value = '';
        elements.employeeCode.value = '';
        elements.salaryPeriod.value =
            getSelectedSalaryPeriodLabel() || '';

        elements.bonus.value = '0';
        elements.penalty.value = '0';
        elements.insurance.value = '0';
        elements.tax.value = '0';

        clearMessage();
    }

    function setModalMode(mode) {
        state.mode = mode;
        elements.modal.dataset.mode = mode;

        if (mode === 'create') {
            elements.modalTitle.textContent =
                'Thêm thông tin lương';

            elements.modalDescription.textContent =
                'Nhập thông tin lương cho nhân viên trong kỳ đã chọn.';

            elements.submitButton.textContent =
                'Thêm thông tin';

            setFieldsDisabled(false);

            elements.employeeCode.readOnly = false;
            elements.submitButton.hidden = false;
        }

        if (mode === 'edit') {
            elements.modalTitle.textContent =
                'Cập nhật thông tin lương';

            elements.modalDescription.textContent =
                'Chỉnh sửa thưởng, phạt, bảo hiểm và thuế.';

            elements.submitButton.textContent =
                'Lưu thay đổi';

            setFieldsDisabled(false);

            /*
             * Khi sửa không cho đổi nhân viên.
             */
            elements.employeeCode.readOnly = true;
            elements.submitButton.hidden = false;
        }

        if (mode === 'view') {
            elements.modalTitle.textContent =
                'Chi tiết thông tin lương';

            elements.modalDescription.textContent =
                'Thông tin lương của nhân viên trong kỳ.';

            setFieldsDisabled(true);

            elements.employeeCode.readOnly = true;
            elements.submitButton.hidden = true;
        }
    }

    function openCreateModal() {
        resetForm();
        setModalMode('create');

        elements.modal.showModal();

        elements.employeeCode.focus();
    }

    function closeModal() {
        if (elements.modal.open) {
            elements.modal.close();
        }

        clearMessage();
    }

    function extractResponseData(result) {
        if (
            result &&
            typeof result === 'object' &&
            result.data !== undefined
        ) {
            return result.data;
        }

        return result;
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

        const contentType =
            response.headers.get('content-type') || '';

        if (!contentType.includes('application/json')) {
            const text = await response.text();

            console.error('API trả về HTML/text:', text);

            throw new Error(
                `API không trả về JSON. HTTP ${response.status}`
            );
        }

        const result = await response.json();

        if (!response.ok || result.success === false) {
            throw new Error(
                result.message ||
                `Request thất bại. HTTP ${response.status}`
            );
        }

        return result;
    }

    function fillForm(salary) {
        state.salaryId = salary.ma_luong;

        elements.salaryId.value =
            salary.ma_luong ?? '';

        elements.employeeCode.value =
            salary.ma_nv ?? '';

        elements.salaryPeriod.value =
            getSelectedSalaryPeriodLabel() ?? '';

        elements.bonus.value =
            toNumber(salary.thuong);

        elements.penalty.value =
            toNumber(salary.phat);

        elements.insurance.value =
            toNumber(salary.bao_hiem);

        elements.tax.value =
            toNumber(salary.thue);
    }

    async function openExistingSalary(id, mode) {
        if (!id) {
            showMessage('Không tìm thấy mã lương.');
            return;
        }

        resetForm();
        setModalMode(mode);

        elements.modal.showModal();

        clearMessage();

        try {
            elements.modalTitle.textContent =
                'Đang tải thông tin...';

            const result = await requestJson(
                `${LUONG_API_URL}/${encodeURIComponent(id)}`
            );

            const salary = extractResponseData(result);

            fillForm(salary);
            setModalMode(mode);
        } catch (error) {
            console.error(error);

            showMessage(error.message);
        }
    }

    function buildPayload() {
        return {
            ma_nv: elements.employeeCode.value
                .trim()
                .toUpperCase(),

            ky_luong: getSelectedSalaryPeriod(),

            thuong: toNumber(elements.bonus.value),
            phat: toNumber(elements.penalty.value),
            bao_hiem: toNumber(elements.insurance.value),
            thue: toNumber(elements.tax.value),
        };
    }

    function validatePayload(payload) {
        if (!payload.ma_nv) {
            return 'Mã nhân viên không được để trống.';
        }

        if (!payload.ky_luong) {
            return 'Kỳ lương không hợp lệ.';
        }

        if (payload.ma_nv.length > 5) {
            return 'Mã nhân viên tối đa 5 ký tự.';
        }

        const moneyFields = [
            payload.thuong,
            payload.phat,
            payload.bao_hiem,
            payload.thue,
        ];

        if (moneyFields.some((value) => value < 0)) {
            return 'Các khoản tiền không được nhỏ hơn 0.';
        }

        return null;
    }

    async function submitSalaryForm(event) {
        event.preventDefault();

        if (state.mode === 'view') {
            return;
        }

        clearMessage();

        const payload = buildPayload();
        const validationMessage =
            validatePayload(payload);

        if (validationMessage) {
            showMessage(validationMessage);
            return;
        }

        const isEdit = state.mode === 'edit';

        const url = isEdit
            ? `${LUONG_API_URL}/${encodeURIComponent(
                state.salaryId
            )}`
            : LUONG_API_URL;

        const method = isEdit ? 'PUT' : 'POST';

        elements.submitButton.disabled = true;
        elements.submitButton.textContent =
            isEdit ? 'Đang lưu...' : 'Đang thêm...';

        try {
            await requestJson(url, {
                method,
                body: JSON.stringify(payload),
            });

            closeModal();

            /*
             * Báo cho luong.js tải lại danh sách.
             */
            document.dispatchEvent(
                new CustomEvent('salary:data-changed', {
                    detail: {
                        action: isEdit
                            ? 'updated'
                            : 'created',
                    },
                })
            );
        } catch (error) {
            console.error(error);
            showMessage(error.message);
        } finally {
            elements.submitButton.disabled = false;
            elements.submitButton.textContent =
                isEdit
                    ? 'Lưu thay đổi'
                    : 'Thêm thông tin';
        }
    }

    async function deleteSalary(id, employeeName) {
        if (!id) {
            return;
        }

        const confirmed = window.confirm(
            `Bạn có chắc muốn xóa thông tin lương của ${
                employeeName || 'nhân viên này'
            } không?`
        );

        if (!confirmed) {
            return;
        }

        try {
            await requestJson(
                `${LUONG_API_URL}/${encodeURIComponent(id)}`,
                {
                    method: 'DELETE',
                }
            );

            document.dispatchEvent(
                new CustomEvent('salary:data-changed', {
                    detail: {
                        action: 'deleted',
                    },
                })
            );
        } catch (error) {
            console.error(error);

            window.alert(error.message);
        }
    }

    elements.createButton?.addEventListener(
        'click',
        openCreateModal
    );

    elements.closeButton?.addEventListener(
        'click',
        closeModal
    );

    elements.cancelButton?.addEventListener(
        'click',
        closeModal
    );

    elements.form.addEventListener(
        'submit',
        submitSalaryForm
    );

    /*
     * Click bên ngoài modal để đóng.
     */
    elements.modal.addEventListener('click', (event) => {
        if (event.target === elements.modal) {
            closeModal();
        }
    });

    /*
     * View, edit, delete trong table.
     */
    elements.tbody?.addEventListener(
        'click',
        (event) => {
            const button = event.target.closest(
                '[data-salary-action]'
            );

            if (!button) {
                return;
            }

            const action =
                button.dataset.salaryAction;

            const id = button.dataset.id;

            if (action === 'view') {
                openExistingSalary(id, 'view');
                return;
            }

            if (action === 'edit') {
                openExistingSalary(id, 'edit');
                return;
            }

            if (action === 'delete') {
                deleteSalary(
                    id,
                    button.dataset.employeeName
                );
            }
        }
    );
});
