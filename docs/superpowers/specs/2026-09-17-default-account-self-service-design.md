# Thiết kế quyền tự phục vụ mặc định cho mọi tài khoản

Ngày soạn thiết kế: 2026-09-17.

## 1. Mục tiêu

Mọi tài khoản nhân viên đang hoạt động và đăng nhập thành công, không phụ thuộc
vai trò, luôn được dùng năm nhóm chức năng tự phục vụ sau:

1. Xem và cập nhật thông tin cá nhân.
2. Tạo đơn nghỉ phép và xem lịch sử đơn của chính mình.
3. Xem hợp đồng của chính mình.
4. Xem lương của chính mình.
5. Xem chấm công của chính mình.

Đây là năng lực cơ bản của tài khoản, không phải quyền quản trị được cấp qua
`vai_tro_quyen`.

## 2. Nguyên tắc phân quyền

Hệ thống tách hai lớp truy cập:

- **Self-service**: chỉ cần middleware `auth`; backend luôn lấy `ma_nv` từ tài
  khoản đang đăng nhập và chỉ đọc hoặc ghi dữ liệu của chính tài khoản đó.
- **Quản trị nghiệp vụ**: tiếp tục dùng các quyền `HopDong.*`, `NghiPhep.*`,
  `ChamCong.*`, `Luong.*` hiện có để đọc hoặc thay đổi dữ liệu trong phạm vi
  công ty.

Không dùng `Gate::before` để tự động cấp các quyền `*.Read` cho mọi tài khoản,
vì các quyền đó đang bảo vệ trang quản trị và có thể mở danh sách, bộ lọc hoặc
export toàn công ty. Không sao chép cùng một bộ quyền vào mọi vai trò.

Tất cả endpoint self-service phải bỏ qua hoặc từ chối mọi `ma_nv` do client
gửi. Nếu actor không phải `NhanVien`, hoặc mã đăng nhập không đúng năm chữ số,
request dừng fail-closed với `403`. Việc truy cập một tài nguyên có ID nhưng
không thuộc actor trả `404` để không tiết lộ sự tồn tại của dữ liệu người khác.

## 3. Contract route và dữ liệu

Các route tĩnh `cua-toi` phải được khai báo trước resource route có tham số để
không bị Laravel bắt thành `{id}`.

### 3.1. Tài khoản cá nhân

Giữ contract hiện tại:

- `GET /ho-so-ca-nhan`
- `PATCH|PUT /ho-so-ca-nhan`
- `GET /doi-mat-khau`
- `PATCH|PUT /doi-mat-khau`

Giữ nguyên các route name `backend.profile.edit`, `backend.profile.update`,
`backend.profile.password.edit` và `backend.profile.password.update`.

Các route chỉ dùng `auth`. Request cập nhật tiếp tục giữ allowlist trường tự
phục vụ; không cho đổi mã nhân viên, vai trò, phòng ban, chức vụ, trạng thái,
lương hoặc các trường hệ thống.

### 3.2. Nghỉ phép của tôi

- Web: `GET /tao-nghi-phep`, chỉ dùng `auth`.
- API lịch sử: `GET /api/v1/nghi-phep/cua-toi`, chỉ dùng `auth`.
- API tạo đơn cá nhân: `POST /api/v1/nghi-phep/cua-toi`, chỉ dùng `auth`.

Route name lần lượt là `backend.nghiphep.create`,
`api.v1.nghi-phep.cua-toi` và `api.v1.nghi-phep.cua-toi.store`.

API tạo đơn cá nhân luôn ghi đè `ma_nv` bằng actor và đặt trạng thái ban đầu
theo contract hiện hành. Client không được chọn nhân viên hoặc trạng thái duyệt.
`POST /api/v1/nghi-phep` hiện có vẫn là endpoint quản trị/tạo thay và tiếp tục
yêu cầu `NghiPhep.Insert`.

Self-service không mặc định cho phép sửa, xóa hoặc duyệt đơn. Các hành vi đó
vẫn phụ thuộc quyền quản trị hiện hành cho đến khi có yêu cầu nghiệp vụ khác.

### 3.3. Hợp đồng của tôi

- Web: `GET /hop-dong-cua-toi`, chỉ dùng `auth`.

Route name là `backend.selfservice.hopdong.index`.

Trang được render server-side từ truy vấn bắt buộc `hop_dong.ma_nv = actor`.
Hiển thị danh sách hợp đồng của actor, mới nhất trước, gồm loại hợp đồng, ngày
ký, ngày hết hạn và lương cơ bản. Không hiển thị bộ lọc nhân viên, danh mục quản
trị, hoặc nút tạo/sửa/xóa. Trạng thái không có hợp đồng phải rõ ràng và không
được coi là lỗi.

Route `/hop-dong` và các mutation hiện có giữ nguyên middleware
`HopDong.Read|Insert|Update|Delete` và phạm vi quản trị.

### 3.4. Lương của tôi

- Web: `GET /luong-cua-toi`, chỉ dùng `auth`.
- API: `GET /api/v1/luong/cua-toi`, chỉ dùng `auth`.

Route name lần lượt là `backend.selfservice.luong.index` và
`api.v1.luong.cua-toi`.

API chỉ nhận bộ lọc kỳ lương và phân trang; không nhận bộ lọc nhận diện nhân
viên, phòng ban hoặc chức vụ. Service/repository luôn áp dụng điều kiện mã nhân
viên chính xác trước khi phân trang. Không cung cấp tạo, sửa, xóa hoặc export
toàn công ty trong trang self-service.

Các route `/luong` và `/api/v1/luong...` hiện có vẫn là contract quản trị và
tiếp tục yêu cầu `Luong.*`.

### 3.5. Chấm công của tôi

- Web: `GET /cham-cong-cua-toi`, chỉ dùng `auth`.
- API: `GET /api/v1/cham-cong/cua-toi`, chỉ dùng `auth`.

Route name lần lượt là `backend.selfservice.chamcong.index` và
`api.v1.cham-cong.cua-toi`.

API chỉ nhận `thang`, `nam`, `page`, `per_page`; `ma_nv` luôn lấy từ actor.
Response giữ shape danh sách và summary canonical của chấm công. Trang chỉ có
bộ chọn kỳ và bảng read-only; không có danh sách nhân viên, phòng ban, import,
export, lưu, sửa hoặc xóa.

Các route `/cham-cong` và `/api/v1/cham-cong...` hiện có vẫn là contract quản
trị và tiếp tục yêu cầu `ChamCong.*`.

## 4. Thành phần dùng chung

Tạo một boundary nhỏ chịu trách nhiệm lấy mã nhân viên hiện tại từ actor đã
authenticate. Boundary này:

- chỉ chấp nhận model `NhanVien`;
- chỉ trả mã khớp `\A[0-9]{5}\z`;
- không nhận mã từ query, form hoặc route;
- fail-closed thay vì trả mã rỗng hoặc `null` cho truy vấn nghiệp vụ.

Các controller self-service dùng boundary này để tránh lặp logic và tránh một
module vô tình tin dữ liệu client. Repository/service nhận `ma_nv` đã xác thực
qua tham số bắt buộc, không dùng tham số nullable cho các phương thức self.

## 5. Giao diện

Sidebar có một nhóm luôn hiển thị cho actor đã đăng nhập, tên **Thông tin của
tôi**, gồm:

- Tài khoản cá nhân.
- Đơn nghỉ phép của tôi.
- Hợp đồng của tôi.
- Lương của tôi.
- Chấm công của tôi.

Các nhóm `Quản lý ...` hiện có tiếp tục phụ thuộc `PermissionService`. Người có
quyền quản trị có thể thấy cả liên kết self-service và liên kết quản trị; nhãn
“của tôi” và “Quản lý” phải phân biệt rõ hai phạm vi.

Trang self-service dùng layout, card, bảng, pagination, loading, empty và error
state hiện hành; không tạo design system mới. Mọi bảng có caption/accessible
name, control có label, focus nhìn thấy được và hoạt động ở viewport hẹp.

## 6. RBAC và SQL

Fresh seed phải bỏ chính xác ba mapping `(5,25)`, `(5,26)`, `(5,33)`, tương ứng
`NghiPhep.Read`, `NghiPhep.Insert` và `Luong.Read`, khỏi role Nhân viên. Các
quyền quản trị vẫn tồn tại trong catalog và tiếp tục được gán cho các vai trò
nghiệp vụ phù hợp. Self-service không được phụ thuộc vào các mapping này.

Việc gỡ các mapping legacy `(5,25)`, `(5,26)`, `(5,33)` khỏi database đang có
dữ liệu là thay đổi quyền có tác động. Runtime self-service không phụ thuộc vào
việc gỡ này. Nếu cần hòa giải database hiện hữu, phải dùng script riêng có:

- kiểm tra chính xác metadata quyền và role 5;
- backup/preflight và phê duyệt trước khi chạy;
- transaction, exact-target delete và post-check;
- không chạy tự động trong ứng dụng hoặc trong lượt triển khai code.

Script additive cũ cấp ba quyền trên phải được đánh dấu superseded hoặc cập
nhật tài liệu để không tiếp tục được dùng cho thiết kế mới.

## 7. Lỗi và trạng thái HTTP

- Guest: redirect login đối với web; `401` đối với JSON.
- Actor không hợp lệ: `403` với thông báo công khai ổn định.
- ID tài nguyên không thuộc actor: `404`.
- Validation query/body: `422` theo contract Laravel hiện tại.
- Không có hợp đồng, lương hoặc chấm công: `200` với empty state, không phải
  `404`.
- Lỗi persistence/query: log nội bộ, response không chứa SQL, stack trace hoặc
  dữ liệu của nhân viên khác.

## 8. Kiểm thử bắt buộc

TDD phải chứng minh RED trước khi sửa production. Bộ regression tối thiểu:

1. Mỗi role canonical 1–5, dù không có quyền module quản trị tương ứng, đều mở
   được năm luồng self-service khi tài khoản còn hoạt động.
2. Guest không truy cập được các route self-service.
3. Query/body `ma_nv` giả mạo bị bỏ qua hoặc bị từ chối; response chỉ chứa actor.
4. ID hợp đồng/lương/nghỉ phép của người khác không thể đọc hoặc thay đổi.
5. Tạo đơn self-service luôn lưu actor và trạng thái ban đầu; không cho client
   tạo thay hoặc tự duyệt.
6. Các route quản trị vẫn `403` khi actor thiếu quyền module.
7. Self-service chấm công không gọi endpoint danh sách nhân viên/phòng ban và
   không render import/export/mutation controls.
8. Sidebar luôn có nhóm “Thông tin của tôi”, còn nhóm quản trị vẫn theo RBAC.
9. Response lỗi không lộ SQL hoặc dữ liệu cross-owner.

Sau targeted tests, chạy full Laravel, toàn bộ frontend, Vite build, route
inventory/duplicate audit, PHP lint, Composer validation và `git diff --check`.
Browser acceptance dùng ít nhất một account không có quyền quản trị để kiểm tra
năm liên kết và dữ liệu chính chủ; không tạo đơn hoặc mutation dữ liệu live.

## 9. Phạm vi không thực hiện

- Không thay đổi quy trình duyệt nghỉ phép.
- Không cấp quyền sửa hợp đồng, lương hoặc chấm công cho self-service.
- Không cho self-service export dữ liệu toàn công ty.
- Không chạy SQL hoặc mutation trên MariaDB/live database.
- Không thay đổi authentication, vòng đời tài khoản hoặc catalog vai trò ngoài
  phần tách mapping self-service legacy đã mô tả.

## 10. Tiêu chí hoàn thành

Tính năng chỉ được coi là hoàn thành khi mỗi role có thể đọc đúng dữ liệu của
chính mình qua route self-service, mọi thử nghiệm cross-owner đều fail-closed,
route quản trị vẫn giữ quyền hiện hữu, UI phân biệt rõ “của tôi” với “Quản lý”,
và toàn bộ kiểm tra nêu trên có bằng chứng fresh. Build hoặc response `200`
đơn lẻ không đủ để kết luận hoàn thành.
