# Database và hợp đồng SQL active

> Cập nhật 2026-08-27. Runtime đã kiểm chứng là MariaDB 10.4.32; chưa claim MySQL 8. Database live không phải acceptance target.

## Nguồn fresh duy nhất

Chạy lần lượt trên database rỗng/disposable đã được phê duyệt:

```text
database/sql/tao_bang.sql
database/sql/du_lieu_mau.sql
database/sql/quyen_vai_tro.sql
```

Ba file active tạo đúng 15 bảng, seed 19 nhân viên, 37 quyền và 12 thủ tục RBAC. `tao_bang.sql` tạo schema; `du_lieu_mau.sql` tạo master/employee/business sample; `quyen_vai_tro.sql` tạo procedure vai trò/quyền và assignment nội bộ. Các file SQL ở root, `quan_ly_nhan_su.session.sql`, `LocalDemoSeeder` và script employee cũ chỉ là lịch sử.

Không import SQL active vào database cần giữ dữ liệu. Trước mọi DDL phải xác nhận target, backup và approval; không dùng web request cho backup/restore/import/export. Không tự tạo procedure bằng phỏng đoán để làm xanh caller.

## Mô hình 15 bảng

```text
phong_ban ─┐
chuc_vu ───┼── nhan_vien ── hop_dong ── loai_hop_dong
vai_tro ───┤       ├──── nghi_phep ── loai_phep
trang_thai┘       ├──── cham_cong
                  ├──── lich_su_he_so_luong
                  └──── luong

vai_tro ── vai_tro_quyen ── quyen
bo_dem_ma_nhan_vien ── cấp mã 00001..65535
```

`nhan_vien` chứa trực tiếp `dia_chi_cu_the`, `phuong_xa`, `quan_huyen`, `tinh_thanh`, `anh_dai_dien`, `ngay_nghi_viec`; không có bảng địa chỉ riêng trong active contract. `ma_nv` là `VARCHAR(5)`, `ma_pb`/`ma_cv`/`ma_vt` là khóa ngoại số, `ma_tt` dùng 1..6. `luong` có unique `(ma_nv, ky_luong)` trong schema active.

## Hợp đồng module thuộc ownership

### Nhân viên

Auth/CRUD Nhân viên dùng explicit Query Builder trên connection mặc định, không gọi procedure employee. Service sở hữu transaction profile + address + avatar; repository chỉ projection/write thuần dữ liệu. Auth projection phải lấy `ma_nv`, `ho_ten`, `email`, `mat_khau`, `ma_vt`, `ma_pb`, `ma_tt`; `ma_pb` cần cho scope Trưởng phòng sau login. Password/hash chỉ ở server boundary.

`NhanVienScope` áp policy HTTP: role `ma_vt = 4` phải có `ma_pb` dương hợp lệ; list ép filter phòng ban; target khác phòng trả 404. Đây là policy code, không phải thay đổi schema/RBAC.

### Phòng ban

`PhongBanRepository` dùng Query Builder trực tiếp trên `phong_ban` và `nhan_vien`, trả `ma_pb`, `ten_pb`, `so_nhan_vien`; mutation dùng transaction/row lock và mã lỗi `PB_*`. Các `sp_phong_ban_*` trong script cũ không phải caller active của repository.

### Chức vụ

`ChucVuRepository` dùng Query Builder trực tiếp trên `chuc_vu` và `nhan_vien`, trả `ma_cv`, `ten_cv`, `he_so_phu_cap`, `so_nhan_vien`; mutation dùng transaction/row lock và mã lỗi `CV_*`. Các `sp_chuc_vu_*` cũ không phải caller active.

## Procedure RBAC active

`quyen_vai_tro.sql` hiện tạo 12 procedure cho vai trò, quyền và gán role nội bộ: `sp_vai_tro_them`, `sp_vai_tro_sua`, `sp_vai_tro_xoa`, `sp_vai_tro_danh_sach`, `sp_quyen_them`, `sp_quyen_danh_sach`, `sp_quyen_xoa`, `sp_quyen_lay_theo_ma_nhan_vien`, `sp_vai_tro_quyen_them`, `sp_vai_tro_quyen_xoa`, `sp_vai_tro_quyen_lay_quyen_theo_vai_tro`, `sp_nhan_vien_gan_vai_tro_noi_bo`. Không coi procedure legacy khác là active chỉ vì còn nằm trong dump lịch sử hoặc live schema cũ.

## Lệch caller ngoài ownership

Các procedure sau **không có trong ba SQL active** và không được ghi là có trong active/live contract:

| Caller hiện tại | Procedure thiếu | Hậu quả |
| --- | --- | --- |
| `LuongRepository@all` | `sp_luong_tim_kiem_phan_trang` | API danh sách lương bị block |
| `ChamCongController` lookup | `sp_phong_ban_danh_sach` | Lookup phòng ban của Chấm công lệch contract |
| `ChamCongController` update | `sp_cham_cong_cap_nhat` | Update Chấm công không có routine active |
| `NghiPhepController` approve | `sp_nghi_phep_duyet_phep` | Duyệt Nghỉ phép bị block |

`sp_cham_cong_chi_tiet_phan_trang` cũng không thuộc active contract; chi tiết Chấm công hiện có nhánh Query Builder riêng. Model/validation/API naming/exception của module ngoài ownership còn legacy drift. Các mục này chỉ note, không sửa trong task Nhân viên/Phòng ban/Chức vụ nếu chưa được giao.

## Preflight và kiểm tra an toàn

Migrations Laravel hiện chỉ là hạ tầng (users/session/cache/queue); không giả định chúng tạo 15 bảng nghiệp vụ. `php artisan migrate:status` có thể báo thiếu bảng migrations.

Read-only preflight bằng DB client:

```sql
SELECT VERSION(), DATABASE(), @@session.time_zone;
SELECT TABLE_TYPE, COUNT(*) FROM information_schema.TABLES
 WHERE TABLE_SCHEMA = DATABASE() GROUP BY TABLE_TYPE;
SELECT ROUTINE_TYPE, COUNT(*) FROM information_schema.ROUTINES
 WHERE ROUTINE_SCHEMA = DATABASE() GROUP BY ROUTINE_TYPE;
```

Fresh MariaDB test chỉ chạy guarded disposable:

```powershell
pwsh -NoProfile -File tests/Support/invoke-employee-mariadb-tests.ps1 -EnableDisposableMariaDb
```

Wrapper phải giữ target disposable, process-scoped credential và cleanup. Không đọc `DB_DATABASE` để tự chọn target mutation, không in credential/hash, và không claim gate nếu wrapper chưa chạy thành công. SQLite/full test, route list, Vite build hoặc response `200` không chứng minh DDL/procedure MariaDB.

## Rủi ro cần giữ nguyên trong backlog

- Runtime production chưa chốt DBMS ngoài MariaDB 10.4.32.
- Không có quy trình rollout/backup/restore production đã được approval trong web.
- Module Lương, Chấm công, Nghỉ phép còn caller procedure thiếu như bảng trên.
- Hợp đồng và RBAC quản trị mới chỉ verified hẹp hoặc thiếu mutation/browser evidence.
- Không log raw SQL exception, SQLSTATE, credential, password hash hay filesystem path.
