<!-- @loom-file release=0.12.08 revision=2 policy=package-priority -->
# LOOM SQL Persistence Standard — v0.11.0

## Before a database is connected
LOOM remains immediately usable. It stores server-side state in protected local files and browser identity in local storage. This is considered **temporary/local mode**.

## After MySQL/MariaDB is connected
LOOM Admin can:
1. save and test the connection,
2. initialize LOOM tables,
3. migrate temporary data.

Once the schema exists, new telemetry/presence and supported identity/settings data are also written to SQL. Local files remain as a cache/safety copy.

## Hostinger
Create a MySQL database and database user in hPanel first. LOOM does not attempt to create a database account. Enter the Hostinger-provided host, port (normally 3306), database name, username, and password in LOOM Admin → Database.

Connection credentials are stored in `data/admin/database.json`, which is protected by `.htaccess`. The password is never returned by the status API.

## v0.11.10 additions
SQL persistence now also covers `loom_identity_ips`, `loom_project_user_state`, and `loom_admin_audit`. Connected installations may safely rerun schema initialization because all additions use `CREATE TABLE IF NOT EXISTS`.
