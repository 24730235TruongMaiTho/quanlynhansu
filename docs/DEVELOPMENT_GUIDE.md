# Hướng dẫn phát triển

## Bắt đầu một phiên

```powershell
Get-Content -Raw AGENTS.md
Get-Content -Raw docs/CODEX_NEXT_HANDOFF.md

git status --short --branch
git rev-parse HEAD
php artisan route:list --except-vendor
```

Sau đó đọc [PROJECT_STATUS.md](PROJECT_STATUS.md) và các file route/controller/request/model/view/JavaScript/SQL liên quan trực tiếp tới task.

Không dùng snapshot cũ thay cho kiểm tra live. Không fetch, merge, rebase, push, tạo upstream hoặc worktree nếu người dùng chưa yêu cầu.

## Setup

```powershell
composer install
Copy-Item .env.example .env
php artisan key:generate
npm install
```

Trước khi chạy module nghiệp vụ:

1. Chỉnh `.env` sang MariaDB/MySQL.
2. Dùng `session=file`, `cache=file`, `queue=sync` khi migrations hạ tầng chưa chạy.
3. Chốt cùng timezone cho Laravel và database; `config/app.php` hiện cố định UTC.
4. Import dump theo [DATABASE.md](DATABASE.md) vào database disposable.
5. Tạo master data tối thiểu trước khi test mutation có khóa ngoại.
6. Không chạy `composer setup` hoặc `db:seed` như lệnh mặc định cho đến khi các lệch setup được sửa.

Chạy local:

```powershell
# Terminal 1
php artisan serve

# Terminal 2
npm run dev
```

## Cách triển khai một module

Làm theo một vertical slice nhỏ:

```text
Route
  → Form Request / validation
  → Controller
  → Service / Repository hoặc query đã chốt
  → Schema / stored procedure
  → JSON contract hoặc Blade
  → JavaScript/UI states
  → Feature + integration test
```

Trước khi viết code:

- Xác định endpoint và tên route.
- Đọc chữ ký procedure thật.
- Ghi input/output/error contract.
- Kiểm tra tên bảng, khóa chính, casts và timestamps.
- Xác định route có cần auth/quyền gì.
- Liệt kê loading, empty, success, validation error và server error.

Không tạo hàng loạt controller/model rỗng để đánh dấu tiến độ.

## Quy ước code

### PHP/Laravel

- Namespace dùng `App\...`.
- Controller extend base controller Laravel.
- Route name dùng `backend.<module>.<action>` hoặc `api.v1.<module>.<action>`.
- Blade path phải khớp chính xác thư mục và dùng chữ thường.
- Validation ở server; không tin dữ liệu JavaScript.
- Không trả raw exception/SQL message cho client.
- Nếu dùng stored procedure, placeholder và binding phải đúng số lượng/thứ tự.

### Database

- Tên bảng/cột dùng `snake_case`.
- Model phải khai báo table/primary key/timestamps khi khác mặc định.
- Không import dump lên database có dữ liệu.
- Không test mutation trên database dùng chung.

### Frontend

- Không trộn thêm một design system mới.
- Dùng semantic HTML, label, keyboard focus và thông báo `aria-live`.
- Escape dữ liệu trước khi chèn bằng `innerHTML`.
- Không gọi UI “responsive/accessibility đạt” nếu chưa browser-test.
- Đọc [FRONTEND_GUIDE.md](FRONTEND_GUIDE.md) trước khi đổi layout/assets.

### Format

- UTF-8, LF, 4 spaces, newline cuối file.
- Không sửa `vendor`, `node_modules`, `storage/framework` hoặc `public/build`.
- Không commit `.env`, secret hoặc dữ liệu cá nhân.

## Kiểm tra theo loại thay đổi

| Phạm vi | Kiểm tra tối thiểu |
| --- | --- |
| PHP | `php -l <file>`, route list, test liên quan |
| Route/controller | `php artisan route:list --except-vendor`, feature test |
| Blade | render test hoặc smoke request, `php artisan view:cache` khi phù hợp |
| JavaScript/CSS/Vite | syntax/test frontend nếu có, `npm run build` |
| Stored procedure | signature check + integration test trên DB disposable |
| UI | desktop/tablet/mobile, keyboard, focus, console, network |
| Documentation | link/path check, command check, `git diff --check` |

Baseline hiện tại có một test fail. Khi thêm thay đổi, báo riêng:

- lỗi baseline đã có;
- test mới/targeted pass hay fail;
- full suite tốt hơn, giữ nguyên hay xấu đi.

`phpunit.xml` hiện ép SQLite in-memory. Không dùng build pass hoặc test suite này để kết luận procedure/trigger MariaDB pass; cần integration suite riêng trên database disposable.

## Definition of Done

Một task module hoàn thành khi:

- [ ] Route/action/name đúng và route cần thiết được bảo vệ.
- [ ] Request validation khớp schema.
- [ ] Data contract có test.
- [ ] Model/procedure/query khớp database.
- [ ] UI có đủ trạng thái và không dùng fixture giả như dữ liệu thật.
- [ ] Feature/integration test pass.
- [ ] Build pass.
- [ ] Browser acceptance phù hợp pass hoặc được ghi rõ là blocked.
- [ ] Docs/status được cập nhật.
- [ ] `git diff --check` sạch và phạm vi Git đúng task.

## Git/GitHub

Đề xuất branch:

```text
feature/<module>
fix/<bug>
docs/<scope>
test/<scope>
refactor/<scope>
```

Quy trình:

1. Bắt đầu từ branch được nhóm chỉ định.
2. Kiểm tra worktree và bảo toàn thay đổi của người khác.
3. Commit nhỏ, mỗi commit một mục đích.
4. Chạy kiểm tra phù hợp.
5. Pull request ghi scope, evidence, risk và ảnh UI nếu có.
6. Không force-push branch dùng chung.

Nhánh local `frontend` và `main` đang phân kỳ. Mọi tích hợp shell phải có task/branch/plan riêng; không merge tự động.

## Làm việc với Codex/AI

Nguồn bắt buộc:

1. `AGENTS.md`.
2. `docs/CODEX_NEXT_HANDOFF.md`.
3. Code/SQL/test live.
4. Instruction và skill trong `.codex/`.

MCP code graph giúp tìm symbol, call path và cluster; IntelliJ MCP giúp đọc project/runtime/DB metadata. Luôn kiểm tra lại bằng Laravel CLI và file live vì parser MCP có thể bỏ sót route hoặc suy luận sai call cùng tên.

Prompt nên nêu:

- module;
- kết quả mong muốn;
- phạm vi file;
- database/test được phép dùng;
- có hay không quyền commit/push/merge.
