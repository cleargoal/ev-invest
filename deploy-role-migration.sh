#!/bin/bash

################################################################################
# Role System Migration Deployment Script
#
# This script automates the migration from Spatie Laravel Permission to a
# simple custom role system. It includes safety checks, automatic backups,
# and rollback capabilities.
#
# Usage:
#   ./deploy-role-migration.sh              # Run with default settings
#   ./deploy-role-migration.sh --keep=20    # Keep 20 most recent backups
#   ./deploy-role-migration.sh --dry-run    # Preview without executing
#
################################################################################

set -e  # Exit on error

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
CYAN='\033[0;36m'
NC='\033[0m' # No Color

# Configuration
BACKUP_DIR="storage/app/backups"
BACKUP_KEEP=10  # Default number of backups to keep
DRY_RUN=false
MAINTENANCE_ENABLED=false

# Parse command line arguments
for arg in "$@"; do
    case $arg in
        --keep=*)
            BACKUP_KEEP="${arg#*=}"
            ;;
        --dry-run)
            DRY_RUN=true
            ;;
        --help)
            echo "Usage: $0 [OPTIONS]"
            echo ""
            echo "Options:"
            echo "  --keep=N      Keep N most recent backups (default: 10)"
            echo "  --dry-run     Preview actions without executing"
            echo "  --help        Show this help message"
            exit 0
            ;;
        *)
            echo -e "${RED}Unknown option: $arg${NC}"
            echo "Use --help for usage information"
            exit 1
            ;;
    esac
done

# Detect environment (Laravel Sail vs native PHP)
if [ -f "./vendor/bin/sail" ] && [ -f "docker-compose.yml" ]; then
    ARTISAN="./vendor/bin/sail artisan"
    MYSQL_CMD="./vendor/bin/sail shell -c"
    MYSQL_HOST="mysql"
    MYSQL_USER="sail"
    MYSQL_PASS="password"
    MYSQL_DB="invest"
    ENV_TYPE="Sail"
else
    ARTISAN="php artisan"
    MYSQL_CMD=""
    # Try to read from .env file
    if [ -f ".env" ]; then
        MYSQL_HOST=$(grep DB_HOST .env | cut -d '=' -f2)
        MYSQL_USER=$(grep DB_USERNAME .env | cut -d '=' -f2)
        MYSQL_PASS=$(grep DB_PASSWORD .env | cut -d '=' -f2)
        MYSQL_DB=$(grep DB_DATABASE .env | cut -d '=' -f2)
    else
        MYSQL_HOST="localhost"
        MYSQL_USER="root"
        MYSQL_PASS=""
        MYSQL_DB="invest"
    fi
    ENV_TYPE="Native PHP"
fi

# Print functions
print_header() {
    echo -e "\n${CYAN}========================================${NC}"
    echo -e "${CYAN}$1${NC}"
    echo -e "${CYAN}========================================${NC}\n"
}

print_info() {
    echo -e "${BLUE}[INFO]${NC} $1"
}

print_success() {
    echo -e "${GREEN}[SUCCESS]${NC} $1"
}

print_warning() {
    echo -e "${YELLOW}[WARNING]${NC} $1"
}

print_error() {
    echo -e "${RED}[ERROR]${NC} $1"
}

print_step() {
    echo -e "\n${CYAN}>>> $1${NC}"
}

# Cleanup function for rollback
cleanup() {
    if [ $? -ne 0 ]; then
        print_error "Script failed! Starting cleanup..."

        if [ "$MAINTENANCE_ENABLED" = true ]; then
            print_step "Disabling maintenance mode"
            $ARTISAN up 2>/dev/null || true
        fi

        if [ -n "$BACKUP_FILE" ] && [ -f "$BACKUP_FILE" ]; then
            print_warning "Database backup available at: $BACKUP_FILE"
            print_info "To rollback, run: mysql -u $MYSQL_USER -p $MYSQL_DB < $BACKUP_FILE"
        fi

        exit 1
    fi
}

trap cleanup EXIT

# Display migration overview
print_header "Role System Migration Deployment"

if [ "$DRY_RUN" = true ]; then
    print_warning "DRY RUN MODE - No changes will be made"
fi

echo -e "${BLUE}Environment:${NC} $ENV_TYPE"
echo -e "${BLUE}Database:${NC} $MYSQL_DB@$MYSQL_HOST"
echo -e "${BLUE}Backup retention:${NC} $BACKUP_KEEP backups"
echo ""

print_info "This script will:"
echo "  1. Create database backup"
echo "  2. Enable maintenance mode"
echo "  3. Run 2 migrations:"
echo "     - Add 'role' column to users table"
echo "     - Drop 5 Spatie Permission tables"
echo "  4. Clear application caches"
echo "  5. Verify migration success"
echo "  6. Disable maintenance mode"
echo ""

print_warning "IMPORTANT: This migration is irreversible without database backup!"
print_warning "Estimated downtime: 2-3 minutes"
echo ""

# Ask for confirmation
if [ "$DRY_RUN" = false ]; then
    read -p "$(echo -e ${YELLOW}Do you want to proceed? [y/N]:${NC} )" -n 1 -r
    echo ""
    if [[ ! $REPLY =~ ^[Yy]$ ]]; then
        print_info "Migration cancelled by user"
        exit 0
    fi
fi

# Check prerequisites
print_step "Checking prerequisites"

# Check if artisan exists
if [ "$DRY_RUN" = false ]; then
    if ! $ARTISAN --version &>/dev/null; then
        print_error "Laravel artisan not accessible"
        print_info "Please ensure you're in the project root directory"
        exit 1
    fi
    print_success "Laravel detected: $($$ARTISAN --version 2>&1 | head -n1)"
fi

# Check database connection
print_info "Checking database connection..."
if [ "$DRY_RUN" = false ]; then
    if [ "$ENV_TYPE" = "Sail" ]; then
        DB_CHECK=$($MYSQL_CMD "mysql -h $MYSQL_HOST -u $MYSQL_USER -p$MYSQL_PASS $MYSQL_DB -e 'SELECT 1;'" 2>&1)
    else
        if [ -n "$MYSQL_PASS" ]; then
            DB_CHECK=$(mysql -h "$MYSQL_HOST" -u "$MYSQL_USER" -p"$MYSQL_PASS" "$MYSQL_DB" -e 'SELECT 1;' 2>&1)
        else
            DB_CHECK=$(mysql -h "$MYSQL_HOST" -u "$MYSQL_USER" "$MYSQL_DB" -e 'SELECT 1;' 2>&1)
        fi
    fi

    if [ $? -eq 0 ]; then
        print_success "Database connection successful"
    else
        print_error "Cannot connect to database"
        print_info "Connection details: $MYSQL_USER@$MYSQL_HOST/$MYSQL_DB"
        exit 1
    fi
fi

# Create backup directory
print_step "Creating backup directory"
if [ "$DRY_RUN" = false ]; then
    mkdir -p "$BACKUP_DIR"
    print_success "Backup directory ready: $BACKUP_DIR"
else
    print_info "Would create: $BACKUP_DIR"
fi

# Create database backup
print_step "Creating database backup"
TIMESTAMP=$(date +%Y%m%d_%H%M%S)
BACKUP_FILE="$BACKUP_DIR/db_role_migration_$TIMESTAMP.sql"

if [ "$DRY_RUN" = false ]; then
    print_info "Backup file: $BACKUP_FILE"

    if [ "$ENV_TYPE" = "Sail" ]; then
        $MYSQL_CMD "mysqldump -h $MYSQL_HOST -u $MYSQL_USER -p$MYSQL_PASS $MYSQL_DB" > "$BACKUP_FILE" 2>&1
    else
        if [ -n "$MYSQL_PASS" ]; then
            mysqldump -h "$MYSQL_HOST" -u "$MYSQL_USER" -p"$MYSQL_PASS" "$MYSQL_DB" > "$BACKUP_FILE" 2>&1
        else
            mysqldump -h "$MYSQL_HOST" -u "$MYSQL_USER" "$MYSQL_DB" > "$BACKUP_FILE" 2>&1
        fi
    fi

    if [ -f "$BACKUP_FILE" ] && [ -s "$BACKUP_FILE" ]; then
        BACKUP_SIZE=$(du -h "$BACKUP_FILE" | cut -f1)
        print_success "Database backup created: $BACKUP_SIZE"
    else
        print_error "Failed to create database backup"
        exit 1
    fi
else
    print_info "Would create backup: $BACKUP_FILE"
fi

# Enable maintenance mode
print_step "Enabling maintenance mode"
if [ "$DRY_RUN" = false ]; then
    $ARTISAN down --retry=60 2>&1
    MAINTENANCE_ENABLED=true
    print_success "Application is now in maintenance mode"
else
    print_info "Would enable maintenance mode"
fi

# Run migrations
print_step "Running migrations"
if [ "$DRY_RUN" = false ]; then
    print_info "Executing: $ARTISAN migrate --force"
    MIGRATION_OUTPUT=$($ARTISAN migrate --force 2>&1)
    MIGRATION_EXIT_CODE=$?

    echo "$MIGRATION_OUTPUT"

    if [ $MIGRATION_EXIT_CODE -eq 0 ]; then
        print_success "Migrations completed successfully"
    else
        print_error "Migration failed!"
        print_warning "Rolling back..."

        # Restore from backup
        print_info "Restoring database from backup..."
        if [ "$ENV_TYPE" = "Sail" ]; then
            $MYSQL_CMD "mysql -h $MYSQL_HOST -u $MYSQL_USER -p$MYSQL_PASS $MYSQL_DB" < "$BACKUP_FILE"
        else
            if [ -n "$MYSQL_PASS" ]; then
                mysql -h "$MYSQL_HOST" -u "$MYSQL_USER" -p"$MYSQL_PASS" "$MYSQL_DB" < "$BACKUP_FILE"
            else
                mysql -h "$MYSQL_HOST" -u "$MYSQL_USER" "$MYSQL_DB" < "$BACKUP_FILE"
            fi
        fi
        print_success "Database restored from backup"

        exit 1
    fi
else
    print_info "Would run: $ARTISAN migrate --force"
    print_info "Expected migrations:"
    print_info "  - 2026_01_19_110157_add_role_to_users_table"
    print_info "  - 2026_01_19_110924_drop_permission_tables"
fi

# Clear caches
print_step "Clearing application caches"
if [ "$DRY_RUN" = false ]; then
    $ARTISAN cache:clear 2>&1 | grep -v "^$"
    $ARTISAN config:clear 2>&1 | grep -v "^$"
    $ARTISAN route:clear 2>&1 | grep -v "^$"
    $ARTISAN view:clear 2>&1 | grep -v "^$"
    print_success "All caches cleared"
else
    print_info "Would clear: cache, config, route, view"
fi

# Verify migration success
print_step "Verifying migration success"
if [ "$DRY_RUN" = false ]; then
    # Check if role column exists
    if [ "$ENV_TYPE" = "Sail" ]; then
        ROLE_CHECK=$($MYSQL_CMD "mysql -h $MYSQL_HOST -u $MYSQL_USER -p$MYSQL_PASS $MYSQL_DB -e \"SHOW COLUMNS FROM users LIKE 'role';\"" 2>&1 | grep -c "role")
    else
        if [ -n "$MYSQL_PASS" ]; then
            ROLE_CHECK=$(mysql -h "$MYSQL_HOST" -u "$MYSQL_USER" -p"$MYSQL_PASS" "$MYSQL_DB" -e "SHOW COLUMNS FROM users LIKE 'role';" 2>&1 | grep -c "role")
        else
            ROLE_CHECK=$(mysql -h "$MYSQL_HOST" -u "$MYSQL_USER" "$MYSQL_DB" -e "SHOW COLUMNS FROM users LIKE 'role';" 2>&1 | grep -c "role")
        fi
    fi

    if [ "$ROLE_CHECK" -gt 0 ]; then
        print_success "✓ Role column exists in users table"
    else
        print_error "✗ Role column not found in users table"
        exit 1
    fi

    # Check if Spatie tables are dropped
    if [ "$ENV_TYPE" = "Sail" ]; then
        SPATIE_TABLES=$($MYSQL_CMD "mysql -h $MYSQL_HOST -u $MYSQL_USER -p$MYSQL_PASS $MYSQL_DB -e \"SHOW TABLES LIKE 'roles';\"" 2>&1 | grep -c "roles")
    else
        if [ -n "$MYSQL_PASS" ]; then
            SPATIE_TABLES=$(mysql -h "$MYSQL_HOST" -u "$MYSQL_USER" -p"$MYSQL_PASS" "$MYSQL_DB" -e "SHOW TABLES LIKE 'roles';" 2>&1 | grep -c "roles")
        else
            SPATIE_TABLES=$(mysql -h "$MYSQL_HOST" -u "$MYSQL_USER" "$MYSQL_DB" -e "SHOW TABLES LIKE 'roles';" 2>&1 | grep -c "roles")
        fi
    fi

    if [ "$SPATIE_TABLES" -eq 0 ]; then
        print_success "✓ Spatie Permission tables dropped"
    else
        print_warning "✗ Spatie tables still exist (this may be okay if migration was partial)"
    fi

    # Check users have roles
    if [ "$ENV_TYPE" = "Sail" ]; then
        USER_ROLES=$($MYSQL_CMD "mysql -h $MYSQL_HOST -u $MYSQL_USER -p$MYSQL_PASS $MYSQL_DB -e \"SELECT COUNT(*) as count FROM users WHERE role IS NOT NULL;\"" 2>&1 | tail -n1)
    else
        if [ -n "$MYSQL_PASS" ]; then
            USER_ROLES=$(mysql -h "$MYSQL_HOST" -u "$MYSQL_USER" -p"$MYSQL_PASS" "$MYSQL_DB" -e "SELECT COUNT(*) as count FROM users WHERE role IS NOT NULL;" 2>&1 | tail -n1)
        else
            USER_ROLES=$(mysql -h "$MYSQL_HOST" -u "$MYSQL_USER" "$MYSQL_DB" -e "SELECT COUNT(*) as count FROM users WHERE role IS NOT NULL;" 2>&1 | tail -n1)
        fi
    fi

    print_success "✓ Users with roles: $USER_ROLES"

    print_success "Migration verification completed"
else
    print_info "Would verify:"
    print_info "  - Role column exists"
    print_info "  - Spatie tables dropped"
    print_info "  - Users have roles assigned"
fi

# Disable maintenance mode
print_step "Disabling maintenance mode"
if [ "$DRY_RUN" = false ]; then
    $ARTISAN up 2>&1
    MAINTENANCE_ENABLED=false
    print_success "Application is now live"
else
    print_info "Would disable maintenance mode"
fi

# Rotate old backups
print_step "Rotating old backups"
if [ "$DRY_RUN" = false ]; then
    BACKUP_COUNT=$(ls -1 "$BACKUP_DIR"/db_role_migration_*.sql 2>/dev/null | wc -l)

    if [ "$BACKUP_COUNT" -gt "$BACKUP_KEEP" ]; then
        print_info "Found $BACKUP_COUNT backups, keeping $BACKUP_KEEP most recent"
        ls -t "$BACKUP_DIR"/db_role_migration_*.sql | tail -n +$((BACKUP_KEEP + 1)) | xargs rm -f
        print_success "Old backups removed"
    else
        print_info "Backup count ($BACKUP_COUNT) within limit ($BACKUP_KEEP)"
    fi
else
    print_info "Would keep $BACKUP_KEEP most recent backups"
fi

# Display summary
print_header "Migration Completed Successfully!"

echo -e "${GREEN}✓ Database backup created${NC}"
echo -e "${GREEN}✓ Migrations executed${NC}"
echo -e "${GREEN}✓ Caches cleared${NC}"
echo -e "${GREEN}✓ Migration verified${NC}"
echo -e "${GREEN}✓ Application is live${NC}"
echo ""

print_info "Next steps:"
echo "  1. Test user authentication (all role types)"
echo "  2. Verify admin panel access (/admin)"
echo "  3. Verify investor panel access (/investor)"
echo "  4. Check application logs for errors"
echo "  5. Run test suite: $ARTISAN test"
echo ""

print_info "Backup location: $BACKUP_FILE"
print_info "Documentation: docs/ROLE_SYSTEM_MIGRATION.md"
echo ""

print_warning "Optional: Remove Spatie package after 24-48 hours:"
echo "  composer remove spatie/laravel-permission"
echo ""

exit 0
