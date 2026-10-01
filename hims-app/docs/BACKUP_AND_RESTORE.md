# HIMS Backup, Disaster Recovery & Key Management Manual

This guide documents the enterprise backup, restoration, disaster recovery (DR) procedures, and **APP_KEY recovery requirements** for the Hospital Information Management System (HIMS v2.0).

---

## 1. Overview of HIMS Backup Architecture

A full HIMS backup consists of three critical components:

1. **Database Schema & Data**: All 52 relational tables including employee directories, competency matrix, CPD credits, training sessions, performance evaluations, succession candidates, and audit trail logs.
2. **Uploaded Assets**: Hospital credentials documents, employee avatars, certificates, and compliance attachments stored under `storage/app/public/`.
3. **Environment Security Secrets (`APP_KEY`)**: The 32-byte cryptographic key used by Laravel's `Illuminate\Support\Facades\Crypt` (AES-256-CBC) to encrypt sensitive Personal Identifiable Information (PII) like employee telephone numbers, encrypted tokens, and secure session identifiers.

---

## 2. Automated Backups via Artisan

HIMS provides dedicated, zero-downtime Artisan console commands:

### Create a Complete Backup (Database + Uploaded Assets)
```bash
php artisan hims:backup
```
- Creates a timestamped archive at `storage/app/backups/hims_backup_YYYY-MM-DD_HHmmss.zip`.
- Includes a verifiable `manifest.json` detailing table counts, timestamp, and the SHA256 fingerprint of the active `APP_KEY`.
- Logs the backup event to the immutable HIMS `audit_trails` ledger.

### Create a Database-Only Backup
```bash
php artisan hims:backup --only-db
```

### Specify a Custom Backup Filename
```bash
php artisan hims:backup --filename=daily_scheduled_backup
```

---

## 3. Restoring from a Backup

To restore the system from a previously generated backup archive:

```bash
# Interactive restore (with confirmation safety checks)
php artisan hims:restore hims_backup_YYYY-MM-DD_HHmmss.zip

# Non-interactive / CI / Disaster Recovery restore
php artisan hims:restore /path/to/archive.zip --force
```

During restore, the command:
1. Validates the integrity of the zip archive and checks `manifest.json`.
2. Computes the SHA256 hash of the active `APP_KEY` in `.env` and compares it to the backup's fingerprint.
3. If an `APP_KEY` mismatch is detected, **halts and issues a prominent warning** to prevent accidental unrecoverable data corruption.
4. Executes the database restoration with foreign key constraints temporarily suspended.
5. Reconstructs `storage/app/public/` directory with file assets.
6. Automatically executes `php artisan optimize:clear` to purge cached views, routes, and configs.

---

## 4. CRITICAL: APP_KEY Recovery & Encryption Key Management

### Why APP_KEY is Mission-Critical
In HIMS, Republic Act 10173 (Data Privacy Act of 2012) and Joint Commission International (JCI) compliance require encrypting sensitive healthcare personnel records at rest. 

Laravel uses the `APP_KEY` from your `.env` file as the encryption cipher key. 

> [!CAUTION]
> **If the `APP_KEY` is lost or changed, any encrypted data in the database (such as employee telephone numbers and tokens) becomes PERMANENTLY UNRECOVERABLE.**
> Attempting to decrypt data with a different `APP_KEY` throws an unrecoverable `Illuminate\Contracts\Encryption\DecryptException`.

### Key Preservation Procedures
1. **Never commit `.env` to Git repositories.** Ensure `.env` is listed in `.gitignore`.
2. **Offline Key Vaulting**:
   - Store the active `APP_KEY` in an enterprise password vault (e.g. AWS Secrets Manager, Azure Key Vault, HashiCorp Vault, or 1Password).
   - Maintain a physical, sealed emergency envelope containing the production `APP_KEY` in the hospital IT director's safe.
3. **Fingerprint Verification**:
   To inspect the fingerprint of your active key without exposing the plaintext key:
   ```bash
   php -r "echo hash('sha256', env('APP_KEY'));"
   ```

### Recovering a Lost APP_KEY During Disaster Recovery
If restoring HIMS onto a completely fresh server or container:
1. Deploy the codebase.
2. **DO NOT** run `php artisan key:generate` on a restored database. That will generate a new random key and invalidate existing encrypted records.
3. Instead, retrieve the archived `APP_KEY` from your secure secrets vault.
4. Set the exact `APP_KEY` in your `.env`:
   ```dotenv
   APP_KEY=base64:YOUR_ORIGINAL_PRESERVED_KEY_HERE=
   ```
5. Run `php artisan hims:restore <backup_file>` to confirm the fingerprint matches 100%.

---

## 5. Automated Scheduling via Cron

To run daily automated backups at 02:00 AM, add the standard Laravel scheduler to your server crontab:

```bash
* * * * * cd /path-to-hims/hims-app && php artisan schedule:run >> /dev/null 2>&1
```

In `routes/console.php`:
```php
use Illuminate\Support\Facades\Schedule;

Schedule::command('hims:backup')->dailyAt('02:00');
Schedule::command('hims:send-supervisor-digest')->weeklyOn(1, '08:00');
```

---

## 6. Disaster Recovery Runbook (Step-by-Step)

In the event of total server loss:

1. **Provision New Host**: Install PHP 8.2+, MySQL 8.0+, Composer, and required extensions (`pdo_mysql`, `zip`, `openssl`).
2. **Clone Repository**:
   ```bash
   git clone git@github.com:shikabane277/HIMS-v2.git
   cd HIMS-v2/hims-app
   composer install --no-dev --optimize-autoloader
   ```
3. **Configure `.env`**:
   ```bash
   cp .env.example .env
   ```
   Paste the verified original `APP_KEY` and database credentials.
4. **Link Storage**:
   ```bash
   php artisan storage:link
   ```
5. **Restore Latest Backup**:
   ```bash
   php artisan hims:restore /backups/hims_backup_latest.zip --force
   ```
6. **Break-Glass Emergency Verification**:
   If administrative accounts are inaccessible:
   ```bash
   php artisan hims:break-glass admin@hospital.ph
   ```
7. **Verify System Health**:
   ```bash
   php artisan test
   ```
