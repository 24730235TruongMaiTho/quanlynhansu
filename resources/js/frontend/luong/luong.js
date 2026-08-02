document.addEventListener('DOMContentLoaded', async function() {
    try {
        const response = await fetch('/api/luong');
        const result = await response.json();

        if (result.success && result.data) {
            const data = result.data.data || result.data;
            const tbody = document.getElementById('salary-tbody');
            tbody.innerHTML = '';

            data.forEach((salary, idx) => {
                const row = document.createElement('tr');
                row.innerHTML = `
                    <td><input class="checkbox" type="checkbox"></td>
                    <td><div class="employee"><div class="avatar">${salary.nhan_vien?.ma_nv?.substring(0,2) || 'NV'}</div><div><div class="employee-name">${salary.nhan_vien?.ten_nv || 'N/A'}</div><div class="meta">${salary.ma_nv}</div></div></div></td>
                    <td><div>${salary.nhan_vien?.phong_ban || 'N/A'}</div><div class="sub">${salary.nhan_vien?.chuc_vu || ''}</div></td>
                    <td class="numeric">22,0</td>
                    <td class="numeric">${(salary.thuong || 0).toLocaleString('vi-VN')} ₫</td>
                    <td class="numeric">${(salary.phat || 0).toLocaleString('vi-VN')} ₫</td>
                    <td class="numeric">${((salary.bao_hiem || 0) + (salary.thue || 0)).toLocaleString('vi-VN')} ₫</td>
                    <td class="numeric"><strong>${(salary.thuong || 0).toLocaleString('vi-VN')} ₫</strong></td>
                    <td><span class="label label-success">Đã hoàn tất</span></td>
                    <td><button class="btn icon-btn kebab">•••</button></td>
                `;
                tbody.appendChild(row);
            });
        }
    } catch (error) {
        console.error('Error loading salary data:', error);
        document.getElementById('salary-tbody').innerHTML = '<tr class="empty-row"><td colspan="10">Lỗi tải dữ liệu</td></tr>';
    }
});
