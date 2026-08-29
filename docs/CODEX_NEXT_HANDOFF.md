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

## Handoff modal sửa Nhân viên 2026-08-29

`/nhan-vien` hiện render một native dialog duy nhất khi actor có `NhanVien.Update`; mỗi action `Chỉnh sửa` giữ href edit thật làm progressive fallback và chỉ tải partial form khi mở. GET edit partial vẫn qua Gate và `NhanVienScope`; trang edit đầy đủ không đổi contract. Submit modal gửi `FormData` tới PUT/PATCH hiện hữu, hỗ trợ avatar, khóa nút khi đang gửi, khóa nút đóng/cancel/Escape để không nhận response lệch modal, hiển thị lỗi 422 theo field hoặc form-level khi khóa lỗi không map được, lỗi mạng/server an toàn, khôi phục focus khi đóng và reload đúng URL list hiện tại sau JSON success. Shared `row-action-select` chỉ thêm callback `modal`, nên Xem/Xóa và CV/PB giữ nguyên.

RED/GREEN: feature modal/update ban đầu fail vì thiếu shell/partial/JSON; các hồi quy listener submit, khóa đóng/cancel/Escape và form-level 422 cũng đã RED trước khi sửa, sau triển khai targeted Nhân viên `32 tests, 309 assertions` pass và Node modal/shared/list `16 tests` pass. Relevant Nhân viên/auth/service/unit suite pass `177 tests, 1518 assertions`; full Laravel hiện `290 passed, 11 failed, 2252 assertions` do các failure baseline ngoài ownership ở ContentFour/Chấm công/Nghỉ phép. Full frontend `31 passed, 1 failed` do đúng baseline Nghỉ phép `expected —, actual -`; build pass `25 modules transformed`, route inventory pass `89 routes`, Composer/PHP lint/diff hygiene pass. Browser chưa chạy; không sửa lỗi ngoài ownership.

## Handoff feedback UI 2026-08-28

Đã triển khai feedback thuộc ownership trong `docs/FEEDBACK_ACTION_PLAN.md`: Be Vietnam Pro, nhãn sidebar, pagination/filter/action select cho Chức vụ/Phòng ban/Nhân viên, grouped employee detail với avatar lớn và form edit accessible. CV/PB dùng Query Builder `paginate()` nhưng giữ `all()`; delete action chỉ submit sau confirm và Gate/guard hiện hữu. Không sửa Dashboard, Lương, Chấm công, Nghỉ phép, Hợp đồng, RBAC hoặc schema.

Verification cuối phiên: focused module/regression `53 tests, 481 assertions` và repository pagination `14 tests, 64 assertions` pass; full Laravel `294 tests, 2287 assertions` pass; frontend `21/21` pass; Vite `21 modules transformed`; route inventory `79`, Composer, PHP lint và `git diff --check` pass. MariaDB disposable: `phpunit.mariadb.xml`, PHPUnit `11.5.56`, PHP `8.5.0`, `12/12 tests`, `422 assertions`, `10.797s`, exit `0`; đây không phải database live. Browser acceptance, font/network thật và production chưa được kiểm chứng.

## Handoff modal Phòng ban/Chức vụ và liên kết edit Nhân viên 2026-08-29

Phòng ban và Chức vụ dùng native dialog chung, chỉ tải partial form khi chọn Sửa; href edit thật vẫn là progressive fallback. Controller nhận header modal để trả partial, còn direct GET trả trang đầy đủ; update JSON success/422/lỗi server chỉ dùng thông báo an toàn. Gate canonical, CSRF, FormRequest, Query Builder, transaction/row lock và delete/view/filter behavior không đổi. row-action-select chỉ gọi callback modal, không điều hướng khi mở modal.

Trang xem Nhân viên thêm trigger có href thật và dùng lại shell data-employee-edit-modal hiện có khi actor có Gate cập nhật; no-JavaScript/direct-link vẫn dùng edit page. Step 3 form bọc từng cặp dữ liệu trong employee-review-row để border liên tục và responsive theo một cột ở màn hình hẹp.

TDD/verification: RED feature 8 failure và Node 2 failure trước implementation; GREEN targeted PB/CV/Nhân viên 51 tests, 487 assertions, targeted Node core 25 tests pass. Full Laravel hiện 298 passed, 11 failed, 2321 assertions do baseline ngoài ownership; frontend 36 passed, 1 failed do tests/Frontend/nghiphep/employee-response.test.js expected —, actual -. Vite build 26 modules transformed; route inventory 89 routes; Composer, PHP lint controller và git diff --check pass. Browser chưa kiểm chứng, MariaDB không chạy vì data layer không đổi; không claim database live/production.
