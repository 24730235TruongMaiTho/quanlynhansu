const state = { editingId: null, roles: [], currentPage: 1, pageSize: 10 };

const dom = typeof document !== 'undefined' ? document : null;

const elements = {
    page: dom?.querySelector('[data-role-data-url]'),
    tableBody: dom?.querySelector('#role-table-body'),
    pagination: dom?.querySelector('#role-pagination'),
    paginationSummary: dom?.querySelector('#role-pagination-summary'),
    totalSummary: dom?.querySelector('#role-total-summary'),
    pageSize: dom?.querySelector('#role-page-size'),
    feedback: dom?.querySelector('#role-feedback'),
    searchForm: dom?.querySelector('#role-search-form'),
    form: dom?.querySelector('#role-form'),
    modal: dom?.querySelector('#role-modal'),
    modalTitle: dom?.querySelector('#role-modal-title'),
    formError: dom?.querySelector('#role-form-error'),
    submit: dom?.querySelector('#role-submit'),
    name: dom?.querySelector('#role-name'),
    description: dom?.querySelector('#role-description'),
};

const modal = elements.modal ? bootstrap.Modal.getOrCreateInstance(elements.modal) : null;
const csrfToken = dom?.querySelector('meta[name="csrf-token"]')?.content;

function showFeedback(message, type = 'success') {
    elements.feedback.innerHTML = `<div class="alert alert-${type}" role="${type === 'danger' ? 'alert' : 'status'}">${message}</div>`;
}

function escapeHtml(value) {
    return String(value ?? '').replace(/[&<>'"]/g, (character) => ({
        '&': '&amp;', '<': '&lt;', '>': '&gt;', "'": '&#039;', '"': '&quot;',
    }[character]));
}

function renderRoleActions(role, permissions) {
    const roleId = encodeURIComponent(String(role.ma_vt ?? ''));
    const roleName = escapeHtml(role.ten_vt);
    const actions = [];

    if (permissions.canViewPermissions) {
        actions.push(`<a class="btn btn-sm btn-outline-secondary me-1" href="/vai-tro/${roleId}/phan-quyen" aria-label="Phân quyền ${roleName}"><i class="bi bi-key" aria-hidden="true"></i></a>`);
    }
    if (permissions.canEdit) {
        actions.push(`<button class="btn btn-sm btn-outline-primary me-1" type="button" data-role-edit="${roleId}" aria-label="Sửa ${roleName}"><i class="bi bi-pencil" aria-hidden="true"></i></button>`);
    }
    if (permissions.canDelete) {
        actions.push(`<button class="btn btn-sm btn-outline-danger" type="button" data-role-delete="${roleId}" aria-label="Xóa ${roleName}"><i class="bi bi-trash" aria-hidden="true"></i></button>`);
    }

    return actions.join('\n');
}

async function request(url, options = {}) {
    const response = await fetch(url, {
        ...options,
        headers: { Accept: 'application/json', 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrfToken, ...(options.headers || {}) },
    });
    const payload = await response.json().catch(() => ({}));
    if (!response.ok) {
        const errors = Object.values(payload.errors || {}).flat();
        throw new Error(errors[0] || payload.message || 'Không thể hoàn tất thao tác.');
    }
    return payload;
}

function renderRoles(roles = state.roles) {
    state.roles = roles;
    const totalPages = Math.max(1, Math.ceil(roles.length / state.pageSize));
    state.currentPage = Math.min(state.currentPage, totalPages);
    const start = (state.currentPage - 1) * state.pageSize;
    const visibleRoles = roles.slice(start, start + state.pageSize);

    if (!roles.length) {
        elements.tableBody.innerHTML = '<tr><td class="text-center text-secondary py-5" colspan="4">Chưa có vai trò phù hợp.</td></tr>';
        elements.paginationSummary.textContent = '';
        elements.totalSummary.textContent = 'Hiển thị 0 trong tổng số 0 vai trò';
        elements.pagination.innerHTML = '';
        return;
    }

    const permissions = {
        canEdit: elements.page.dataset.roleCanEdit === '1',
        canDelete: elements.page.dataset.roleCanDelete === '1',
        canViewPermissions: elements.page.dataset.roleCanPermission === '1',
    };
    elements.tableBody.innerHTML = visibleRoles.map((role) => `
        <tr>
            <th scope="row">${escapeHtml(role.ma_vt)}</th>
            <td class="fw-medium">${escapeHtml(role.ten_vt)}</td>
            <td>${escapeHtml(role.mo_ta || 'Chưa có mô tả')}</td>
            <td class="text-end text-nowrap">
                ${renderRoleActions(role, permissions)}
            </td>
        </tr>`).join('');
    elements.paginationSummary.textContent = `Hiển thị ${start + 1}-${Math.min(start + state.pageSize, roles.length)} trong tổng số ${roles.length} vai trò`;
    elements.totalSummary.textContent = `Hiển thị ${start + 1} - ${Math.min(start + state.pageSize, roles.length)} trong tổng số ${roles.length} vai trò`;
    elements.pagination.innerHTML = `
        <li class="page-item ${state.currentPage === 1 ? 'disabled' : ''}">
            <button class="page-link" type="button" data-role-page="${state.currentPage - 1}" aria-label="Trang trước" ${state.currentPage === 1 ? 'disabled' : ''}>
                <i class="bi bi-chevron-left" aria-hidden="true"></i>
            </button>
        </li>
        ${Array.from({ length: totalPages }, (_, index) => index + 1).map((page) => `
            <li class="page-item ${page === state.currentPage ? 'active' : ''}">
                <button class="page-link" type="button" data-role-page="${page}" aria-label="Trang ${page}" ${page === state.currentPage ? 'aria-current="page"' : ''}>${page}</button>
            </li>`).join('')}
        <li class="page-item ${state.currentPage === totalPages ? 'disabled' : ''}">
            <button class="page-link" type="button" data-role-page="${state.currentPage + 1}" aria-label="Trang sau" ${state.currentPage === totalPages ? 'disabled' : ''}>
                <i class="bi bi-chevron-right" aria-hidden="true"></i>
            </button>
        </li>`;
}

async function loadRoles() {
    elements.tableBody.innerHTML = '<tr><td class="text-center text-secondary py-5" colspan="4">Đang tải dữ liệu...</td></tr>';
    try {
        const query = new URLSearchParams(new FormData(elements.searchForm));
        const endpoint = query.get('ten_vt') ? elements.page.dataset.roleSearchUrl : elements.page.dataset.roleDataUrl;
        const payload = await request(`${endpoint}?${query}`);
        state.currentPage = 1;
        renderRoles(payload.data || []);
    } catch (error) {
        elements.tableBody.innerHTML = `<tr><td class="text-center text-danger py-5" colspan="4">${escapeHtml(error.message)}</td></tr>`;
        elements.paginationSummary.textContent = '';
        elements.totalSummary.textContent = '';
        elements.pagination.innerHTML = '';
    }
}

function openCreate() {
    state.editingId = null;
    elements.modalTitle.textContent = 'Thêm vai trò';
    elements.submit.textContent = 'Lưu vai trò';
    elements.form.reset();
    elements.formError.classList.add('d-none');
    modal.show();
}

async function openEdit(id) {
    try {
        const payload = await request(`/vai-tro/${id}`);
        const role = payload.data;
        state.editingId = id;
        elements.modalTitle.textContent = 'Chỉnh sửa vai trò';
        elements.submit.textContent = 'Cập nhật vai trò';
        elements.name.value = role.ten_vt || '';
        elements.description.value = role.mo_ta || '';
        elements.formError.classList.add('d-none');
        modal.show();
    } catch (error) {
        showFeedback(error.message, 'danger');
    }
}

async function saveRole(event) {
    event.preventDefault();
    elements.submit.disabled = true;
    elements.formError.classList.add('d-none');
    const data = Object.fromEntries(new FormData(elements.form));
    const id = state.editingId;
    try {
        const payload = await request(id ? `/vai-tro/${id}` : elements.page.dataset.roleStoreUrl, {
            method: id ? 'PUT' : 'POST', body: JSON.stringify(data),
        });
        modal.hide();
        showFeedback(payload.message || 'Đã lưu vai trò.');
        await loadRoles();
    } catch (error) {
        elements.formError.textContent = error.message;
        elements.formError.classList.remove('d-none');
    } finally {
        elements.submit.disabled = false;
    }
}

async function deleteRole(id) {
    if (!window.confirm('Bạn có chắc muốn xóa vai trò này?')) return;
    try {
        const payload = await request(`/vai-tro/${id}`, { method: 'DELETE' });
        showFeedback(payload.message || 'Đã xóa vai trò.');
        await loadRoles();
    } catch (error) {
        showFeedback(error.message, 'danger');
    }
}

if (elements.searchForm) {
    elements.searchForm.addEventListener('submit', (event) => { event.preventDefault(); loadRoles(); });
    document.querySelector('[data-role-create]')?.addEventListener('click', openCreate);
    elements.form.addEventListener('submit', saveRole);
    elements.tableBody.addEventListener('click', (event) => {
        const editButton = event.target.closest('[data-role-edit]');
        const deleteButton = event.target.closest('[data-role-delete]');
        if (editButton) openEdit(editButton.dataset.roleEdit);
        if (deleteButton) deleteRole(deleteButton.dataset.roleDelete);
    });
    elements.pagination.addEventListener('click', (event) => {
        const pageButton = event.target.closest('[data-role-page]');
        if (!pageButton || pageButton.disabled) return;
        state.currentPage = Number(pageButton.dataset.rolePage);
        renderRoles();
    });
    elements.pageSize.addEventListener('change', () => {
        state.pageSize = Number(elements.pageSize.value);
        state.currentPage = 1;
        renderRoles();
    });
    loadRoles();
}

export { renderRoleActions, renderRoles };
