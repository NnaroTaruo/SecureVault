# Upgrade legacy tables to InnoDB

Use [2026-10-28-transactional-vault-tables.sql](2026-10-28-transactional-vault-tables.sql) for an existing installation whose `users`, `categories`, `accounts`, or `sessions` tables use MyISAM. Fresh installations already use InnoDB. Master-key changes stay blocked until `users`, `accounts`, and `sessions` all support transactions.

The migration changes only the storage engine. It preserves columns, indexes, character sets, authentication hashes, master-key salts, and encrypted passwords. It does not add foreign keys or convert the legacy character set. The separate login-counter migration is still required.

## Prepare and test

1. In phpMyAdmin, select the application database. Export **all tables**, with **structure and data**, as SQL. Keep the download outside the repository and web root; do not share it publicly.
2. Inspect the actual table definitions with `SHOW CREATE TABLE`. Check available storage and InnoDB support. The conversion rebuilds tables and needs additional disk space.
3. Restore the backup into a separate private test database, then test this migration there. Use the same database-server version when possible. Verify that the restore works and all data rows survive the conversion. Keep any application connected to this copy isolated from the live site.

The regression test also exercises a synthetic legacy schema with `latin1` columns and a `LONGBLOB` password field. It checks every stored field byte for byte, unchanged column/index definitions, repeated imports, decryption, failed-rotation rollback, and a successful master-key change. Run `node tests/master-password-change.cjs`.

## Apply in phpMyAdmin

1. Set `MAINTENANCE_MODE` to `true` in the **live private** `config.php`. Confirm the sign-in page and API reject online requests with HTTP 503, let existing requests finish, and pause any other database writers.
2. Take a **fresh structure-and-data backup while writes are paused**. Record exact row counts and a private comparison of the data rows before changing anything. A single-transaction export alone cannot make a consistent MyISAM backup.
3. Select the correct application database in phpMyAdmin, choose **Import**, and import only `2026-10-28-transactional-vault-tables.sql`. Do not import `database.example.sql` into an existing database. Stop if any statement fails or reports a warning; keep maintenance enabled and inspect the result.
4. Check the engines:

   ```sql
   SELECT TABLE_NAME, ENGINE
   FROM information_schema.TABLES
   WHERE TABLE_SCHEMA = DATABASE()
     AND TABLE_NAME IN ('users', 'categories', 'accounts', 'sessions', 'login_throttles');
   ```

   All five tables should report `InnoDB`. Compare exact row counts and data rows with the fresh backup, including authentication hashes, salts, and ciphertext bytes. Compare the data, rather than the entire dump file: its table-engine declarations will change. Preserve existing column types, collations, and indexes.
5. Once those checks pass, set `MAINTENANCE_MODE` back to `false`. Use a disposable account with sample entries to test sign-in, reveal, add/edit/delete, changing its master key, and signing in with the new key. Sync once online and check offline reveal. Keep the original backup private.

## Interrupted conversion or restore

`ALTER TABLE` commits implicitly. These four statements are **not** a single transaction, and SQL `ROLLBACK` cannot undo them. If interrupted, inspect each table's engine and compare its data before continuing. A repeated import preserves data but rebuilds tables that are already InnoDB, so prefer converting only the remaining MyISAM tables after resolving the error.

If data differs or an integrity check fails, leave maintenance enabled. Restore the verified backup into an empty recovery database, validate it, and switch the application's private database configuration only after that recovery copy is ready. Do not import a backup on top of partially migrated live tables. Keep old and new database copies until recovery is verified; converting engines back is not a substitute for restoring data.

References: [MariaDB engine conversion](https://mariadb.com/docs/server/server-usage/storage-engines/converting-tables-from-myisam-to-innodb), [implicit commits](https://mariadb.com/docs/server/reference/sql-statements/transactions/sql-statements-that-cause-an-implicit-commit).
