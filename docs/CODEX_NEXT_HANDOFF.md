# Handoff tiếp tục đồ án `quanlynhansu`

> Snapshot: 2026-08-11 (Asia/Saigon)
>
> Branch: `main`
>
> HEAD audit: `643563c029e10a49636f1a6f2e70b4e427f1dc7e`
>
> Remote: `main` bằng `origin/main` tại thời điểm audit
>
> Phạm vi: code/route/render hẹp/test/build/SQL/MariaDB read-only; không sửa nghiệp vụ, không ghi DB.

## Đọc trước

1. `AGENTS.md`.
2. [docs/README.md](README.md).
3. [PROJECT_STATUS.md](PROJECT_STATUS.md).
4. Code, test và SQL liên quan trực tiếp tới task.

Không dùng snapshot này nếu HEAD đã đổi mà chưa chạy lại kiểm tra.

## Baseline live

- PHP 8.5.0; project target PHP 8.2+.
- Laravel 12.62.0.
- 44 app route: 17 web, 27 API.
- `npm run build`: pass, Vite 7.3.6.
- `php artisan test`: 1 pass, 1 fail vì `/` trả 404; suite dùng SQLite in-memory, không chứng minh routine MariaDB.
- Composer metadata: valid.
- Runtime app: DB mysql, session/cache file, queue sync.
- MariaDB 10.4.32 reachable; schema có 14 table, 1 view, 8 function, 10 trigger, 63 procedure.
- Migrations chưa chạy; `migrate:status` báo không có bảng migrations.

## Phân loại đúng

- **Verified hẹp:** Laravel boot, route registration, build, một số Blade page/lookup endpoint trả 200 trên DB rỗng.
- **Prototype:** dashboard, nhân viên, lương, chấm công, nghỉ phép, hệ số lương.
- **Blocked:** home/landing, phòng ban, danh sách lương, pagination chấm công.
- **Planned:** auth/RBAC, hợp đồng, vai trò/quyền/tài khoản, báo cáo; backup/restore vẫn là unsafe legacy procedures.

Không gọi lương/chấm công/nghỉ phép “hoàn thành” chỉ vì có UI/API code.

## Blocker P0

1. Không có route `/`; `/admin` gọi view `frontend.home` không tồn tại.
2. Năm web route trỏ method không tồn tại ở `PhongBanController`/`NhanVienController`.
3. Tên route lương/chấm công/nghỉ phép bị lặp `backend.backend.*`.
4. Phòng ban có Blade lỗi, procedure chi tiết thiếu và update sai placeholder.
5. Code gọi bốn procedure không có trong dump/DB:
   - `sp_phong_ban_chi_tiet`
   - `sp_cham_cong_nhan_vien_phan_trang`
   - `sp_cham_cong_chi_tiet_phan_trang`
   - `sp_luong_tim_kiem_phan_trang`
6. `.env.example` dùng SQLite/database session-cache-queue, lệch runtime nghiệp vụ.
7. Chưa có auth middleware hoặc permission checks.
8. Database nghiệp vụ hiện rỗng và chưa có master-data seed; các FK nhân viên cần phòng ban/chức vụ/trạng thái/vai trò trước mutation.
9. Laravel dùng UTC trong khi MariaDB system timezone là UTC+7; `now()` và `CURDATE()` có thể lệch kỳ.

## Branch frontend

Local branch `frontend` ở `940e7cc`; merge-base với main là `063c669`; hai phía có 17/20 commit riêng.

Shell contract Header + Sidebar + Main + Footer/no global navbar đã được duyệt và pass automated render/controller/build tests trên branch đó; browser acceptance chưa hoàn tất và shell **chưa có trên main**. Xem [ADR-001](decisions/ADR-001-admin-shell.md).

Không fetch/merge/rebase/cherry-pick/push/tạo upstream hoặc worktree nếu người dùng chưa yêu cầu.

`docs/CODEX_FRONTEND_HANDOFF.md` là local-only qua `.git/info/exclude`; không stage file này và không coi nó là source of truth cho main.

## Task tiếp theo đề xuất

Ưu tiên một task “stabilize baseline”, không mở thêm module:

1. Chốt DBMS, timezone và setup `.env.example`.
2. Tạo master-data seed tối thiểu.
3. Chốt contract route home và API naming/error status.
4. Chốt cách bổ sung/thay bốn procedure thiếu và write contract lương.
5. Hoàn thiện auth/data safety cần thiết.
6. Viết integration branch plan cho shell như một workstream riêng; không merge ngay.
7. Sau đó hoàn thiện phòng ban thành vertical slice mẫu.

Roadmap đầy đủ: [ROADMAP.md](ROADMAP.md).

## Checklist phiên tiếp theo

```powershell
Get-Content -Raw AGENTS.md
Get-Content -Raw docs/CODEX_NEXT_HANDOFF.md

git status --short --branch
git rev-parse HEAD
php artisan about --only=environment,drivers
php artisan route:list --except-vendor
php artisan test
npm run build
```

Nếu task dùng database:

- Đọc [DATABASE.md](DATABASE.md).
- Chỉ test mutation trên database disposable.
- Kiểm tra procedure signature trước khi sửa PHP.

## Giới hạn bằng chứng

- Không clean-replay dump.
- Không chạy mutation nghiệp vụ.
- Không có browser acceptance mới.
- Response 200 trên database rỗng không chứng minh workflow.
- Vite build không chứng minh API/DB.
- `php artisan db:show` có thể lỗi vì MariaDB local thiếu `performance_schema.session_status`; không dùng riêng lỗi này để kết luận mất kết nối.
