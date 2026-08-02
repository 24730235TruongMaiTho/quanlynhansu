document.addEventListener('DOMContentLoaded', async function() {
    try {
        const response = await fetch('/api/nghi-phep');
        const result = await response.json();

        if (result.success && result.data) {
            const data = result.data.data || result.data;
            const tbody = document.getElementById('leave-tbody');
            tbody.innerHTML = '';

            let pendingCount = 0, approvedCount = 0;

            data.forEach((leave) => {
                if (leave.trang_thai_duyet === 0) pendingCount++;
                if (leave.trang_thai_duyet === 1) approvedCount++;

                const statusLabel = leave.trang_thai_duyet === 0 ? '<span class="label label-attention">Chờ duyệt</span>'
                    : leave.trang_thai_duyet === 1 ? '<span class="label label-success">Đã duyệt</span>'
                        : '<span class="label label-danger">Từ chối</span>';

                const row = document.createElement('tr');
                row.innerHTML = `
                    <td><div class="employee"><div class="avatar">${leave.nhan_vien?.ma_nv?.substring(0,2) || 'NV'}</div><div><div class="employee-name">${leave.nhan_vien?.ten_nv || 'N/A'}</div><div class="meta">${leave.ma_nv} · ${leave.nhan_vien?.phong_ban || 'N/A'}</div></div></div></td>
                    <td>${leave.loai_phep?.ten_lp || 'Nghỉ phép'}</td>
                    <td><div>${leave.tu_ngay} – ${leave.den_ngay}</div><div class="sub">Ngày liên tiếp</div></td>
                    <td class="numeric">${Math.ceil((new Date(leave.den_ngay) - new Date(leave.tu_ngay)) / (1000 * 60 * 60 * 24)) + 1}</td>
                    <td>${leave.ly_do || 'N/A'}</td>
                    <td>N/A</td>
                    <td>${statusLabel}</td>
                    <td>
                        ${leave.trang_thai_duyet === 0 ? `
                            <div class="row-actions">
                                <button class="btn approve-btn" data-id="${leave.ma_np}">Duyệt</button>
                                <button class="btn btn-danger reject-btn" data-id="${leave.ma_np}">Từ chối</button>
                            </div>
                        ` : `<button class="btn icon-btn kebab">•••</button>`}
                    </td>
                `;
                tbody.appendChild(row);
            });

            document.getElementById('pending-count').textContent = pendingCount;
            document.getElementById('approved-count').textContent = approvedCount;

            document.querySelectorAll('.approve-btn').forEach(btn => {
                btn.addEventListener('click', async (e) => {
                    const id = e.target.dataset.id;
                    await fetch(`/api/nghi-phep/${id}`, {
                        method: 'PUT',
                        headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': '{{ csrf_token() }}' },
                        body: JSON.stringify({ trang_thai_duyet: 1 })
                    });
                    location.reload();
                });
            });

            document.querySelectorAll('.reject-btn').forEach(btn => {
                btn.addEventListener('click', async (e) => {
                    const id = e.target.dataset.id;
                    await fetch(`/api/nghi-phep/${id}`, {
                        method: 'PUT',
                        headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': '{{ csrf_token() }}' },
                        body: JSON.stringify({ trang_thai_duyet: 2 })
                    });
                    location.reload();
                });
            });
        }
    } catch (error) {
        console.error('Error loading leave data:', error);
        document.getElementById('leave-tbody').innerHTML = '<tr class="empty-row"><td colspan="8">Lỗi tải dữ liệu</td></tr>';
    }
});
