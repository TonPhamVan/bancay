# bancay.vn — Legacy analysis and migration plan

Phase 0 đã hoàn tất phân tích và kiểm chứng local. **Phase 1: READY WITH RISKS.** Backup cấu hình hosting được hoãn theo chỉ định; chưa xác nhận full production recovery.

- [Master Plan nguyên bản](docs/master-plan.md)
- [Báo cáo Phase 0](docs/phase-0.md)
- [Yêu cầu Phase 0](docs/phase-0/request.md)
- [Kiến trúc legacy](docs/phase-0/architecture.md)
- [Database inventory](docs/phase-0/database.md)
- [Backup và giới hạn kiểm chứng](docs/phase-0/backup.md)
- [Readiness](docs/phase-0/readiness.md)

Công cụ audit offline nằm trong `scripts/phase0/`. Source legacy, raw SQL, dữ liệu khách hàng và backup gốc được giữ riêng ngoài Git. Production source/database, hosting và DNS chưa bị thay đổi.
