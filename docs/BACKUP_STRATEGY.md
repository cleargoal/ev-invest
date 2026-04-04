# Database Backup Strategy

## Overview
This application uses **event-driven backups** triggered automatically after critical financial operations. Since operations are rare but high-value, this approach minimizes storage while ensuring data protection during critical moments.

## Automatic Backup Triggers

Backups are automatically created after:

### 1. Vehicle Purchase (`VehicleService::buyVehicle()`)
- Triggered after successful vehicle purchase
- Includes vehicle record creation
- Location: `app/Services/VehicleService.php:37`

### 2. Vehicle Sale (`VehicleService::sellVehicle()`)
- Triggered after successful vehicle sale transaction
- Includes profit distribution to investors (using existing percentages)
- No percentage recalculation (operates with amounts only)
- Location: `app/Services/VehicleService.php:86`

### 3. Vehicle Unselling (`VehicleService::unsellVehicle()`)
- Triggered after successful vehicle unsell operation
- Includes contribution reversals (using existing percentages)
- No percentage recalculation (operates with amounts only, like selling)
- Location: `app/Services/VehicleService.php:173`

### 4. Payment Confirmation (`PaymentService::paymentConfirmation()`)
- Triggered after operator confirms investor payment
- Includes contribution creation AND percentage recalculation for ALL investors
- Location: `app/Services/PaymentService.php:99`


## Backup Rotation

The backup system automatically manages storage:
- **Default retention**: 4 most recent backups
- **Automatic cleanup**: Old backups deleted automatically
- **Customizable**: Use `--keep` option to adjust retention

## Manual Backup

Run on-demand backup before major operations:
```bash
php artisan db:backup
```

Keep more backups:
```bash
php artisan db:backup --keep=20
```

## Backup Location

All backups stored at: `storage/app/backups/`

Filename format: `db_backup-YYYY-MM-DD_HH-ii-ss.sql`

## Technical Implementation

Backups run asynchronously using **Laravel Jobs** (`BackupDatabaseJob`):
- **Job Class**: `app/Jobs/BackupDatabaseJob.php`
- **Dispatch Method**: `dispatch(new BackupDatabaseJob())->afterResponse()`
- **Execution**: Runs after HTTP response is sent to user (non-blocking)
- **Queue Connection**: Uses `sync` driver (no queue worker needed)
- **Retry Logic**: 3 attempts with 60-second backoff
- **Logging**: Success/failure logged to `storage/logs/laravel.log`
- **No exec() dependency**: Works even if shell functions are disabled in php.ini

## Why Event-Driven?

✅ **Advantages:**
- Backups only when data actually changes
- No wasted backups during idle periods
- Captures critical operations immediately
- Minimal storage usage for low-activity app

❌ **Scheduled backups not used because:**
- Rare operations make scheduled backups mostly empty
- Wastes storage with duplicate data
- May miss critical operations between schedule intervals

## Recovery

To restore from backup:
```bash
mysql -u username -p database_name < storage/app/backups/db_backup-YYYY-MM-DD_HH-ii-ss.sql
```

## Production Deployment

After deploying to production, ensure proper setup:
1. Fix storage permissions: `sudo chown -R www-data:www-data storage/ && sudo chmod -R 775 storage/`
2. Test backup manually: `php artisan db:backup`
3. Verify backup file created: `ls -lth storage/app/backups/ | head -3`
4. Check backup log if issues: `tail -50 storage/logs/laravel.log | grep -i backup`

## Monitoring

Check backup count:
```bash
ls -lt storage/app/backups/ | head -15
```

Check latest backup:
```bash
ls -t storage/app/backups/ | head -1
```
