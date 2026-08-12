# Đặc tả thiết kế chức năng quản lý nhân viên

- Ngày chốt thiết kế: 2026-08-12
- Nhánh triển khai: `feature/quanly-nhan-vien`
- Trạng thái: thiết kế đã được người dùng duyệt; chưa triển khai
- Phạm vi bằng chứng hiện tại: repository và MariaDB local; chưa phải xác nhận sẵn sàng production

## 1. Mục tiêu

Xây dựng một vertical slice quản lý nhân viên có thể hoạt động độc lập, gồm:

- xem danh sách, tìm kiếm, lọc và phân trang;
- xem chi tiết;
- tạo hồ sơ nhân viên đồng thời tạo tài khoản đăng nhập;
- sửa hồ sơ, phòng ban, chức vụ, vai trò và trạng thái làm việc;
- lưu địa chỉ và ảnh đại diện;
- xóa cứng khi chưa có lịch sử nghiệp vụ, hoặc chuyển sang `DA_NGHI` khi đã có lịch sử;
- đặt lại mật khẩu;
- đăng nhập bằng mã nhân viên hoặc email;
- kiểm soát truy cập bằng quyền nghiệp vụ của module.

Stored procedure là bắt buộc cho các truy vấn/mutation nghiệp vụ của repository nhân viên và việc nạp tài khoản xác thực; không dùng Eloquent/Query Builder để thay thế CRUD nhân viên. Laravel vẫn chịu trách nhiệm về HTTP, validation (kể cả pre-check thân thiện), transaction orchestration, hash mật khẩu, lưu file và ánh xạ lỗi. Database constraint vẫn là tuyến bảo vệ cuối trước race condition.

## 2. Ngoài phạm vi của vertical slice

- CRUD phòng ban, chức vụ, vai trò và trạng thái làm việc.
- CRUD hợp đồng, loại hợp đồng và lương cơ bản trong hợp đồng.
- Gộp hoặc merge nhánh của đồng nghiệp.
- Thay đổi global layout hoặc nhập shell từ nhánh `frontend`.
- Tạo JSON API mới cho giao diện nhân viên đầu tiên.
- Chứng nhận bảo mật hoặc sẵn sàng production.

Phòng ban, chức vụ, vai trò và trạng thái là master data bắt buộc phải tồn tại để tạo nhân viên. Module nhân viên chỉ đọc các lookup này; nếu thiếu, giao diện phải báo rõ và khóa thao tác lưu.

## 3. Bằng chứng và vấn đề hiện tại

Schema hiện tại đã có bảng `nhan_vien` và bảy procedure nhân viên, nhưng không thể dùng nguyên trạng vì:

- các procedure đọc dùng `SELECT *` qua view có cột `mat_khau`;
- mật khẩu được hash SHA-256 không salt trong database;
- cập nhật với mật khẩu rỗng có thể reset về `123456`;
- tham số email của procedure ngắn hơn cột email;
- tìm kiếm chưa phân trang;
- email và CCCD chưa được bảo vệ bằng unique constraint;
- procedure xóa tự mở và commit transaction;
- `NhanVienController` và các view hiện chỉ là prototype/hard-code;
- `NghiPhepController` đang gọi trực tiếp chữ ký cũ của `sp_nhan_vien_them` và `sp_nhan_vien_sua`.

Vì vậy bộ procedure nhân viên sẽ được thiết kế lại. Trước khi thay chữ ký procedure cũ, mọi call site trong repository phải được chuyển đồng thời; không được làm hỏng âm thầm hai endpoint nhân viên đang nằm trong module nghỉ phép.

## 4. Quyết định nghiệp vụ đã chốt

### 4.1. Nhân viên đồng thời là tài khoản

- Một bản ghi `nhan_vien` cũng là một tài khoản Laravel.
- Có thể đăng nhập bằng `ma_nv` hoặc email.
- Email được trim, chuyển chữ thường và phải duy nhất.
- Nhân viên ở trạng thái `DA_NGHI` không được đăng nhập.

### 4.2. Mật khẩu mặc định

- Công thức: `nhom3@{năm tạo}`, ví dụ tạo năm 2026 là `nhom3@2026`.
- Không có cờ bắt buộc đổi mật khẩu.
- Năm được Laravel lấy theo timezone `Asia/Ho_Chi_Minh`.
- Cấu hình timezone của Laravel và database session phải cùng là `Asia/Ho_Chi_Minh`; các ngày nghiệp vụ vẫn được Laravel truyền tường minh vào procedure.
- Laravel tạo hash bằng `Hash::make`; procedure chỉ nhận và lưu hash.
- Không đưa mật khẩu/hash vào procedure danh sách, chi tiết, response UI hoặc log.
- Procedure tra cứu đăng nhập là ngoại lệ kỹ thuật: chỉ trả hash cho repository xác thực phía server.
- Đặt lại mật khẩu sử dụng `nhom3@{năm đặt lại}` và một hash mới do Laravel tạo.

Đây là quy ước cho đồ án demo nội bộ. Mật khẩu có thể đoán được và việc không bắt đổi mật khẩu là rủi ro được chấp nhận cho phạm vi này, không phải thiết kế production.

### 4.3. Mã nhân viên

- Giữ kiểu `VARCHAR(5)` và định dạng `NV001` đến `NV999`.
- Quản trị viên không nhập mã; hệ thống sinh mã khi lưu.
- Dùng một bảng đếm một dòng và khóa `SELECT ... FOR UPDATE` trong transaction ngoài của Laravel.
- Không dùng `MAX(ma_nv) + 1`.
- Mã đã commit không được cấp lại, kể cả sau khi xóa cứng nhân viên.
- Transaction bị rollback có thể dùng lại số vì mã đó chưa từng được commit.
- Khi vượt `NV999`, thao tác dừng với lỗi miền rõ ràng; không tự đổi định dạng.

### 4.4. Xóa hoặc kết thúc làm việc

- Nếu nhân viên đã ở `DA_NGHI`: không xét lại để xóa cứng; giữ nguyên ngày nghỉ đầu tiên và trả `TERMINATED`. Như vậy action có tính idempotent và không xóa tài khoản lịch sử do dữ liệu phụ thuộc bị dọn về sau.
- Với nhân viên chưa nghỉ và không có bản ghi liên quan trong `hop_dong`, `cham_cong`, `nghi_phep`, `luong` và `lich_su_he_so_luong`: xóa nhân viên trong transaction; địa chỉ bị xóa theo foreign key cascade; trả action `DELETED`.
- Với nhân viên chưa nghỉ và có ít nhất một phụ thuộc: không xóa; chuyển trạng thái sang `DA_NGHI`, ghi `ngay_nghi_viec`, trả action `TERMINATED`.
- Ngày nghỉ việc do Laravel truyền vào theo timezone đã chốt, không phụ thuộc `CURDATE()` của database.
- Cả hai kết quả phải được giao diện thông báo khác nhau.

## 5. Kiến trúc

Luồng chính:

```text
Blade form/list
  -> FormRequest
  -> NhanVienController
  -> NhanVienService (transaction, hash, file compensation)
  -> NhanVienRepository (CALL procedure, consume result sets)
  -> MariaDB stored procedures
```

Phân công trách nhiệm:

- Blade/JavaScript: wizard, trạng thái tải/lỗi/thành công và accessibility.
- FormRequest: chuẩn hóa input và validation thân thiện.
- Controller: điều phối HTTP, redirect, flash và authorization.
- Service: transaction ngoài, tạo mật khẩu/hash, xử lý file và nghiệp vụ liên procedure.
- Repository: chữ ký procedure, binding tham số, đọc result set và OUT parameter.
- Procedure: kiểm tra invariant quan trọng, khóa/cấp mã, truy vấn và mutation dữ liệu.
- Database constraint: tuyến phòng thủ cuối cho unique và foreign key.

Procedure mutation không được tự `START TRANSACTION`, `COMMIT` hoặc `ROLLBACK`; transaction thuộc về service Laravel để nhiều procedure có thể cùng thành công hoặc cùng rollback.

Mọi `CALL` và truy vấn đọc OUT parameter phải chạy trên cùng connection của transaction. Repository phải tiêu thụ hết result set phụ của MariaDB trước khi gửi câu lệnh kế tiếp để tránh lỗi “commands out of sync”.

## 6. Thay đổi mô hình dữ liệu

### 6.1. Bảng `nhan_vien`

Bổ sung:

- `anh_dai_dien VARCHAR(255) NULL`: chỉ lưu đường dẫn tương đối do ứng dụng sinh;
- `ngay_nghi_viec DATE NULL`;
- unique constraint cho `email`;
- unique constraint cho `cccd`.

Giữ `ma_nv VARCHAR(5)` làm primary key. `mat_khau VARCHAR(255)` chỉ chứa hash Laravel. Email phải được normalize chữ thường ở Laravel và procedure trước khi lưu để unique constraint có hành vi nhất quán.

### 6.2. Bảng `trang_thai_lam_viec`

Bổ sung `ky_hieu VARCHAR(20) NOT NULL UNIQUE`. Tối thiểu cần các mã ổn định:

- `DANG_LAM`;
- `THU_VIEC`;
- `DA_NGHI`.

Logic không được dựa vào tên hiển thị hoặc một `ma_tt` hard-code. Script nâng cấp phải seed/backfill theo dữ liệu đã xác nhận và dừng nếu gặp mapping mơ hồ.

### 6.3. Bảng `dia_chi_nhan_vien`

Tạo quan hệ một-một:

| Cột | Kiểu/constraint |
| --- | --- |
| `ma_nv` | `VARCHAR(5) PRIMARY KEY`, FK tới `nhan_vien(ma_nv)` với `ON DELETE CASCADE` |
| `dia_chi_cu_the` | `NVARCHAR(255) NOT NULL` |
| `phuong_xa` | `NVARCHAR(100) NOT NULL` |
| `quan_huyen` | `NVARCHAR(100) NOT NULL` |
| `tinh_thanh` | `NVARCHAR(100) NOT NULL` |

### 6.4. Bảng `bo_dem_ma_nhan_vien`

Bảng có `ten_bo_dem VARCHAR(30) PRIMARY KEY` và `so_da_cap SMALLINT UNSIGNED NOT NULL`. Chỉ có một dòng canonical với khóa `NHAN_VIEN`. Procedure tạo nhân viên khóa dòng này, tăng một đơn vị, kiểm tra không vượt `999`, rồi ghép `NV` với ba chữ số.

Trên database mới, bộ đếm bắt đầu bằng `0`. Khi nâng cấp database đã có dữ liệu, script preflight mọi mã theo định dạng `NV[0-9]{3}`, khởi tạo `so_da_cap` bằng hậu tố lớn nhất và dừng an toàn nếu có mã ngoài contract hoặc dữ liệu trùng cần xử lý. Script không bao giờ hạ giá trị bộ đếm.

### 6.5. View dùng chung an toàn

Recreate `vw_danh_sach_nhan_vien_chi_tiet` với danh sách cột tường minh:

- `ma_nv`, `ho_ten`, `ngay_sinh`, `gioi_tinh`, `gioi_tinh_hien_thi`;
- `sdt`, `email`, `ngay_vao_lam`;
- `ma_pb`, `ten_pb`, `ma_cv`, `ten_cv`, `he_so_phu_cap`;
- `dan_toc`, `cccd`, `noi_cap_cccd`, `hoc_van`;
- `ma_tt`, `ky_hieu`, `ten_tt`, `ngay_nghi_viec`;
- `ma_vt`, `ten_vt`, `anh_dai_dien`.

View tuyệt đối không có `mat_khau`. Vì view đang được controller nghỉ phép và procedure chấm công/lương dùng chung, migration phải inventory và regression-test toàn bộ caller trước/sau khi recreate; không được thay tên hoặc loại cột an toàn cũ mà chưa chuyển caller.

### 6.6. Script database

- Tạo script nâng cấp có version, chỉ chứa thay đổi thuộc module nhân viên.
- Đồng bộ cùng thay đổi vào đúng block của `quan_ly_nhan_su.session.sql`.
- Preflight và dừng trước DDL nếu email/CCCD hiện có bị rỗng, sai format hoặc trùng sau normalize; không tự sửa dữ liệu mơ hồ. Sau khi preflight sạch, script áp dụng phép chuẩn hóa đã chốt (`LOWER(TRIM(email))`, `TRIM(cccd)`) rồi mới thêm unique constraint.
- Nếu database cũ đã có nhân viên được map sang `DA_NGHI`, migration phải nhận ngày nghỉ đã được xác nhận để backfill hoặc dừng; không dùng ngày chạy migration làm ngày nghỉ giả.
- Không import toàn dump vào database có dữ liệu cần giữ.
- Không thêm procedure, bảng hoặc route giả của module hợp đồng.
- Target được kiểm chứng là MariaDB 10.4.32; chưa tuyên bố tương thích MySQL 8 nếu chưa test riêng.

## 7. Contract stored procedure

Tên dưới đây là contract canonical sau khi thay thế bộ cũ.

### 7.1. `sp_nhan_vien_danh_sach_phan_trang`

Input:

- `p_tu_khoa NVARCHAR(100)`;
- `p_ma_pb INT NULL`;
- `p_ma_cv INT NULL`;
- `p_ma_tt TINYINT NULL`;
- `p_trang INT`;
- `p_so_dong INT`;
- `OUT p_tong_so BIGINT`.

Hành vi:

- tìm theo mã, họ tên, số điện thoại, email, CCCD, phòng ban và chức vụ;
- lọc tùy chọn theo phòng ban, chức vụ, trạng thái;
- thứ tự ổn định `ma_nv ASC`;
- result set cố định gồm `ma_nv VARCHAR(5)`, `ho_ten NVARCHAR(50)`, `sdt VARCHAR(15)`, `email NVARCHAR(100)`, `ngay_vao_lam DATE`, `anh_dai_dien VARCHAR(255) NULL`, `ma_pb INT`, `ten_pb NVARCHAR(100)`, `ma_cv INT`, `ten_cv NVARCHAR(100)`, `ma_tt TINYINT`, `ky_hieu VARCHAR(20)`, `ten_tt NVARCHAR(50)`;
- tuyệt đối không select `mat_khau`;
- `p_trang >= 1`, `p_so_dong` trong khoảng `1..100`.

### 7.2. `sp_nhan_vien_chi_tiet`

Input `p_ma_nv VARCHAR(5)`. Trả đúng một dòng hồ sơ, hoặc result rỗng nếu không tồn tại. Result set cố định:

- định danh: `ma_nv VARCHAR(5)`, `ho_ten NVARCHAR(50)`, `ngay_sinh DATE`, `gioi_tinh TINYINT`;
- liên hệ: `sdt VARCHAR(15)`, `email NVARCHAR(100)`;
- công việc: `ngay_vao_lam DATE`, `ma_pb INT`, `ten_pb NVARCHAR(100)`, `ma_cv INT`, `ten_cv NVARCHAR(100)`;
- hồ sơ: `dan_toc NVARCHAR(50)`, `cccd VARCHAR(12)`, `noi_cap_cccd NVARCHAR(50)`, `hoc_van NVARCHAR(50)`;
- trạng thái: `ma_tt TINYINT`, `ky_hieu VARCHAR(20)`, `ten_tt NVARCHAR(50)`, `ngay_nghi_viec DATE NULL`;
- tài khoản/ảnh: `ma_vt INT`, `ten_vt NVARCHAR(100)`, `anh_dai_dien VARCHAR(255) NULL`;
- địa chỉ: `dia_chi_cu_the NVARCHAR(255) NULL`, `phuong_xa NVARCHAR(100) NULL`, `quan_huyen NVARCHAR(100) NULL`, `tinh_thanh NVARCHAR(100) NULL`.

Không trả `mat_khau`. Các cột địa chỉ nullable ở result để có thể đọc an toàn dữ liệu cũ trước khi backfill; create/update mới vẫn bắt buộc đủ địa chỉ.

### 7.3. `sp_nhan_vien_them`

Input theo thứ tự:

- `p_ho_ten NVARCHAR(50)`;
- `p_ngay_sinh DATE`;
- `p_gioi_tinh TINYINT`;
- `p_sdt VARCHAR(15)`;
- `p_email NVARCHAR(100)`;
- `p_ngay_vao_lam DATE`;
- `p_ma_pb INT`;
- `p_ma_cv INT`;
- `p_dan_toc NVARCHAR(50)`;
- `p_cccd VARCHAR(12)`;
- `p_noi_cap_cccd NVARCHAR(50)`;
- `p_hoc_van NVARCHAR(50)`;
- `p_ma_tt TINYINT`;
- `p_mat_khau_hash VARCHAR(255)`;
- `p_ma_vt INT`;
- `p_anh_dai_dien VARCHAR(255) NULL`;
- `OUT p_ma_nv VARCHAR(5)`.

Procedure không nhận mã nhân viên từ UI.

Hành vi trong transaction ngoài:

1. khóa dòng bộ đếm;
2. cấp mã tiếp theo;
3. chuẩn hóa các trường cần thiết;
4. kiểm tra tuổi, format định danh, foreign key và trạng thái;
5. insert nhân viên với hash đã nhận;
6. trả mã đã cấp.

Procedure không hash plaintext và không nhận plaintext. Procedure từ chối trạng thái `DA_NGHI` khi tạo mới.

### 7.4. `sp_nhan_vien_sua`

Input theo thứ tự:

- `p_ma_nv VARCHAR(5)`;
- `p_ho_ten NVARCHAR(50)`;
- `p_ngay_sinh DATE`;
- `p_gioi_tinh TINYINT`;
- `p_sdt VARCHAR(15)`;
- `p_email NVARCHAR(100)`;
- `p_ngay_vao_lam DATE`;
- `p_ma_pb INT`;
- `p_ma_cv INT`;
- `p_dan_toc NVARCHAR(50)`;
- `p_cccd VARCHAR(12)`;
- `p_noi_cap_cccd NVARCHAR(50)`;
- `p_hoc_van NVARCHAR(50)`;
- `p_ma_tt TINYINT`;
- `p_ma_vt INT`.

Việc sửa hồ sơ phải giữ nguyên hash và avatar hiện tại. Mã nhân viên không được đổi. Procedure cấm chuyển từ trạng thái đang làm sang `DA_NGHI` hoặc từ `DA_NGHI` trở lại trạng thái active; các chuyển đổi đó không được đi vòng qua flow kết thúc làm việc. Một nhân viên đã nghỉ vẫn có thể được sửa thông tin nếu giữ nguyên `DA_NGHI` và ngày nghỉ cũ; nhân viên chưa nghỉ phải luôn có `ngay_nghi_viec IS NULL`.

### 7.5. `sp_dia_chi_nhan_vien_luu`

Nhận `p_ma_nv VARCHAR(5)`, `p_dia_chi_cu_the NVARCHAR(255)`, `p_phuong_xa NVARCHAR(100)`, `p_quan_huyen NVARCHAR(100)` và `p_tinh_thanh NVARCHAR(100)`; thực hiện upsert một-một. Tất cả thành phần bắt buộc sau khi trim.

### 7.6. `sp_nhan_vien_cap_nhat_anh`

Nhận `p_ma_nv VARCHAR(5)`, `p_anh_moi VARCHAR(255) NULL` và trả `OUT p_anh_cu VARCHAR(255)`. Procedure khóa bản ghi cần sửa, trả đường dẫn cũ cho service bù trừ, chỉ cập nhật đường dẫn và không thao tác filesystem.

### 7.7. `sp_nhan_vien_xoa_hoac_nghi_viec`

Nhận `p_ma_nv VARCHAR(5)`, `p_ngay_nghi_viec DATE`; trả `OUT p_hanh_dong VARCHAR(12)` bằng `DELETED` hoặc `TERMINATED` và `OUT p_anh_cu VARCHAR(255)`. Procedure khóa nhân viên, kiểm tra đủ năm bảng phụ thuộc, tìm trạng thái bằng `ky_hieu = 'DA_NGHI'`, và không tự commit. Với `TERMINATED`, cả trạng thái và ngày nghỉ được cập nhật cùng lúc. Nếu nhân viên đã `DA_NGHI`, gọi lại là idempotent và giữ nguyên ngày nghỉ đầu tiên. Đường dẫn cũ chỉ được service xóa sau khi action `DELETED` đã commit.

### 7.8. `sp_nhan_vien_dat_lai_mat_khau`

Nhận `p_ma_nv VARCHAR(5)` và `p_mat_khau_hash VARCHAR(255)`. Hash do Laravel tạo. Procedure không trả hash.

### 7.9. `sp_nhan_vien_lay_tai_khoan_dang_nhap`

Nhận `p_dinh_danh NVARCHAR(100)` đã trim. Tìm chính xác theo `ma_nv` viết hoa hoặc email đã normalize chữ thường. Trả tối đa một dòng cho tầng auth phía server:

- `ma_nv`;
- `ho_ten`;
- `email`;
- `mat_khau`;
- `ma_vt`;
- `ky_hieu` trạng thái.

Không phân biệt thông báo “không tồn tại” và “sai mật khẩu” ở giao diện đăng nhập.

### 7.10. Lỗi procedure

Procedure báo lỗi miền bằng đúng mẫu `SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = '<MA_LOI>'`. SQLSTATE luôn là `45000`; mã ổn định nằm trong `MESSAGE_TEXT` để Laravel ánh xạ sang tiếng Việt an toàn, ví dụ:

- `NV_NOT_FOUND`;
- `NV_EMAIL_DUPLICATE`;
- `NV_CCCD_DUPLICATE`;
- `NV_REFERENCE_INVALID`;
- `NV_CODE_EXHAUSTED`;
- `NV_STATUS_MISSING`;
- `NV_PAGINATION_INVALID`.

Controller/API không trả raw exception, SQL text hoặc stack trace. Race condition duplicate cuối cùng vẫn phải được unique constraint bắt và ánh xạ về đúng lỗi field.

## 8. Tương thích với caller cũ

`NghiPhepController::storeEmployee` và `NghiPhepController::updateEmployee` đang gọi chữ ký 16 tham số cũ. Khi triển khai procedure mới, phải chọn một thay đổi đồng bộ, có test:

- chuyển các endpoint đó sang dùng chung `NhanVienService`; hoặc
- loại bỏ endpoint nhân viên trùng lặp nếu đã xác nhận không còn consumer.

Không giữ hai contract canonical. Nếu cần compatibility wrapper tạm thời, wrapper phải select trường tường minh, không được lộ hash, phải được đánh dấu deprecated và có commit xóa xác định.

Đích thay thế procedure cũ:

| Contract cũ | Contract canonical |
| --- | --- |
| `sp_nhan_vien_danh_sach`, `sp_nhan_vien_tim_kiem` | `sp_nhan_vien_danh_sach_phan_trang` |
| `sp_nhan_vien_chi_tiet` | viết lại tại cùng tên, result shape tường minh |
| `sp_nhan_vien_them`, `sp_nhan_vien_sua` | viết lại tại cùng tên theo chữ ký mới |
| `sp_nhan_vien_xoa` | `sp_nhan_vien_xoa_hoac_nghi_viec` |
| `sp_nhan_vien_dang_nhap` | `sp_nhan_vien_lay_tai_khoan_dang_nhap` |

Trước mỗi `DROP/CREATE`, phải inventory lại caller ở PHP, JavaScript, test và tài liệu tại HEAD hiện hành. Việc bỏ contract cũ và chuyển caller phải nằm trong cùng một delivery có thể kiểm chứng.

Compatibility audit còn phải bao phủ:

- endpoint GET `/api/v1/nghi-phep/nhan-vien`, JavaScript nghỉ phép và các truy vấn trực tiếp tới view dùng chung;
- procedure chấm công/lương đang đọc view;
- mọi FormRequest nhận `ma_nv`, đặc biệt `StoreChamCongRequest`, `UpdateChamCongRequest`, `UpdateNghiPhepRequest` và `UpdateLuongRequest` đang có drift kiểu `integer`.

Các request liên module phải thống nhất `ma_nv` là chuỗi đúng format `NV` cộng ba chữ số, tối đa 5 ký tự, và tồn tại trong `nhan_vien`. Sửa compatibility chỉ nhằm giúp consumer hiện hữu chấp nhận mã canonical; không mở rộng sang viết lại nghiệp vụ nghỉ phép/chấm công/lương.

## 9. Web routes và HTTP contract

Giao diện đầu tiên là server-rendered Blade, dùng session, CSRF, redirect và flash message.

| Method | URI | Route name | Mục đích |
| --- | --- | --- | --- |
| `GET` | `/admin/nhan-vien` | `backend.nhanvien.index` | danh sách, tìm kiếm, lọc, phân trang |
| `GET` | `/admin/nhan-vien/create` | `backend.nhanvien.create` | wizard tạo |
| `POST` | `/admin/nhan-vien` | `backend.nhanvien.store` | tạo nhân viên |
| `GET` | `/admin/nhan-vien/{ma_nv}` | `backend.nhanvien.show` | chi tiết |
| `GET` | `/admin/nhan-vien/{ma_nv}/edit` | `backend.nhanvien.edit` | form sửa |
| `PUT/PATCH` | `/admin/nhan-vien/{ma_nv}` | `backend.nhanvien.update` | cập nhật |
| `DELETE` | `/admin/nhan-vien/{ma_nv}` | `backend.nhanvien.destroy` | xóa hoặc nghỉ việc |
| `PATCH` | `/admin/nhan-vien/{ma_nv}/dat-lai-mat-khau` | `backend.nhanvien.reset-password` | reset mật khẩu |

Filter danh sách nằm trong query string và được giữ khi chuyển trang. Route cũ `/admin/nhan-vien/danh-sach-nhan-vien` chỉ được redirect có chủ đích sang route canonical sau khi kiểm tra link đang dùng.

Các route động dùng constraint `ma_nv` theo `NV[0-9]{3}` và được khai báo sau route tĩnh để `create` không bị hiểu là mã nhân viên.

Các route hiện tại đang map sai `PUT .../{id}` vào action `show`; việc sửa route này thuộc slice route/controller và phải có test hồi quy.

## 10. Wizard tạo nhân viên

### Bước 1 — Thông tin cá nhân

- họ tên;
- ngày sinh;
- giới tính;
- dân tộc;
- học vấn;
- CCCD và nơi cấp;
- email;
- số điện thoại;
- bốn thành phần địa chỉ;
- ảnh đại diện tùy chọn.

### Bước 2 — Công việc và tài khoản

- ngày vào làm;
- phòng ban;
- chức vụ;
- vai trò;
- trạng thái làm việc;
- thông báo “Mã nhân viên được hệ thống cấp khi lưu”;
- hiển thị quy ước mật khẩu mặc định, không có ô nhập mật khẩu.

### Bước 3 — Kiểm tra và xác nhận

Hiển thị lại dữ liệu đã nhập, cho phép quay lại chỉnh sửa, và chỉ gửi một lần. Sau khi thành công, trang kết quả hiển thị mã `NVxxx` vừa cấp.

Form sửa không có trường mật khẩu. Đặt lại mật khẩu là action riêng, có xác nhận riêng.

Trạng thái `DA_NGHI` không xuất hiện như một lựa chọn sửa thông thường: trạng thái này chỉ được đặt qua action xóa/kết thúc làm việc để ngày nghỉ và kiểm tra phụ thuộc luôn nhất quán. Tạo mới cũng không được chọn `DA_NGHI`. Việc kích hoạt lại một nhân viên đã nghỉ nằm ngoài phạm vi đặc tả này.

## 11. Validation

Laravel kiểm tra trước; procedure kiểm tra lại invariant quan trọng.

- đủ 18 tuổi tại `ngay_vao_lam`;
- CCCD đúng 12 chữ số và duy nhất;
- email hợp lệ, tối đa 100 ký tự, normalize chữ thường và duy nhất;
- số điện thoại đúng 10 chữ số, bắt đầu bằng `0`;
- họ tên, dân tộc, nơi cấp CCCD, học vấn và bốn thành phần địa chỉ không rỗng;
- phòng ban, chức vụ, vai trò và trạng thái phải tồn tại;
- ảnh tùy chọn, chỉ JPG/PNG/WebP, tối đa 2 MB;
- `ma_nv` lấy từ route/procedure, không tin input ẩn của client.

Khi validation lỗi, giữ dữ liệu cũ, mở đúng bước có lỗi và focus field lỗi đầu tiên.

## 12. Xử lý ảnh và bù trừ lỗi

- Tên file do ứng dụng sinh ngẫu nhiên; không dùng trực tiếp tên file người dùng.
- Database chỉ lưu đường dẫn tương đối.
- Upload mới được đưa vào vùng tạm trước.
- Service di chuyển file sang vị trí đích, gọi procedure trong transaction và xóa file mới nếu database rollback/thất bại.
- Khi thay avatar, chỉ xóa file cũ sau khi transaction database commit thành công.
- Khi xóa cứng nhân viên, xóa file avatar sau commit; nếu xóa file thất bại, ghi log vận hành nhưng không phục hồi bản ghi đã xóa.
- Không log nội dung file, mật khẩu hoặc hash.

## 13. Trạng thái giao diện và accessibility

Các màn hình phải có đầy đủ:

- loading cho lookup và danh sách;
- empty state khi chưa có nhân viên;
- empty search khi bộ lọc không có kết quả;
- validation error theo field;
- server/domain error an toàn;
- success message;
- disabled/submitting và chống double submit;
- confirm rõ ràng trước delete/reset;
- label, accessible name, focus keyboard và contrast phù hợp;
- responsive ở desktop, tablet và mobile.

Nếu thiếu master data, nút lưu bị khóa và thông báo chỉ rõ thiếu phòng ban, chức vụ, vai trò hay trạng thái.

Trang nhân viên phải tương thích với quyết định shell `Header + Sidebar + Main + Footer`, không global navbar, nhưng slice này không tự thay global layout hoặc lấy code từ nhánh khác.

## 14. Auth và phân quyền

Model `NhanVien` sẽ là Laravel `Authenticatable`, map đúng table, primary key chuỗi, không timestamps và trường password `mat_khau`.

Auth được giao thành một slice riêng nhưng contract thuộc tiêu chí hoàn thành của chức năng:

| Method | URI | Route name | Hành vi |
| --- | --- | --- | --- |
| `GET` | `/dang-nhap` | `login` | hiển thị form mã nhân viên/email và mật khẩu |
| `POST` | `/dang-nhap` | `login.store` | xác thực, regenerate session, redirect intended hoặc dashboard |
| `POST` | `/dang-xuat` | `logout` | logout, invalidate session và regenerate CSRF token |

- Dùng custom `NhanVienUserProvider`, không để Eloquent provider tự query bảng nhân viên.
- `retrieveByCredentials` và `retrieveById` đều gọi repository bọc `sp_nhan_vien_lay_tai_khoan_dang_nhap`; session lưu `ma_nv` và mỗi request nạp lại tài khoản bằng mã này.
- Provider từ chối tài khoản có `ky_hieu = 'DA_NGHI'`, kể cả session được tạo trước khi nhân viên nghỉ.
- `validateCredentials` dùng `Hash::check`; nếu cần rehash do cấu hình cost thay đổi thì Laravel tạo hash mới và lưu qua procedure cập nhật hash, không đưa plaintext xuống database.
- Model map `getAuthPasswordName()`/`getAuthPassword()` tới `mat_khau`.
- Không hỗ trợ “ghi nhớ đăng nhập” trong phạm vi này: form không có remember checkbox, model không có remember-token column, provider không phát/persist remember token.
- Sai mã/email, sai mật khẩu và tài khoản đã nghỉ đều trả cùng thông báo đăng nhập chung; log không chứa credential/hash.
- Toàn bộ route `/admin`, bao gồm module nhân viên, nằm sau middleware `auth`; từng action còn phải qua quyền tương ứng.

Quyền nghiệp vụ:

- `NHAN_VIEN_XEM`;
- `NHAN_VIEN_TAO`;
- `NHAN_VIEN_SUA`;
- `NHAN_VIEN_XOA`;
- `NHAN_VIEN_DAT_LAI_MAT_KHAU`.

Auth/RBAC là một vertical slice riêng trong kế hoạch triển khai. Trước khi slice này hoàn thành, UI/API nhân viên chỉ được mô tả là prototype hoặc verified hẹp, không được gọi là an toàn để public.

## 15. Ranh giới với module hợp đồng

Module nhân viên phải chạy hoàn chỉnh khi controller/service/procedure/UI hợp đồng chưa tồn tại. Policy xóa vẫn phụ thuộc interface database ổn định là bảng `hop_dong` có cột `ma_nv`; mọi thay đổi schema này của đồng nghiệp phải được phối hợp như một contract DB.

- Transaction tạo nhân viên không gọi procedure hợp đồng.
- Không tạo controller, service, repository, procedure hay route hợp đồng giả.
- Hợp đồng liên kết với nhân viên chỉ bằng `ma_nv` sau khi nhân viên đã commit.
- Nếu tạo hợp đồng về sau thất bại, nhân viên vẫn tồn tại; người dùng thử lại flow hợp đồng riêng.
- Trước khi tích hợp, thông báo thành công ghi “Đã tạo nhân viên; có thể bổ sung hợp đồng sau”.
- Khi module thật của đồng nghiệp đã sẵn sàng, một commit tích hợp riêng mới thêm CTA/redirect/handoff sang hợp đồng.
- Không merge nhánh đồng nghiệp trong phạm vi module nhân viên.

Lương cơ bản nằm trong `hop_dong`, vì vậy không được lưu tạm hoặc sao chép sang bảng nhân viên.

## 16. Kiểm thử

### 16.1. MariaDB disposable

Test procedure và constraint trên database MariaDB có thể xóa/tạo lại, không chạy mutation trên dữ liệu cần giữ:

- cấp mã tuần tự và cạnh tranh đồng thời không trùng mã;
- mã đã commit/xóa không được cấp lại;
- rollback có thể dùng lại số chưa commit;
- dừng rõ ràng khi vượt `NV999`;
- email/CCCD trùng, kể cả race condition;
- lookup không tồn tại;
- danh sách, filter, phân trang, tổng số và trang rỗng;
- detail/list không có `mat_khau`;
- view dùng chung không có cột `mat_khau` và các procedure chấm công/lương vẫn chạy với result shape cũ cần thiết;
- sửa hồ sơ không đổi hash;
- upsert địa chỉ;
- hard delete khi không có phụ thuộc;
- chuyển `DA_NGHI` khi có từng loại phụ thuộc và chặn đăng nhập;
- gọi lại action trên nhân viên đã `DA_NGHI` vẫn trả `TERMINATED`, giữ ngày nghỉ đầu tiên và không hard-delete dù phụ thuộc đã bị dọn;
- lookup đăng nhập bằng mã và email normalize;
- reset password nhận hash Laravel;
- transaction rollback và xử lý file bù trừ ở tầng service.

SQLite in-memory của `phpunit.xml` không chứng minh được procedure, lock, trigger, collation hoặc foreign key MariaDB.

### 16.2. Laravel feature/unit

- route name/method/action đúng;
- custom provider đăng nhập và phục hồi user từ session đều đi qua procedure; session cũ bị vô hiệu khi tài khoản chuyển `DA_NGHI`;
- logout/session regeneration và việc không phát remember token;
- FormRequest và authorization theo từng quyền;
- redirect, flash, old input và đúng bước wizard;
- mapping mã lỗi database sang lỗi an toàn;
- file type/size, thay/xóa avatar và cleanup khi lỗi;
- chống double submit;
- employee service hoạt động không cần contract module;
- caller cũ trong `NghiPhepController` không còn dùng chữ ký procedure lỗi thời;
- endpoint/JavaScript nghỉ phép vẫn đọc được nhân viên, và mọi FormRequest liên module chấp nhận `NV001` thay vì ép integer.

### 16.3. Browser acceptance

Kiểm tra thủ công hoặc tự động trên trình duyệt thật:

- tạo, sửa, tìm kiếm/lọc, chi tiết, delete/terminate và reset;
- loading, empty, error, success;
- focus sau validation;
- desktop, tablet, mobile;
- keyboard navigation;
- không có request/response hiển thị hash;
- tạo nhân viên thành công khi chưa có module hợp đồng.

Build pass hoặc response `200` chỉ là bằng chứng hẹp, không thay browser acceptance và MariaDB integration test.

## 17. Tiêu chí chấp nhận

Chức năng quản lý nhân viên chỉ được xem là hoàn thành khi:

1. schema và toàn bộ procedure canonical chạy trên MariaDB disposable;
2. mọi caller procedure cũ đã được migrate hoặc loại bỏ có kiểm chứng;
3. danh sách/tìm kiếm/lọc/phân trang dùng dữ liệu thật và không lộ hash;
4. tạo nhân viên sinh mã đúng, hash đúng, lưu địa chỉ/ảnh và rollback an toàn;
5. sửa không reset mật khẩu;
6. delete/terminate trả và hiển thị đúng hai kết quả;
7. đăng nhập mã/email hoạt động, `DA_NGHI` bị chặn;
8. authorization theo năm quyền được kiểm thử;
9. UI đủ trạng thái và qua browser acceptance;
10. module chạy độc lập với hợp đồng;
11. test, build, lint/diff check liên quan đều pass, hoặc blocker cũ được ghi rõ;
12. tài liệu trạng thái/handoff được cập nhật đúng mức bằng chứng.

## 18. Chiến lược commit và push

- Mỗi commit là một vertical slice nhỏ, hoàn chỉnh và đã test theo phạm vi.
- Stage tường minh, xem diff trước commit; không stage `docs/CODEX_FRONTEND_HANDOFF.md`, secret hoặc thay đổi ngoài task.
- Commit message nêu rõ contract hoặc hành vi, đặc biệt ở ranh giới nhân viên–hợp đồng.
- Sau mỗi commit hoàn chỉnh, push lên `origin/feature/quanly-nhan-vien`.
- Push đầu tiên dùng upstream; các lần sau push bình thường.
- Không merge, rebase, force-push hoặc tự tạo PR.
- Việc tích hợp hợp đồng sau này phải là commit riêng, dễ review/revert và chỉ thực hiện khi module thật đã có.

## 19. Rủi ro và giới hạn đã biết

- Không gian mã chỉ có 999 nhân viên; thay đổi định dạng cần một quyết định/migration riêng.
- Mật khẩu mặc định có thể đoán được và không có cờ đổi mật khẩu; chỉ chấp nhận cho demo nội bộ.
- Master data live hiện trống nên cần seed/fixture được duyệt trước acceptance end-to-end.
- Procedure cũ có caller chéo module; thay chữ ký không đồng bộ sẽ gây regression.
- Database và filesystem không có distributed transaction; phải dựa vào quy trình bù trừ và test lỗi.
- Auth/RBAC hiện chưa có; không được tuyên bố an toàn khi mới hoàn thành CRUD/UI.
- Contract/hợp đồng thuộc nhánh đồng nghiệp và không nằm trong transaction tạo nhân viên.
- Cam kết không tái dùng mã chỉ được bảo đảm từ lúc bảng bộ đếm được triển khai. Mã đã bị xóa trước migration không thể suy ra nếu không có audit log lịch sử.
- Tương thích hiện chỉ được xác nhận mục tiêu trên MariaDB 10.4.32.
