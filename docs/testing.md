> Current [release recheck](release-readiness/recheck-54a0a2f/report.md#12-regression-results): [164 PHP/1062 assertions](release-readiness/recheck-54a0a2f/tests.json), 5 JS, 47 browser, 24 responsive, 13-step E2E, 5 security HTTP PASS. Six new recovery-delta tests cover five reproduced rejection cases and valid customer FK SET NULL. Safari/iOS and physical Android NOT TESTED; mobile viewport emulation is explicit. Original test evidence/counts below are historical. Production gates remain P5-B01/02/03.

> Latest [release review](release-readiness-report.md):158 PHP/1054 assertions,0 errors/failures/skips;5JS,47 browser,24 responsive,13-step E2E and5 security HTTP cases PASS.9 new ledger/view-restore tests plus actual98-table post-window restore/ledger reproduction;see release-readiness/evidence. Earlier149-test checkpoint remains historical,not overwritten.

# Phase 5 — QA and evidence

[Full report](phase-5-report.md), [test cases](phase-5/evidence/tests.json), [functional matrix](phase-5/functional-matrix.csv). Destructive tests chỉ bancay_test. Browser/performance chỉ owned bancay_phase4_ui clone;8000/8002 không là checkout targets. Không chạy đồng thời destructive suites.

Final:149 PHP tests/1033 assertions,0 errors/failures/skips;5 JS PASS. Full suite gồm Phases1–4 và discovery/headers/host/error/mail/workflow/content-image tests mới. Pint,Composer strict validate/platform,Exment requirements,Blade compile/build PASS; audits0.

Installed branded headless Chrome/Edge và Chrome mobile viewport:47 migrated acceptance cases (18+18+11). Continuous admin→public→admin E2E13 bước: category/product/options/variants/image/price/stock→checkout→snapshot→status/history→repricing/cancel. DB xác minh snapshot123000×2=246000,giá catalog mới200000,stock10,reserved0 sau cancel. Security HTTP5 cases cho session/CSRF/avatar fake/IDOR/logout.

Responsive24 cases:1440/768/390/320 ×Home/Category/Product/Cart/Checkout/Article; kiểm overflow,visible controls/labels,alt/image loads,H1,keyboard skip link/menu,console/network. Không full WCAG/assistive-tech certification. Safari/iOS NOT TESTED: không runtime/device. Mobile là emulation, không thiết bị Android thật.

Initial failures được giữ private: PNG fixture không hợp lệ được thay bằng PNG generated hợp lệ; PJAX test đọc DOM quá sớm được sửa chờ snapshot heading; lỗi gallery320px thật được sửa min-width0 và rerun; Medium mime advisory được vá trước final regression. Không tính initial failure thành PASS.

WSL commands:

    python3 scripts/phase5/checks.py
    python3 scripts/phase5/seo.py
    python3 scripts/phase5/security_review.py
    python3 scripts/phase5/clean_install.py
    python3 scripts/phase5/qa_environment.py start

Run browser_acceptance.cjs bằng PlaywrightCore/installed browsers qua BANCAY_PLAYWRIGHT_MODULE; chạy verify_ui.py ngay sau3 migrated orders, trước admin-e2e.cjs thêm order riêng. Run responsive.cjs,admin-e2e.cjs,security-http.cjs; stop clone bằng qa_environment.py stop. Private fixtures dùng QA infrastructure có sẵn; chỉ ghi evidence Phase5, không collectors lịch sử/import/merge replay.

Clean install source-only,không vendor/node_modules/initialized DB:composer,publish,native install/admin,plugins,storage link,npm ci/build,route/config/view cache và HTTP smoke PASS. Owned DB/account đã xóa;17 domain schemas khớp operational khi bỏ AUTO_INCREMENT counters. Fresh install97 base tables+2 views;upgraded main98 base tables+2 views giữ thêm native system_logs table,UUID table names khác theo install. Không drop native table để ép counts bằng nhau;không phải production bootstrap certification.

Raw logs/JUnit/sessions/credentials và screenshots legacy giữ ignored/private. Public evidence chỉ aggregates/IDs/hashes/synthetic fixtures. Manifest ràng buộc tested application source; giữ nguyên Master Plan và historical evidence.
