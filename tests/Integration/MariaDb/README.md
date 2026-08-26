# MariaDB integration test inventory

`FreshEmployeeSchemaContractTest.php` is the active guarded gate for the
15-table employee/auth/RBAC source. Test replay ba file SQL active, kiểm tra thủ
tục RBAC và khởi chạy hai worker repository disposable cho race bộ đếm.
`phpunit.mariadb.xml` chỉ liệt kê file này.

The remaining `*ProcedureTest.php`, `CanonicalDumpReplayTest.php`, legacy
fixture tests and native procedure workers preserve Task 12–20 evidence for the
superseded routine/address-table schema. They are not active acceptance tests,
must not be run against the fresh pair or a live database, and are retained only
as explicitly marked historical material until separately replaced.
