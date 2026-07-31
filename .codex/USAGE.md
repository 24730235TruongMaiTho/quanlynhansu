# Codex assets cho đồ án `quanlynhansu`

Thư mục này lưu ngữ cảnh và workflow project-local để Codex cùng các thành viên làm việc nhất quán trên GitHub.

## Cấu trúc

```text
.codex/
├── agents/          # Vai trò tập trung cho từng loại phiên làm việc
├── instructions/    # Quy tắc ngắn theo backend, UI, database và Git
├── prompts/         # Prompt mẫu cho công việc lặp lại
├── skills/          # Skill riêng của đồ án
└── USAGE.md
```

Skill dự án có cấu trúc giống mẫu trong ảnh tham chiếu:

```text
.codex/skills/quanlynhansu-project-standard/
├── agents/
│   └── openai.yaml
├── references/
│   └── project-checklist.md
└── SKILL.md
```

## Nguồn sự thật

Đọc theo thứ tự:

1. `../AGENTS.md` — quy tắc bắt buộc.
2. `../docs/CODEX_NEXT_HANDOFF.md` — snapshot và điểm tiếp tục gần nhất.
3. Code và SQL liên quan trực tiếp tới task.
4. `instructions/` và skill dự án.
5. `../README.md` — mục tiêu, setup và workflow nhóm.

Nếu tài liệu mâu thuẫn với code, ưu tiên code hiện tại và cập nhật tài liệu trong cùng task khi phù hợp.

## Cách dùng

- Dùng `prompts/continue-from-handoff.md` khi bắt đầu phiên mới.
- Dùng `prompts/implement-module.md` khi xây một module CRUD.
- Dùng `prompts/review-before-merge.md` trước pull request.
- Dùng file trong `agents/` khi cần một vai trò tập trung.
- Tham chiếu `.codex/skills/quanlynhansu-project-standard/SKILL.md` trực tiếp, hoặc copy skill vào `~/.codex/skills/` nếu muốn Codex tự phát hiện trong máy cá nhân.

`.agents/skills/` ở root vẫn là thư viện workflow kỹ thuật dùng chung. `.codex/` chỉ bổ sung ngữ cảnh riêng của đồ án, không thay thế thư viện đó.

## Quy trình nhóm đề xuất

1. Giữ `AGENTS.md`, `.codex/`, README và handoff trong Git.
2. Mỗi task dùng branch ngắn hạn và pull request.
3. Đọc prompt phù hợp, sửa theo một lát cắt nhỏ rồi chạy test/build.
4. Cập nhật handoff khi trạng thái hoặc ưu tiên dự án thay đổi đáng kể.
5. Không lưu secret hoặc dữ liệu cá nhân trong prompt, log hay tài liệu Codex.
