# Employee SQL sources

## Active contract (2026-08-24)

For a new disposable database, run only these two sources in order:

1. `database/tao_bang.sql`
2. `database/du_lieu_mau.sql`

Together they create exactly 15 base tables, including
`bo_dem_ma_nhan_vien`, and no required view, routine or trigger. The seed has
explicit master/RBAC IDs, 30 Vietnamese employee rows, direct address/avatar/
termination columns, bcrypt hashes and counter `30`. The local/demo login
convention is `NV001` / `nhom3@2026`; it must never be reused for production.

For an existing approved disposable database with the former 16-table shape,
review and run `2026_08_24_001_migrate_to_fifteen_tables.sql` section by section.
It has read-only preflight, fixed role/status and permission-ID checks, address
copy verification, counter repair, RBAC additions and postchecks. MariaDB DDL
implicitly commits: take a backup first and do not pretend the script is one
atomic transaction. It never selects a database and must not be run on the live
`quan_ly_nhan_su` database.

After the migration postcheck, run
`2026_08_24_002_cleanup_legacy_employee_objects.sql` only when the returned
allowlist is reviewed. That separate script drops only the known employee view
and routines; it deliberately leaves procedures from other modules untouched.

Authoritative setup and acceptance notes are in
[docs/EMPLOYEE_MODULE_GUIDE.md](../../../docs/EMPLOYEE_MODULE_GUIDE.md) and
[docs/DATABASE.md](../../../docs/DATABASE.md).

## Historical appendix (not an active setup path)

The following files are retained for audit comparison only and must not be
sourced on the 15-table contract:

- `2026_08_12_001_schema.sql` through `2026_08_12_006_rbac.sql`
- `demo/2026_08_21_001_demo_seed.sql`
- `demo/2026_08_21_002_demo_cleanup.sql`
- `../du_lieu_mau.sql`

They target the superseded address table, role/status symbols and stored
procedures. Their legacy marker is at the beginning of each file. No command
sequence for replaying them is provided here, so historical text cannot be
mistaken for the current rollout contract. The old files may mention their
former routine contracts; active Laravel code uses direct Query Builder and
permission IDs instead.

The canonical `quan_ly_nhan_su.session.sql` dump is also for a fresh,
disposable database only. It begins with `DROP DATABASE IF EXISTS`; never use
it to update a database whose data must be retained.
