<!-- @loom-file release=0.12.08 revision=2 policy=package-priority -->
# Hostinger Database Setup for LOOM

1. Open **Hostinger hPanel → Databases → MySQL Databases**.
2. Create a database for LOOM.
3. Create/assign a database user with full privileges to that database.
4. Copy these four values from Hostinger:
   - database host,
   - database name,
   - database username,
   - database password.
5. Open **LOOM Admin → Database**.
6. Paste those values. Leave port at **3306** unless Hostinger gives you another port.
7. Click **Save & Test Connection**.
8. When the status shows Connected, click **Initialize LOOM Tables**.
9. Click **Migrate Temporary Data**.

After the final migration step, LOOM reports **Storage: SQL permanent**. Existing temporary profile/account/module-setting/telemetry/presence data is copied to the database. Local files are intentionally kept as a safety/cache copy.

## Important
- LOOM never displays the saved database password after it is stored.
- The DB credential file is under `data/admin/` and is denied by the packaged Apache `.htaccess`.
- The database itself must be created in Hostinger first; LOOM creates its tables, not the database account.
