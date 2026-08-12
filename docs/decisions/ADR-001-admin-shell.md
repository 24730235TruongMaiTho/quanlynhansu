# ADR-001: Shell quản trị canonical

## Status

Accepted về thiết kế; chưa tích hợp vào branch `main`.

## Date

2026-08-05; rà soát lại theo `main` ngày 2026-08-11.

## Context

Đồ án có nhiều họ giao diện:

- Landing/shared assets.
- Backend Bootstrap/CDN trên `main`.
- Bộ prototype Primer.
- Shell quản trị được phát triển riêng trên local branch `frontend`.

Nếu mỗi page tự chứa header/sidebar/footer hoặc tiếp tục thêm asset tùy ý, navigation, responsive, accessibility và Vite contract sẽ phân kỳ nhanh.

Branch `main` và `frontend` hiện đã phân kỳ từ merge-base `063c669`. `main` có business API mới; `frontend` có shell/test UI riêng. Wholesale merge có nguy cơ ghi đè hoặc làm mất cả hai phía.

## Decision

Shell quản trị mục tiêu có cấu trúc:

```text
Header
Sidebar
Main
Footer
```

Không dùng global navbar. Navigation chính thuộc Sidebar; breadcrumb, title và actions thuộc page header trong Main.

Hành vi:

- Desktop sidebar mở rộng và có thể thu thành icon rail.
- Group cùng cấp dùng accordion.
- Group chứa route active tự mở.
- Rail thu gọn dùng flyout/tooltip không bị clip.
- Mobile dùng drawer độc lập.
- Có fallback navigation khi JavaScript không khởi tạo.
- Landing và admin dùng asset runtime tách biệt.

Page con chỉ cung cấp title, page header, page width và content; không lặp document wrapper hoặc shell.

## Implementation state

Implementation đã pass automated render/controller/build tests riêng trên local branch `frontend`, HEAD `940e7cc0be2c3fac03588d1d6455e865d30bc2ed`. Browser acceptance vẫn chưa hoàn tất; không có claim mới về viewport, clipping, focus, console hoặc network.

Branch `main` hiện vẫn dùng `backend.layouts.app` với Sidebar + Topbar + content. Vì vậy:

- Không mô tả ADR này như code đã chạy trên `main`.
- Không tự merge/rebase/cherry-pick.
- Khi được yêu cầu tích hợp, tạo branch từ `main`, port có chọn lọc và giữ business API mới.

## Alternatives considered

### Giữ nguyên shell main và mở rộng

- Ưu: ít thay đổi ngay lập tức.
- Nhược: navigation hard-code, asset/CDN/Vite chồng nhau, thiếu Footer và thiếu test shell.

Không chọn làm kiến trúc dài hạn, nhưng vẫn là runtime hiện tại cho tới khi tích hợp.

### Global navbar

- Ưu: dễ triển khai cho ít module.
- Nhược: không phù hợp số lượng module HR và trùng vai trò với Sidebar.

Không chọn.

### Merge toàn bộ branch frontend

- Ưu: nhanh nếu branch không phân kỳ.
- Nhược: hai branch hiện có 17/20 commit riêng; layout, route, assets và business code đã khác nhau.

Không chọn.

## Consequences

- Cần một task tích hợp riêng có plan và test.
- Page trên `main` phải được chuyển dần sang contract mới.
- Route name và navigation config phải được chuẩn hóa trước khi thêm menu.
- Cần giữ automated shell/asset-isolation tests và bổ sung browser matrix.
- Cho tới khi tích hợp xong, tài liệu phải phân biệt “runtime main” và “target shell”.

## Verification required for integration

- Laravel route/render tests.
- Navigation active/fallback tests.
- Frontend controller/state tests.
- Vite manifest và asset isolation.
- Desktop/tablet/mobile browser acceptance.
- Keyboard/focus/accessibility check.
- Full Git diff để bảo đảm không mất API/business changes trên `main`.
