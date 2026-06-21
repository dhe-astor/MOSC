# Database Backup and Restore Guide

This guide explains how to back up and restore the database and media assets for the MSOC Europe Portal application.

---

## 1. Local / SQLite Environment

When running locally with SQLite, the database is stored in a single file: `laravel/database/database.sqlite`.

### Backup
Simply copy the SQLite file to your backup location:
```bash
cp laravel/database/database.sqlite /path/to/backups/database_backup_$(date +%F).sqlite
```

### Restore
1. Stop the application server.
2. Replace the active sqlite file with your backup file:
```bash
cp /path/to/backups/database_backup_YYYY-MM-DD.sqlite laravel/database/database.sqlite
```
3. Restart the application server.

---

## 2. Production / PostgreSQL Environment

In production, the application is configured to connect to a PostgreSQL database.

### Backup
Use the `pg_dump` utility to export the database to a compressed backup file:
```bash
pg_dump -h <db_host> -U <db_user> -d msoc_prod -F c -b -v -f /path/to/backups/msoc_prod_$(date +%F).backup
```

### Restore
Use the `pg_restore` utility to restore the database:
1. Terminate active database connections.
2. Drop and recreate the database schema (optional, if you want a clean slate):
```bash
dropdb -h <db_host> -U <db_user> msoc_prod
createdb -h <db_host> -U <db_user> msoc_prod
```
3. Run the restore command:
```bash
pg_restore -h <db_host> -U <db_user> -d msoc_prod -v /path/to/backups/msoc_prod_YYYY-MM-DD.backup
```

---

## 3. Media Assets and Storage Backup

Uploaded media files are stored in `laravel/storage/app/public/` (locally) or the configured filesystem disk in production.

### Backup
Compress the public storage folder:
```bash
tar -czf /path/to/backups/storage_assets_$(date +%F).tar.gz laravel/storage/app/public/
```

### Restore
Extract the backup archive to the storage folder:
```bash
tar -xzf /path/to/backups/storage_assets_YYYY-MM-DD.tar.gz -C laravel/storage/app/
```
