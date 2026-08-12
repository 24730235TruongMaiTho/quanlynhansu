# Triển khai một module

Module cần làm: `<ten-module>`

Kết quả mong muốn: `<mo-ta-ngan>`

Hãy dùng `$quanlynhansu-project-standard`, sau đó:

1. Đọc route, request, controller, service/repository, model, Blade/JavaScript, test và procedure của module.
2. Nêu các điểm chưa khớp giữa code và database.
3. Triển khai một lát cắt hoàn chỉnh: route → validation → controller → data contract → Blade/JavaScript → test.
4. Giữ phạm vi nhỏ, không sửa module khác nếu không phải dependency bắt buộc.
5. Chạy `php -l`, `php artisan test`, `npm run build` và kiểm tra Git tùy theo file đã sửa.
6. Báo cáo file thay đổi, kết quả kiểm tra và việc còn lại.
