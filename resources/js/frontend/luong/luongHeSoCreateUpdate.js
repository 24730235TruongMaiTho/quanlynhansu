document.addEventListener('DOMContentLoaded', () => {
    const HE_SO_API_URL =
        '/api/v1/luong/he-so-luong';

    const elements = {
        modal:
            document.getElementById(
                'coefficient-modal'
            ),

        form:
            document.getElementById(
                'coefficient-form'
            ),

        modalTitle:
            document.getElementById(
                'coefficient-modal-title'
            ),

        modalDescription:
            document.getElementById(
                'coefficient-modal-description'
            ),

        modalMessage:
            document.getElementById(
                'coefficient-modal-message'
            ),

        closeButton:
            document.getElementById(
                'coefficient-modal-close'
            ),

        cancelButton:
            document.getElementById(
                'coefficient-modal-cancel'
            ),

        submitButton:
            document.getElementById(
                'coefficient-modal-submit'
            ),

        coefficientId:
            document.getElementById(
                'coefficient-id'
            ),

        employeeCode:
            document.getElementById(
                'coefficient-employee-code'
            ),

        employeeName:
            document.getElementById(
                'coefficient-employee-name'
            ),

        coefficientValue:
            document.getElementById(
                'coefficient-value'
            ),

        fromDate:
            document.getElementById(
                'coefficient-from-date'
            ),

        toDate:
            document.getElementById(
                'coefficient-to-date'
            ),
    };

    if (!elements.modal || !elements.form) {
        return;
    }

    const state = {
        mode: 'create',
        coefficientId: null,
        employeeCode: null,
        employeeName: null,
    };

    function showMessage(message) {
        if (!elements.modalMessage) {
            return;
        }

        elements.modalMessage.textContent =
            message;

        elements.modalMessage.hidden = false;
    }

    function clearMessage() {
        if (!elements.modalMessage) {
            return;
        }

        elements.modalMessage.textContent = '';
        elements.modalMessage.hidden = true;
    }

    function closeModal() {
        if (elements.modal.open) {
            elements.modal.close();
        }

        clearMessage();
    }

    function setFieldsDisabled(disabled) {
        [
            elements.coefficientValue,
            elements.fromDate,
            elements.toDate,
        ].forEach((field) => {
            if (field) {
                field.disabled = disabled;
            }
        });
    }

    function resetForm() {
        elements.form.reset();

        state.coefficientId = null;

        elements.coefficientId.value = '';
        elements.coefficientValue.value = '';
        elements.fromDate.value = '';
        elements.toDate.value = '';

        clearMessage();
    }

    function setMode(mode) {
        state.mode = mode;
        elements.modal.dataset.mode = mode;

        if (mode === 'create') {
            elements.modalTitle.textContent =
                'Thêm hệ số lương';

            elements.modalDescription.textContent =
                'Tạo hệ số lương mới cho nhân viên.';

            elements.submitButton.textContent =
                'Thêm hệ số';

            elements.submitButton.hidden = false;

            setFieldsDisabled(false);
        }

        if (mode === 'edit') {
            elements.modalTitle.textContent =
                'Cập nhật hệ số lương';

            elements.modalDescription.textContent =
                'Cập nhật giá trị và thời gian hiệu lực.';

            elements.submitButton.textContent =
                'Lưu thay đổi';

            elements.submitButton.hidden = false;

            setFieldsDisabled(false);
        }

        if (mode === 'view') {
            elements.modalTitle.textContent =
                'Chi tiết hệ số lương';

            elements.modalDescription.textContent =
                'Thông tin hệ số lương của nhân viên.';

            elements.submitButton.hidden = true;

            setFieldsDisabled(true);
        }
    }

    function bindEmployee(
        employeeCode,
        employeeName
    ) {
        state.employeeCode = employeeCode;
        state.employeeName = employeeName;

        elements.employeeCode.value =
            employeeCode || '';

        elements.employeeName.value =
            employeeName || '';
    }

    async function requestJson(
        url,
        options = {}
    ) {
        const response = await fetch(url, {
            headers: {
                Accept: 'application/json',
                'Content-Type':
                    'application/json',
                'X-Requested-With':
                    'XMLHttpRequest',

                ...(options.headers || {}),
            },

            ...options,
        });

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

        if (
            !response.ok ||
            result.success === false
        ) {
            const validationErrors =
                result.errors
                    ? Object.values(
                        result.errors
                    )
                        .flat()
                        .join(' ')
                    : null;

            throw new Error(
                validationErrors ||
                result.message ||
                `Request thất bại. HTTP ${response.status}`
            );
        }

        return result;
    }

    function extractData(result) {
        return result?.data ?? result;
    }

    function fillForm(item) {
        state.coefficientId =
            item.ma_ls ?? null;

        elements.coefficientId.value =
            item.ma_ls ?? '';

        elements.employeeCode.value =
            item.ma_nv ??
            state.employeeCode ??
            '';

        elements.coefficientValue.value =
            item.he_so_luong ?? '';

        elements.fromDate.value =
            item.tu_ngay ?? '';

        elements.toDate.value =
            item.den_ngay ?? '';
    }

    function buildPayload() {
        return {
            ma_nv:
                elements.employeeCode.value
                    .trim()
                    .toUpperCase(),

            he_so_luong:
                Number(
                    elements.coefficientValue.value
                ),

            tu_ngay:
            elements.fromDate.value,

            den_ngay:
                elements.toDate.value || null,
        };
    }

    function validatePayload(payload) {
        if (!payload.ma_nv) {
            return 'Mã nhân viên không hợp lệ.';
        }

        if (
            !Number.isFinite(
                payload.he_so_luong
            ) ||
            payload.he_so_luong <= 0
        ) {
            return 'Hệ số lương phải lớn hơn 0.';
        }

        if (!payload.tu_ngay) {
            return 'Từ ngày không được để trống.';
        }

        if (
            payload.den_ngay &&
            payload.den_ngay < payload.tu_ngay
        ) {
            return 'Đến ngày không được nhỏ hơn từ ngày.';
        }

        return null;
    }

    function openCreate(
        employeeCode,
        employeeName
    ) {
        resetForm();
        setMode('create');

        bindEmployee(
            employeeCode,
            employeeName
        );

        elements.modal.showModal();

        elements.coefficientValue.focus();
    }

    async function openExisting(
        coefficientId,
        employeeCode,
        employeeName,
        mode
    ) {
        resetForm();
        setMode(mode);

        bindEmployee(
            employeeCode,
            employeeName
        );

        elements.modal.showModal();

        try {
            elements.modalTitle.textContent =
                'Đang tải thông tin...';

            const result = await requestJson(
                `${HE_SO_API_URL}/${encodeURIComponent(
                    coefficientId
                )}`
            );

            const item = extractData(result);

            fillForm(item);
            setMode(mode);
        } catch (error) {
            console.error(error);
            showMessage(error.message);
        }
    }

    async function submitForm(event) {
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

        const isEdit =
            state.mode === 'edit';

        const url = isEdit
            ? `${HE_SO_API_URL}/${encodeURIComponent(
                state.coefficientId
            )}`
            : HE_SO_API_URL;

        elements.submitButton.disabled = true;

        elements.submitButton.textContent =
            isEdit
                ? 'Đang lưu...'
                : 'Đang thêm...';

        try {
            await requestJson(url, {
                method: isEdit
                    ? 'PUT'
                    : 'POST',

                body: JSON.stringify(payload),
            });

            closeModal();

            document.dispatchEvent(
                new CustomEvent(
                    'salary-coefficient:data-changed',
                    {
                        detail: {
                            action: isEdit
                                ? 'updated'
                                : 'created',

                            employeeCode:
                            state.employeeCode,
                        },
                    }
                )
            );
        } catch (error) {
            console.error(error);
            showMessage(error.message);
        } finally {
            elements.submitButton.disabled = false;

            elements.submitButton.textContent =
                isEdit
                    ? 'Lưu thay đổi'
                    : 'Thêm hệ số';
        }
    }

    async function deleteCoefficient(
        coefficientId,
        employeeCode,
        employeeName
    ) {
        const confirmed = window.confirm(
            `Bạn có chắc muốn xóa hệ số lương này của ${
                employeeName ||
                employeeCode
            } không?`
        );

        if (!confirmed) {
            return;
        }

        try {
            await requestJson(
                `${HE_SO_API_URL}/${encodeURIComponent(
                    coefficientId
                )}`,
                {
                    method: 'DELETE',
                }
            );

            document.dispatchEvent(
                new CustomEvent(
                    'salary-coefficient:data-changed',
                    {
                        detail: {
                            action: 'deleted',
                            employeeCode,
                        },
                    }
                )
            );
        } catch (error) {
            console.error(error);
            window.alert(error.message);
        }
    }

    document.addEventListener(
        'salary-coefficient:action',
        (event) => {
            const {
                action,
                coefficientId,
                employeeCode,
                employeeName,
            } = event.detail || {};

            if (action === 'create') {
                openCreate(
                    employeeCode,
                    employeeName
                );

                return;
            }

            if (action === 'edit') {
                openExisting(
                    coefficientId,
                    employeeCode,
                    employeeName,
                    'edit'
                );

                return;
            }

            if (action === 'view') {
                openExisting(
                    coefficientId,
                    employeeCode,
                    employeeName,
                    'view'
                );

                return;
            }

            if (action === 'delete') {
                deleteCoefficient(
                    coefficientId,
                    employeeCode,
                    employeeName
                );
            }
        }
    );

    elements.form.addEventListener(
        'submit',
        submitForm
    );

    elements.closeButton?.addEventListener(
        'click',
        closeModal
    );

    elements.cancelButton?.addEventListener(
        'click',
        closeModal
    );

    elements.modal.addEventListener(
        'click',
        (event) => {
            if (
                event.target === elements.modal
            ) {
                closeModal();
            }
        }
    );
});
