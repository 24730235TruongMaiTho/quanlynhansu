# Handoff tiếp tục `quanlynhansu`

> Cập nhật 2026-08-27 trên local `main`, HEAD `f71c0b20a4e04e8e2ec32cdad2a68722e4aaa0b7`. Chỉ có thay đổi local của lát cắt Nhân viên và tài liệu; file `AIAssistantInput-a1d28494-8caf-4d5a-8217-4d71fad94b75.chatInput` là untracked của người dùng và phải giữ nguyên.

## Nguồn sự thật và ownership

- SQL fresh active là `database/sql/tao_bang.sql` → `database/sql/du_lieu_mau.sql` → `database/sql/quyen_vai_tro.sql`. Hợp đồng gồm 15 bảng, 19 nhân viên, 37 quyền và 12 thủ tục RBAC; các dump/script khác là lịch sử.
- Nhóm hiện chỉ sở hữu code Nhân viên, Phòng ban và Chức vụ. Code Dashboard, Lương, Chấm công, Nghỉ phép, Hợp đồng, Vai trò/Phân quyền/RBAC và API của đồng nghiệp chỉ được note, không tự sửa nếu chưa có task giao rõ.
- Không fetch, merge, rebase, cherry-pick, push, commit hoặc mutation database live trong phiên này. Không stage `docs/CODEX_FRONTEND_HANDOFF.md`.
- Khi tài liệu khác code hoặc database live, ưu tiên bằng chứng live rồi cập nhật tài liệu.

## Thay đổi Nhân viên trong phiên này

`App\Support\NhanVienScope` giữ policy tại ranh giới HTTP, không nhúng auth vào repository:

- Role `NhanVienRole::DepartmentManager` phải có `ma_pb` là số dương hợp lệ; thiếu/sai thì list rỗng an toàn và target trả 404.
- List luôn ép `ma_pb` bằng phòng ban actor, bỏ qua filter phòng ban do client gửi; lookup phòng ban trên UI chỉ còn phòng ban của actor.
- Show/edit/destroy kiểm tra target sau khi lookup; cross-department trả 404, không gọi mutation. `UpdateNhanVienRequest::authorize()` cũng kiểm tra target trước validation/mutation.
- Destroy chặn tự xóa trước khi gọi service bằng lỗi ổn định `Không thể tự xóa tài khoản đang đăng nhập.`. Index/show/edit và partial action không render nút phá hủy cho chính actor.
- Auth projection/repository hydrate `ma_pb`, giúp department scope tồn tại sau login/session restore.

## Bằng chứng đã chạy

- RED trước implementation: test scope mới fail vì thiếu `NhanVienScope`, auth model chưa hydrate `ma_pb`, manager list vẫn nhận `ma_pb` client và các target/self guards chưa có.
- Focused GREEN: `php artisan test tests/Unit/Support/NhanVienScopeTest.php tests/Feature/Backend/NhanVien/NhanVienDepartmentScopeTest.php tests/Unit/Models/NhanVienTest.php` → `14 tests, 66 assertions` pass.
- Employee/auth/regression slice → `110 tests, 1308 assertions` pass.
- Full Laravel sau thay đổi → `288 tests, 2222 assertions` pass.
- Phòng ban và Chức vụ không sửa code; các feature tests của hai module pass trong full suite.
- Route inventory → `79` route, command `php artisan route:list --except-vendor` pass.
- Frontend → `18` Node tests pass; Vite build pass với `19 modules transformed`.
- Composer → `composer validate --no-check-publish` pass; PHP lint các file sửa và `git diff --check` pass.
- Guarded MariaDB trên schema disposable → `phpunit.mariadb.xml`, PHPUnit `11.5.56`, PHP `8.5.0`, `12/12 tests, 422 assertions`, `10.797s`, exit `0`.
- Chưa chạy browser acceptance trong phiên này; MariaDB disposable không phải bằng chứng cho database live hoặc production.

## Trạng thái module

| Module | Trạng thái | Ghi chú |
| --- | --- | --- |
| Nhân viên | Verified hẹp | CRUD/lifecycle/auth/RBAC và scope Trưởng phòng có automated evidence; browser/production chưa claim |
| Phòng ban | Verified hẹp | Direct Query Builder, transaction/row lock và Gate canonical; code không đổi phiên này, browser chưa claim |
| Chức vụ | Verified hẹp | Direct Query Builder, transaction/row lock và Gate canonical; code không đổi phiên này, browser chưa claim |
| Dashboard | Prototype | Chỉ ghi nhận auth/permission riêng; không sửa trong scope |
| Lương | Prototype/blocked | `LuongRepository@all` gọi `sp_luong_tim_kiem_phan_trang`, procedure không có trong ba SQL active |
| Chấm công | Prototype/blocked | Lookup gọi `sp_phong_ban_danh_sach`, update gọi `sp_cham_cong_cap_nhat`; không có trong ba SQL active |
| Nghỉ phép | Prototype/blocked | Approve gọi `sp_nghi_phep_duyet_phep`; không có trong ba SQL active |
| Hợp đồng | Planned/scaffold | Chưa có mutation/browser evidence đủ |
| Vai trò/Phân quyền/RBAC | Nền tảng verified hẹp | Catalog và 12 RBAC procedure active có test hẹp; UI quản trị/mutation/browser chưa đóng |

Model/validation legacy drift, API naming và exception contract của module ngoài ownership vẫn là backlog; không sửa để làm sạch ngoài phạm vi.

## Lệnh tiếp tục

```powershell
git status --short --branch
php artisan route:list --except-vendor
php artisan test
npm run test:frontend
npm run build
composer validate --no-check-publish
git diff --check
```

MariaDB chỉ chạy bằng wrapper guarded trên disposable target:

```powershell
pwsh -NoProfile -File tests/Support/invoke-employee-mariadb-tests.ps1 -EnableDisposableMariaDb
```

Không claim MySQL 8, production rollout, browser acceptance hoặc DB live mutation nếu chưa có bằng chứng và approval riêng.

## Handoff feedback UI 2026-08-28

Đã triển khai feedback thuộc ownership trong `docs/FEEDBACK_ACTION_PLAN.md`: Be Vietnam Pro, nhãn sidebar, pagination/filter/action select cho Chức vụ/Phòng ban/Nhân viên, grouped employee detail với avatar lớn và form edit accessible. CV/PB dùng Query Builder `paginate()` nhưng giữ `all()`; delete action chỉ submit sau confirm và Gate/guard hiện hữu. Không sửa Dashboard, Lương, Chấm công, Nghỉ phép, Hợp đồng, RBAC hoặc schema.

Verification cuối phiên: focused module/regression `53 tests, 481 assertions` và repository pagination `14 tests, 64 assertions` pass; full Laravel `294 tests, 2287 assertions` pass; frontend `21/21` pass; Vite `21 modules transformed`; route inventory `79`, Composer, PHP lint và `git diff --check` pass. MariaDB disposable: `phpunit.mariadb.xml`, PHPUnit `11.5.56`, PHP `8.5.0`, `12/12 tests`, `422 assertions`, `10.797s`, exit `0`; đây không phải database live. Browser acceptance, font/network thật và production chưa được kiểm chứng.
