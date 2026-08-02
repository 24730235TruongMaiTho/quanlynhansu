document.addEventListener('DOMContentLoaded', async function() {
    try {
        const response = await fetch('/api/cham-cong');
        const result = await response.json();

        if (result.success && result.data) {
            const data = result.data.data || result.data;
            const tbody = document.getElementById('attendance-tbody');
            tbody.innerHTML = '';

            let totalHours = 0, lateCount = 0, earlyCount = 0;

            data.forEach((attendance) => {
                totalHours += attendance.so_gio_lam || 0;
                if (attendance.vao_muon) lateCount++;
                if (attendance.ve_som) earlyCount++;

                const row = document.createElement('tr');
                row.innerHTML = `
                    <td><div class="employee"><div class="avatar">${attendance.nhan_vien?.ma_nv?.substring(0,2) || 'NV'}</div><div><div class="employee-name">${attendance.nhan_vien?.ten_nv || 'N/A'}</div><div class="meta">${attendance.ma_nv}</div></div></div></td>
                    <td>${attendance.nhan_vien?.phong_ban || 'N/A'}</td>
                    <td class="numeric"><strong>${attendance.so_gio_lam || 0}</strong> / 22</td>
                    <td><div class="progress"><span style="width:${Math.min((attendance.so_gio_lam || 0) / 22 * 100, 100)}%"></span></div></td>
                    <td class="numeric">${attendance.so_gio_lam || 0} giờ</td>
                    <td class="numeric">${attendance.vao_muon || 0}</td>
                    <td class="numeric">${attendance.ve_som || 0}</td>
                    <td class="numeric">0</td>
                    <td><span class="label label-success">Đủ công</span></td>
                    <td><button class="btn icon-btn kebab">•••</button></td>
                `;
                tbody.appendChild(row);
            });

            document.getElementById('total-hours').textContent = Math.round(totalHours);
            document.getElementById('late-count').textContent = lateCount;
            document.getElementById('early-count').textContent = earlyCount;
        }
    } catch (error) {
        console.error('Error loading attendance data:', error);
        document.getElementById('attendance-tbody').innerHTML = '<tr class="empty-row"><td colspan="10">Lỗi tải dữ liệu</td></tr>';
    }
});
