# Role System Migration: Spatie Permission → Simple Custom Roles

## Overview

This guide documents the migration from Spatie Laravel Permission package to a simple custom role system. The migration was necessary due to database deadlocks during testing and because the application only uses simple role-based authorization without complex permissions.

### What Changed

**Before:**
- Used Spatie Laravel Permission package
- 5 database tables: `roles`, `permissions`, `model_has_roles`, `model_has_permissions`, `role_has_permissions`
- Roles assigned via pivot table relationships
- Authorization checks via `hasRole()` from Spatie trait

**After:**
- Simple `role` column in `users` table
- Custom `hasRole()` and `assignRole()` methods in User model
- Direct database queries instead of relationship joins
- 5 fewer database tables
- Better performance and no deadlocks

### Roles Supported

- `admin` - Full administrative access
- `operator` - Operations management
- `investor` - Investment tracking and reporting
- `company` - Company commission tracking
- `viewer` - Read-only access (if needed)
- `super-admin` - Super administrative access (if needed)

## Prerequisites

Before running the migration on production:

- [ ] **Database Backup**: Ensure you have a recent database backup
- [ ] **Code Deployment**: All code changes must be deployed to production first
- [ ] **Review Users**: Check current user roles in database
- [ ] **Maintenance Window**: Plan for brief downtime (2-5 minutes)
- [ ] **Test Environment**: Test the migration in staging environment first
- [ ] **Laravel Version**: Confirm Laravel 11+ is installed
- [ ] **Database Access**: Ensure MySQL credentials are configured correctly

## Migration Files

Two migration files will be executed:

1. **`2026_01_19_110157_add_role_to_users_table.php`**
   - Adds `role` column to users table (default: 'investor')
   - Migrates existing role data from `model_has_roles` pivot table
   - Creates index on role column for performance

2. **`2026_01_19_110924_drop_permission_tables.php`**
   - Drops 5 Spatie tables: `roles`, `permissions`, `model_has_roles`, `model_has_permissions`, `role_has_permissions`
   - This is irreversible without database backup

## Deployment Options

### Option 1: Automated Script (Recommended)

Use the provided deployment script for safe, automated migration:

```bash
# Make script executable
chmod +x deploy-role-migration.sh

# Run deployment script
./deploy-role-migration.sh

# Or with custom backup retention
./deploy-role-migration.sh --keep=20
```

The script will:
- Display overview and ask for confirmation
- Check prerequisites (database connection, artisan)
- Create automatic database backup
- Enable maintenance mode
- Run migrations
- Clear all caches
- Verify role migration success
- Disable maintenance mode
- Display summary

### Option 2: Manual Deployment

If you prefer manual control, follow these steps:

#### Step 1: Pre-Deployment Checks

```bash
# Check database connection
php artisan migrate:status

# Verify current user roles (optional)
mysql -u your_user -p your_database -e "
SELECT u.id, u.name, r.name as role
FROM users u
LEFT JOIN model_has_roles mhr ON u.id = mhr.model_id
LEFT JOIN roles r ON mhr.role_id = r.id
LIMIT 10;
"
```

#### Step 2: Create Database Backup

```bash
# Using Laravel Sail
./vendor/bin/sail shell -c "mysqldump -h mysql -u sail -psecret invest > /tmp/db_backup_$(date +%Y%m%d_%H%M%S).sql"

# Or native MySQL
mysqldump -u your_user -p your_database > backup/db_backup_$(date +%Y%m%d_%H%M%S).sql
```

#### Step 3: Enable Maintenance Mode

```bash
# Using Sail
./vendor/bin/sail artisan down

# Or native PHP
php artisan down
```

#### Step 4: Run Migrations

```bash
# Using Sail
./vendor/bin/sail artisan migrate --force

# Or native PHP
php artisan migrate --force

# Expected output:
# Migrating: 2026_01_19_110157_add_role_to_users_table
# Migrated:  2026_01_19_110157_add_role_to_users_table (XX.XXms)
# Migrating: 2026_01_19_110924_drop_permission_tables
# Migrated:  2026_01_19_110924_drop_permission_tables (XX.XXms)
```

#### Step 5: Clear Application Caches

```bash
# Using Sail
./vendor/bin/sail artisan cache:clear
./vendor/bin/sail artisan config:clear
./vendor/bin/sail artisan route:clear
./vendor/bin/sail artisan view:clear

# Or native PHP
php artisan cache:clear
php artisan config:clear
php artisan route:clear
php artisan view:clear
```

#### Step 6: Verify Migration Success

```bash
# Check role column exists
mysql -u your_user -p your_database -e "SHOW COLUMNS FROM users LIKE 'role';"

# Check Spatie tables are gone
mysql -u your_user -p your_database -e "SHOW TABLES LIKE 'roles';"
# Should return empty

# Verify users have roles assigned
mysql -u your_user -p your_database -e "SELECT role, COUNT(*) as count FROM users GROUP BY role;"
# Should show role distribution
```

#### Step 7: Disable Maintenance Mode

```bash
# Using Sail
./vendor/bin/sail artisan up

# Or native PHP
php artisan up
```

## Post-Deployment Verification

After migration completes, verify the following:

### 1. User Authentication
- [ ] Admin users can log in
- [ ] Operator users can log in
- [ ] Investor users can log in
- [ ] Company users can log in

### 2. Panel Access
- [ ] Admin panel accessible at `/admin` (admin/operator roles)
- [ ] Investor panel accessible at `/investor` (all roles)
- [ ] Role-based features display correctly

### 3. Authorization Checks
- [ ] Policies work correctly (vehicle, payment, contribution policies)
- [ ] Filament resources show correct data based on roles
- [ ] Widget calculations display properly

### 4. Database State
```bash
# Check all users have roles
php artisan tinker
>>> User::whereNull('role')->count(); // Should be 0
>>> User::all()->pluck('role')->unique(); // Should show all roles
>>> exit
```

## Rollback Procedures

### If Migration Fails During Execution

The deployment script includes automatic rollback on failure. If using manual deployment:

1. **Restore from backup immediately:**
```bash
# Using Sail
./vendor/bin/sail shell -c "mysql -h mysql -u sail -psecret invest < /tmp/db_backup_YYYYMMDD_HHMMSS.sql"

# Or native MySQL
mysql -u your_user -p your_database < backup/db_backup_YYYYMMDD_HHMMSS.sql
```

2. **Rollback migrations if partially applied:**
```bash
php artisan migrate:rollback --step=2
```

3. **Clear caches:**
```bash
php artisan cache:clear
php artisan config:clear
```

4. **Disable maintenance mode:**
```bash
php artisan up
```

### If Issues Discovered After Migration

1. **Immediate rollback:**
```bash
# Put site in maintenance mode
php artisan down

# Restore database from backup
mysql -u your_user -p your_database < backup/db_backup_YYYYMMDD_HHMMSS.sql

# Clear caches
php artisan cache:clear && php artisan config:clear

# Bring site back up
php artisan up
```

2. **Redeploy previous code version** (if code changes were deployed)

## Optional Cleanup

After successful deployment and verification (wait 24-48 hours):

### Remove Spatie Package from Composer

```bash
# Remove package
composer remove spatie/laravel-permission

# Update autoloader
composer dump-autoload

# Test application
php artisan test
```

### Delete Old Backup Files

```bash
# Keep only the 3 most recent backups
cd storage/app/backups/
ls -t | tail -n +4 | xargs rm --
```

## Troubleshooting

### Problem: Migration fails with "Column 'role' already exists"

**Cause:** Migration was partially run before

**Solution:**
```bash
# Check if column exists
mysql -e "SHOW COLUMNS FROM users LIKE 'role';"

# If it exists, skip first migration
php artisan migrate:status
php artisan migrate --force  # Will skip already-run migrations
```

### Problem: Users have NULL roles after migration

**Cause:** model_has_roles table was empty or users weren't assigned roles

**Solution:**
```bash
# Set default role for users with NULL
php artisan tinker
>>> User::whereNull('role')->update(['role' => 'investor']);
>>> exit
```

### Problem: "Class 'Spatie\Permission\Traits\HasRoles' not found"

**Cause:** Code not deployed before running migration

**Solution:**
1. Deploy latest code changes first
2. Verify User model no longer uses HasRoles trait
3. Run migrations again

### Problem: Backup script fails with permission denied

**Cause:** Script doesn't have execute permissions

**Solution:**
```bash
chmod +x deploy-role-migration.sh
./deploy-role-migration.sh
```

### Problem: Authentication breaks after migration

**Cause:** Session data might reference old Spatie structures

**Solution:**
```bash
# Clear all sessions
php artisan session:flush

# Or delete session files
rm storage/framework/sessions/*

# Users will need to log in again
```

## Testing in Staging

Before deploying to production, test the migration in a staging environment:

1. **Create staging database backup:**
```bash
php artisan db:backup
```

2. **Run deployment script in dry-run mode (if available):**
```bash
./deploy-role-migration.sh --dry-run
```

3. **Execute migration:**
```bash
./deploy-role-migration.sh
```

4. **Test all user roles:**
- Log in as each role type
- Verify panel access
- Check authorization policies
- Test widget calculations

5. **Run test suite:**
```bash
php artisan test
# Expected: 85+ tests passing
```

## Expected Downtime

- **Automated Script:** 2-3 minutes
- **Manual Deployment:** 5-10 minutes

Downtime occurs only during:
- Migration execution (30-60 seconds)
- Cache clearing (30 seconds)

## Success Criteria

Migration is successful when:

- [x] Both migrations show as "Migrated" in `php artisan migrate:status`
- [x] Users table has `role` column with index
- [x] All 5 Spatie tables are dropped
- [x] All users have non-null roles assigned
- [x] Authentication works for all user types
- [x] Authorization policies function correctly
- [x] Application tests pass (85+ tests)
- [x] No database errors in logs

## Support

If you encounter issues not covered in this guide:

1. Check Laravel logs: `storage/logs/laravel.log`
2. Review migration output for error messages
3. Restore from backup if critical issues occur
4. Contact development team with:
   - Error messages
   - Migration output
   - Database state before migration

## References

- **Development Migrations:** `database/migrations/2026_01_19_*`
- **User Model Changes:** `app/Models/User.php`
- **Automated Script:** `deploy-role-migration.sh`
- **Project Documentation:** `CLAUDE.md`
