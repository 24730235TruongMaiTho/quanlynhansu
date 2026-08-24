# MariaDB integration test inventory

`FreshEmployeeSchemaContractTest.php` is the active guarded gate for the
15-table employee/auth/RBAC source. It also derives a disposable 16-table
legacy fixture via `tests/Fixtures/MariaDb/employee_legacy_fifteen_plus_address.sql`
to verify migration/copy/cleanup and starts two direct repository workers for
the counter race. `phpunit.mariadb.xml` intentionally lists that file only.

The remaining `*ProcedureTest.php`, `CanonicalDumpReplayTest.php`, legacy
fixture tests and native procedure workers preserve Task 12–20 evidence for the
superseded routine/address-table schema. They are not active acceptance tests,
must not be run against the fresh pair or a live database, and are retained only
as explicitly marked historical material until separately replaced.
