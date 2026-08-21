# Tài liệu dự án

Thư mục này tách tài liệu theo mục đích để thành viên và AI agent không phải suy đoán từ một README dài.

## Nguồn sự thật

Khi thông tin mâu thuẫn, dùng thứ tự ưu tiên:

1. Code, route và cấu hình tại HEAD hiện tại.
2. Database/schema đang được kiểm tra và `quan_ly_nhan_su.session.sql`.
3. Test/build/kiểm tra live của phiên hiện tại.
4. [PROJECT_STATUS.md](PROJECT_STATUS.md) và [CODEX_NEXT_HANDOFF.md](CODEX_NEXT_HANDOFF.md).
5. Các tài liệu thiết kế, prototype và snapshot cũ.

Mọi số liệu route, test, build và Git đều là snapshot; phải đo lại khi HEAD đổi.

Snapshot mới nhất của Tasks 13–20 nằm ở đầu [PROJECT_STATUS.md](PROJECT_STATUS.md)
và [CODEX_NEXT_HANDOFF.md](CODEX_NEXT_HANDOFF.md). Browser employee đã có
responsive evidence hẹp; avatar file upload vẫn blocked/unverified. Source và
dependency locks cùng tài liệu evidence đã push trên feature branch. Không dùng các đoạn lịch sử bên dưới để suy rộng thành
production readiness.

## Bản đồ tài liệu

| Tài liệu | Dùng khi nào |
| --- | --- |
| [README gốc](../README.md) | Onboarding nhanh và tổng quan dự án |
| [PROJECT_STATUS.md](PROJECT_STATUS.md) | Kiểm tra module nào wired, prototype, blocked hoặc planned |
| [ARCHITECTURE.md](ARCHITECTURE.md) | Hiểu luồng request, lớp code, route và asset |
| [DATABASE.md](DATABASE.md) | Setup MariaDB/MySQL, schema, routine và rủi ro dữ liệu |
| [DEVELOPMENT_GUIDE.md](DEVELOPMENT_GUIDE.md) | Bắt đầu task, quy ước code, Git và kiểm tra |
| [FRONTEND_GUIDE.md](FRONTEND_GUIDE.md) | Làm Blade/UI, tích hợp shell và accessibility |
| [ROADMAP.md](ROADMAP.md) | Chọn task tiếp theo theo dependency |
| [CODEX_NEXT_HANDOFF.md](CODEX_NEXT_HANDOFF.md) | Tiếp tục một phiên Codex/AI từ snapshot gần nhất |
| [ADR-001](decisions/ADR-001-admin-shell.md) | Quyết định shell quản trị đã duyệt nhưng chưa tích hợp vào `main` |

## Tài liệu tham khảo, không phải runtime

- [noi_dung_3/README.md](noi_dung_3/README.md) mô tả ba prototype HTML tĩnh. Không coi các file này là màn hình Laravel đang chạy.
- `docs/CODEX_FRONTEND_HANDOFF.md` là file local-only của branch `frontend` trên máy hiện tại. File bị loại khỏi Git và không phải nguồn sự thật cho branch `main`.

## Quy tắc cập nhật

- Cập nhật `PROJECT_STATUS.md` khi trạng thái module hoặc baseline kiểm tra đổi.
- Cập nhật `DATABASE.md` khi schema/routine/config setup đổi.
- Tạo ADR mới khi thay đổi quyết định kiến trúc; không xóa ADR cũ.
- Cập nhật `CODEX_NEXT_HANDOFF.md` sau một mốc lớn, nhưng luôn giữ tài liệu này ngắn và có HEAD/snapshot.
- Không ghi secret, dữ liệu cá nhân hoặc nội dung `.env` thật vào tài liệu.
