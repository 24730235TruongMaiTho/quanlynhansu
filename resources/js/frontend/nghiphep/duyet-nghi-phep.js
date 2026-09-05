import { renderSharedPagination } from '../shared/pagination.js';

document.addEventListener('DOMContentLoaded', () => {
    const AUTH_ME_API_URL = '/api/v1/auth/me';
    const APPROVAL_LIST_API_URL = '/api/v1/nghi-phep/phe-duyet';
    const NGHI_PHEP_API_URL = '/api/v1/nghi-phep';
    const LOAI_PHEP_API_URL = '/api/v1/nghi-phep/loai-phep';
    const PHONG_BAN_API_URL = '/api/v1/nghi-phep/phong-ban';

    const el = {
        loading: document.getElementById('leave-approval-loading'),
        error: document.getElementById('leave-approval-error'),
        filter: document.getElementById('leave-approval-filter'),
        main: document.getElementById('leave-approval-main'),
        department: document.getElementById('leave-approval-department'),
        keyword: document.getElementById('leave-filter-keyword'),
        type: document.getElementById('leave-filter-type'),
        from: document.getElementById('leave-filter-from'),
        to: document.getElementById('leave-filter-to'),
        reset: document.getElementById('leave-filter-reset'),
        pendingTab: document.getElementById('leave-tab-pending'),
        processedTab: document.getElementById('leave-tab-processed'),
        tbody: document.getElementById('leave-approval-tbody'),
        pageInfo: document.getElementById('leave-pagination-info'),
        pagination: document.getElementById('leave-pagination'),
        pageSize: document.getElementById('leave-page-size'),
        detailEmpty: document.getElementById('leave-detail-empty'),
        detailContent: document.getElementById('leave-detail-content'),
        maNv: document.getElementById('detail-ma-nv'),
        hoTen: document.getElementById('detail-ho-ten'),
        phongBan: document.getElementById('detail-phong-ban'),
        chucVu: document.getElementById('detail-chuc-vu'),
        loaiNghi: document.getElementById('detail-loai-nghi'),
        thoiGian: document.getElementById('detail-thoi-gian'),
        lyDo: document.getElementById('detail-ly-do'),
        nguoiTao: document.getElementById('detail-nguoi-tao'),
        trangThai: document.getElementById('detail-trang-thai'),
        approve: document.getElementById('leave-action-approve'),
        reject: document.getElementById('leave-action-reject'),
        success: document.getElementById('leave-action-success'),
        actionError: document.getElementById('leave-action-error'),
    };

    const state = {
        user: null,
        departmentName: '—',
        tab: 'pending',
        page: 1,
        perPage: 10,
        rows: [],
        paginator: null,
        selectedId: null,
        actionLoading: false,
        searchTimer: null,
    };

    const csrfToken = () => document.querySelector('meta[name="csrf-token"]')?.content || null;

    function escapeHtml(value) {
        return String(value ?? '')
            .replaceAll('&', '&amp;').replaceAll('<', '&lt;').replaceAll('>', '&gt;')
            .replaceAll('"', '&quot;').replaceAll("'", '&#039;');
    }

    function formatDate(value) {
        if (!value) return '—';
        const match = String(value).match(/^(\d{4})-(\d{2})-(\d{2})/);
        return match ? `${match[2]}/${match[3]}/${match[1]}` : String(value);
    }

    function statusText(status) {
        if (status === 0) return 'Chờ duyệt';
        if (status === 1) return 'Đã duyệt';
        if (status === 2) return 'Từ chối';
        return 'Không xác định';
    }

    function statusBadge(status) {
        if (status === 0) return '<span class="badge text-bg-primary-subtle text-primary border border-primary-subtle">Chờ duyệt</span>';
        if (status === 1) return '<span class="badge text-bg-success-subtle text-success border border-success-subtle">Đã duyệt</span>';
        return '<span class="badge text-bg-danger-subtle text-danger border border-danger-subtle">Từ chối</span>';
    }

    async function requestJson(url, options = {}) {
        const method = String(options.method || 'GET').toUpperCase();
        const headers = {
            Accept: 'application/json',
            'X-Requested-With': 'XMLHttpRequest',
            ...(options.headers || {}),
        };
        if (options.body !== undefined) headers['Content-Type'] = 'application/json';
        const token = csrfToken();
        if (token && !['GET', 'HEAD'].includes(method)) headers['X-CSRF-TOKEN'] = token;

        const response = await fetch(url, { ...options, method, headers, credentials: 'same-origin' });
        const contentType = response.headers.get('content-type') || '';
        if (!contentType.includes('application/json')) {
            throw new Error(`API không trả JSON. HTTP ${response.status}`);
        }
        const result = await response.json();
        if (!response.ok || result.success === false) {
            const validation = result?.errors ? Object.values(result.errors).flat().join(' ') : null;
            throw new Error(validation || result?.message || `Request thất bại. HTTP ${response.status}`);
        }
        return result;
    }

    function extractRows(result) {
        if (Array.isArray(result)) return result;
        if (Array.isArray(result?.data)) return result.data;
        if (Array.isArray(result?.data?.data)) return result.data.data;
        if (Array.isArray(result?.data?.data?.data)) return result.data.data.data;
        return [];
    }

    function extractPaginator(result) {
        return [result?.data, result?.data?.data, result].find(x =>
            x && typeof x === 'object' && !Array.isArray(x) && ('current_page' in x || 'last_page' in x)
        ) || null;
    }

    function normalizeAuth(result) {
        const data = result?.data || {};
        return data.user || {
            ma_nv: data.ma_nv ?? null,
            ho_ten: data.ho_ten ?? null,
            ma_pb: data.ma_pb ?? null,
            ten_pb: data.ten_pb ?? null,
        };
    }

    async function loadAuth() {
        const result = await requestJson(AUTH_ME_API_URL);
        state.user = normalizeAuth(result);
        if (!state.user?.ma_nv) throw new Error('Không lấy được tài khoản hiện tại.');
    }

    async function loadDepartment() {
        if (state.user?.ten_pb) {
            state.departmentName = state.user.ten_pb;
            el.department.textContent = state.departmentName;
            return;
        }
        if (!state.user?.ma_pb) {
            state.departmentName = 'Chưa xác định';
            el.department.textContent = state.departmentName;
            return;
        }
        const result = await requestJson(PHONG_BAN_API_URL);
        const department = extractRows(result).find(x => String(x.ma_pb) === String(state.user.ma_pb));
        state.departmentName = department?.ten_pb || `Phòng ban #${state.user.ma_pb}`;
        el.department.textContent = state.departmentName;
    }

    async function loadLeaveTypes() {
        const result = await requestJson(LOAI_PHEP_API_URL);
        el.type.innerHTML = '<option value="">Tất cả</option>';
        extractRows(result).forEach(item => {
            const option = document.createElement('option');
            option.value = String(item.ma_lp);
            option.textContent = item.ten_lp || `Loại ${item.ma_lp}`;
            el.type.appendChild(option);
        });
    }

    function daysBetween(from, to) {
        if (!from || !to) return 0;
        const a = new Date(`${String(from).slice(0,10)}T00:00:00`);
        const b = new Date(`${String(to).slice(0,10)}T00:00:00`);
        if (Number.isNaN(a.getTime()) || Number.isNaN(b.getTime())) return 0;
        return Math.floor((b - a) / 86400000) + 1;
    }

    function normalizeLeave(item) {
        const status = Number(item.trang_thai_duyet ?? 0);
        return {
            ma_np: item.ma_np ?? item.id ?? '',
            ma_nv: item.ma_nv ?? '',
            ho_ten: item.ho_ten ?? item.nhan_vien?.ho_ten ?? '—',
            ten_pb: item.ten_pb ?? item.phong_ban?.ten_pb ?? state.departmentName,
            ten_cv: item.ten_cv ?? item.chuc_vu?.ten_cv ?? item.nhan_vien?.ten_cv ?? '—',
            ma_lp: item.ma_lp ?? item.loai_phep?.ma_lp ?? null,
            ten_lp: item.ten_lp ?? item.loai_phep?.ten_lp ?? 'Nghỉ phép',
            tu_ngay: item.tu_ngay ?? '',
            den_ngay: item.den_ngay ?? '',
            so_ngay: Number(item.so_ngay ?? daysBetween(item.tu_ngay, item.den_ngay)),
            ly_do: item.ly_do ?? '',
            trang_thai_duyet: status,
            created_at: item.created_at ?? item.ngay_tao ?? item.thoi_gian_tao ?? null,
        };
    }

    function buildListUrl() {
        const url = new URL(APPROVAL_LIST_API_URL, window.location.origin);
        url.searchParams.set('tab', state.tab);
        url.searchParams.set('page', String(state.page));
        url.searchParams.set('per_page', String(state.perPage));
        if (el.keyword.value.trim()) url.searchParams.set('tu_khoa', el.keyword.value.trim());
        if (el.type.value) url.searchParams.set('ma_lp', el.type.value);
        if (el.from.value) url.searchParams.set('tu_ngay', el.from.value);
        if (el.to.value) url.searchParams.set('den_ngay', el.to.value);
        return url.toString();
    }

    function validateRange() {
        if (el.from.value && el.to.value && el.to.value < el.from.value) {
            el.error.textContent = 'Đến ngày không được nhỏ hơn Từ ngày.';
            el.error.hidden = false;
            return false;
        }
        el.error.hidden = true;
        return true;
    }

    async function loadList() {
        if (!validateRange()) return;
        el.tbody.innerHTML = '<tr><td colspan="8" class="text-center text-secondary py-5">Đang tải dữ liệu...</td></tr>';
        try {
            const result = await requestJson(buildListUrl());
            state.rows = extractRows(result).map(normalizeLeave);
            state.paginator = extractPaginator(result);
            renderTable();
            renderPagination();
            state.rows.length ? selectLeave(state.rows[0].ma_np) : clearSelection();
        } catch (error) {
            state.rows = [];
            state.paginator = null;
            el.tbody.innerHTML = `<tr><td colspan="8" class="text-center text-danger py-5">${escapeHtml(error.message)}</td></tr>`;
            renderPagination();
            clearSelection();
        }
    }

    function renderTable() {
        if (!state.rows.length) {
            el.tbody.innerHTML = `<tr><td colspan="8" class="text-center text-secondary py-5">${state.tab === 'pending' ? 'Không có đơn đang chờ duyệt.' : 'Chưa có đơn đã xử lý.'}</td></tr>`;
            return;
        }
        el.tbody.innerHTML = state.rows.map(item => `
            <tr class="leave-approval-row" data-leave-id="${escapeHtml(item.ma_np)}">
                <td><input class="form-check-input" type="radio" name="leave-selected" value="${escapeHtml(item.ma_np)}"></td>
                <td class="fw-semibold">${escapeHtml(item.ma_np)}</td>
                <td><strong>${escapeHtml(item.ho_ten)}</strong><br><small class="text-secondary">${escapeHtml(item.ma_nv)}</small></td>
                <td>${escapeHtml(item.ten_lp)}</td>
                <td>${formatDate(item.tu_ngay)}</td>
                <td>${formatDate(item.den_ngay)}</td>
                <td class="text-center">${escapeHtml(item.so_ngay || '—')}</td>
                <td>${statusBadge(item.trang_thai_duyet)}</td>
            </tr>`).join('');
    }

    function renderPagination() {
        const p = state.paginator;
        if (!p) {
            el.pageInfo.textContent = state.rows.length ? `Hiển thị ${state.rows.length} đơn` : 'Hiển thị 0 đơn';
            el.pagination.innerHTML = '';
            return;
        }
        const current = Number(p.current_page || 1);
        const last = Number(p.last_page || 1);
        const total = Number(p.total || 0);
        el.pageInfo.textContent = total ? `Hiển thị ${p.from ?? 0} đến ${p.to ?? 0} trong tổng số ${total} đơn` : 'Hiển thị 0 đơn';
        renderSharedPagination(el.pagination, p, {
            pageAttribute: 'page',
        });
    }

    function selectedLeave() {
        return state.rows.find(x => String(x.ma_np) === String(state.selectedId)) || null;
    }

    function selectLeave(id) {
        const item = state.rows.find(x => String(x.ma_np) === String(id));
        if (!item) return clearSelection();
        state.selectedId = String(item.ma_np);
        document.querySelectorAll('.leave-approval-row').forEach(row => {
            const selected = String(row.dataset.leaveId) === state.selectedId;
            row.classList.toggle('is-selected', selected);
            const radio = row.querySelector('input[type="radio"]');
            if (radio) radio.checked = selected;
        });
        renderDetail(item);
    }

    function clearSelection() {
        state.selectedId = null;
        el.detailEmpty.hidden = false;
        el.detailContent.hidden = true;
    }

    function renderDetail(item) {
        el.detailEmpty.hidden = true;
        el.detailContent.hidden = false;
        el.maNv.textContent = item.ma_nv || '—';
        el.hoTen.textContent = item.ho_ten || '—';
        el.phongBan.textContent = item.ten_pb || state.departmentName || '—';
        el.chucVu.textContent = item.ten_cv || '—';
        el.loaiNghi.textContent = item.ten_lp || '—';
        el.thoiGian.textContent = `${formatDate(item.tu_ngay)} – ${formatDate(item.den_ngay)}${item.so_ngay ? ` (${item.so_ngay} ngày)` : ''}`;
        el.lyDo.textContent = item.ly_do || '—';
        el.nguoiTao.textContent = item.ho_ten || '—';
        el.trangThai.innerHTML = statusBadge(item.trang_thai_duyet);
        syncActions(item);
        clearActionMessages();
    }

    function syncActions(item = selectedLeave()) {
        const enabled = !!item && state.tab === 'pending' && item.trang_thai_duyet === 0 && !state.actionLoading;
        el.approve.disabled = !enabled;
        el.reject.disabled = !enabled;
    }

    function updatePayload(item, status) {
        return {
            ma_nv: item.ma_nv,
            tu_ngay: String(item.tu_ngay).slice(0, 10),
            den_ngay: String(item.den_ngay).slice(0, 10),
            ma_lp: item.ma_lp,
            ly_do: item.ly_do || '',
            trang_thai_duyet: status,
        };
    }

    async function processLeave(status) {
        const item = selectedLeave();
        if (!item) return showActionError('Vui lòng chọn một đơn nghỉ phép.');
        if (state.tab !== 'pending' || item.trang_thai_duyet !== 0) return showActionError('Chỉ đơn chờ duyệt mới được xử lý.');
        const verb = status === 1 ? 'phê duyệt' : 'từ chối';
        if (!window.confirm(`Bạn có chắc muốn ${verb} đơn #${item.ma_np}?`)) return;

        state.actionLoading = true;
        syncActions(item);
        clearActionMessages();
        try {
            const result = await requestJson(`${NGHI_PHEP_API_URL}/${encodeURIComponent(item.ma_np)}`, {
                method: 'PUT', body: JSON.stringify(updatePayload(item, status)),
            });
            showActionSuccess(result.message || (status === 1 ? 'Phê duyệt thành công.' : 'Từ chối thành công.'));
            await loadList();
        } catch (error) {
            showActionError(error.message);
        } finally {
            state.actionLoading = false;
            syncActions();
        }
    }

    function clearActionMessages() {
        el.success.hidden = true; el.success.textContent = '';
        el.actionError.hidden = true; el.actionError.textContent = '';
    }
    function showActionSuccess(message) {
        el.actionError.hidden = true;
        el.success.textContent = message; el.success.hidden = false;
    }
    function showActionError(message) {
        el.success.hidden = true;
        el.actionError.textContent = message; el.actionError.hidden = false;
    }

    function switchTab(tab) {
        state.tab = tab;
        state.page = 1;
        el.pendingTab.classList.toggle('active', tab === 'pending');
        el.processedTab.classList.toggle('active', tab === 'processed');
        loadList();
    }

    function resetFilters() {
        el.keyword.value = '';
        el.type.value = '';
        el.from.value = '';
        el.to.value = '';
        el.to.removeAttribute('min');
        state.page = 1;
        loadList();
    }

    function scheduleSearch() {
        clearTimeout(state.searchTimer);
        state.searchTimer = setTimeout(() => { state.page = 1; loadList(); }, 350);
    }

    async function initialize() {
        try {
            await loadAuth();
            await Promise.all([loadDepartment(), loadLeaveTypes()]);
            el.loading.hidden = true;
            el.filter.hidden = false;
            el.main.hidden = false;
            await loadList();
        } catch (error) {
            el.loading.hidden = true;
            el.error.textContent = error.message;
            el.error.hidden = false;
        }
    }

    el.keyword?.addEventListener('input', scheduleSearch);
    el.type?.addEventListener('change', () => { state.page = 1; loadList(); });
    el.from?.addEventListener('change', () => {
        if (el.from.value) el.to.min = el.from.value; else el.to.removeAttribute('min');
        state.page = 1; loadList();
    });
    el.to?.addEventListener('change', () => { state.page = 1; loadList(); });
    el.reset?.addEventListener('click', resetFilters);
    el.pendingTab?.addEventListener('click', () => switchTab('pending'));
    el.processedTab?.addEventListener('click', () => switchTab('processed'));
    el.tbody?.addEventListener('click', event => {
        const row = event.target.closest('[data-leave-id]');
        if (row) selectLeave(row.dataset.leaveId);
    });
    el.pagination?.addEventListener('click', event => {
        const btn = event.target.closest('[data-page]');
        if (!btn || btn.closest('.disabled')) return;
        const page = Number(btn.dataset.page);
        if (page >= 1) { state.page = page; loadList(); }
    });
    el.pageSize?.addEventListener('change', () => {
        state.perPage = Number(el.pageSize.value) || 10;
        state.page = 1; loadList();
    });
    el.approve?.addEventListener('click', () => processLeave(1));
    el.reject?.addEventListener('click', () => processLeave(2));
    initialize();
});
