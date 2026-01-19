#!/bin/bash

################################################################################
# Role Migration Rollback Script
#
# This script helps restore the database from a backup if issues occur after
# the role system migration.
#
# Usage:
#   ./rollback-role-migration.sh                    # Interactive mode
#   ./rollback-role-migration.sh backup_file.sql    # Restore specific backup
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
MAINTENANCE_ENABLED=false

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

# Cleanup function
cleanup() {
    if [ "$MAINTENANCE_ENABLED" = true ]; then
        print_step "Disabling maintenance mode"
        $ARTISAN up 2>/dev/null || true
    fi
}

trap cleanup EXIT

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

# Display rollback header
print_header "Role Migration Rollback"

print_warning "This script will restore your database from a backup"
print_warning "ALL current database data will be lost!"
echo ""

# Determine backup file
if [ -z "$1" ]; then
    # Interactive mode - list available backups
    print_info "Available backups:"
    echo ""

    if [ ! -d "$BACKUP_DIR" ] || [ -z "$(ls -A $BACKUP_DIR/db_role_migration_*.sql 2>/dev/null)" ]; then
        print_error "No backups found in $BACKUP_DIR"
        print_info "Please specify backup file manually:"
        print_info "  $0 /path/to/backup.sql"
        exit 1
    fi

    # List backups with numbers
    BACKUPS=($(ls -t "$BACKUP_DIR"/db_role_migration_*.sql))
    for i in "${!BACKUPS[@]}"; do
        BACKUP_FILE="${BACKUPS[$i]}"
        BACKUP_SIZE=$(du -h "$BACKUP_FILE" | cut -f1)
        BACKUP_DATE=$(basename "$BACKUP_FILE" | sed 's/db_role_migration_//' | sed 's/.sql//')
        echo -e "  ${CYAN}[$((i+1))]${NC} $BACKUP_FILE ($BACKUP_SIZE) - $BACKUP_DATE"
    done

    echo ""
    read -p "$(echo -e ${YELLOW}Select backup number [1-${#BACKUPS[@]}]:${NC} )" -r BACKUP_NUM

    if ! [[ "$BACKUP_NUM" =~ ^[0-9]+$ ]] || [ "$BACKUP_NUM" -lt 1 ] || [ "$BACKUP_NUM" -gt "${#BACKUPS[@]}" ]; then
        print_error "Invalid selection"
        exit 1
    fi

    BACKUP_FILE="${BACKUPS[$((BACKUP_NUM-1))]}"
else
    # Command line argument provided
    BACKUP_FILE="$1"

    if [ ! -f "$BACKUP_FILE" ]; then
        print_error "Backup file not found: $BACKUP_FILE"
        exit 1
    fi
fi

# Display selected backup
print_info "Selected backup: $BACKUP_FILE"
BACKUP_SIZE=$(du -h "$BACKUP_FILE" | cut -f1)
print_info "Backup size: $BACKUP_SIZE"
echo ""

# Final confirmation
print_warning "DANGER: This will completely replace the current database!"
print_warning "Current database: $MYSQL_DB@$MYSQL_HOST"
echo ""
read -p "$(echo -e ${RED}Are you absolutely sure? Type 'ROLLBACK' to continue:${NC} )" -r CONFIRMATION

if [ "$CONFIRMATION" != "ROLLBACK" ]; then
    print_info "Rollback cancelled"
    exit 0
fi

# Create safety backup of current state
print_step "Creating safety backup of current database state"
SAFETY_BACKUP="$BACKUP_DIR/db_before_rollback_$(date +%Y%m%d_%H%M%S).sql"

if [ "$ENV_TYPE" = "Sail" ]; then
    $MYSQL_CMD "mysqldump -h $MYSQL_HOST -u $MYSQL_USER -p$MYSQL_PASS $MYSQL_DB" > "$SAFETY_BACKUP" 2>&1
else
    if [ -n "$MYSQL_PASS" ]; then
        mysqldump -h "$MYSQL_HOST" -u "$MYSQL_USER" -p"$MYSQL_PASS" "$MYSQL_DB" > "$SAFETY_BACKUP" 2>&1
    else
        mysqldump -h "$MYSQL_HOST" -u "$MYSQL_USER" "$MYSQL_DB" > "$SAFETY_BACKUP" 2>&1
    fi
fi

if [ -f "$SAFETY_BACKUP" ] && [ -s "$SAFETY_BACKUP" ]; then
    SAFETY_SIZE=$(du -h "$SAFETY_BACKUP" | cut -f1)
    print_success "Safety backup created: $SAFETY_BACKUP ($SAFETY_SIZE)"
else
    print_error "Failed to create safety backup"
    exit 1
fi

# Enable maintenance mode
print_step "Enabling maintenance mode"
$ARTISAN down --retry=60 2>&1
MAINTENANCE_ENABLED=true
print_success "Application is now in maintenance mode"

# Restore database
print_step "Restoring database from backup"
print_info "This may take a few minutes..."

if [ "$ENV_TYPE" = "Sail" ]; then
    RESTORE_OUTPUT=$($MYSQL_CMD "mysql -h $MYSQL_HOST -u $MYSQL_USER -p$MYSQL_PASS $MYSQL_DB" < "$BACKUP_FILE" 2>&1)
    RESTORE_EXIT=$?
else
    if [ -n "$MYSQL_PASS" ]; then
        RESTORE_OUTPUT=$(mysql -h "$MYSQL_HOST" -u "$MYSQL_USER" -p"$MYSQL_PASS" "$MYSQL_DB" < "$BACKUP_FILE" 2>&1)
        RESTORE_EXIT=$?
    else
        RESTORE_OUTPUT=$(mysql -h "$MYSQL_HOST" -u "$MYSQL_USER" "$MYSQL_DB" < "$BACKUP_FILE" 2>&1)
        RESTORE_EXIT=$?
    fi
fi

if [ $RESTORE_EXIT -eq 0 ]; then
    print_success "Database restored successfully"
else
    print_error "Database restoration failed!"
    echo "$RESTORE_OUTPUT"
    print_warning "Safety backup available at: $SAFETY_BACKUP"
    exit 1
fi

# Rollback migrations (if needed)
print_step "Checking migration status"
MIGRATION_STATUS=$($ARTISAN migrate:status 2>&1)

if echo "$MIGRATION_STATUS" | grep -q "2026_01_19_110924_drop_permission_tables"; then
    print_info "Rolling back migrations..."
    $ARTISAN migrate:rollback --step=2 --force 2>&1
    print_success "Migrations rolled back"
else
    print_info "No migration rollback needed"
fi

# Clear caches
print_step "Clearing application caches"
$ARTISAN cache:clear 2>&1 | grep -v "^$"
$ARTISAN config:clear 2>&1 | grep -v "^$"
$ARTISAN route:clear 2>&1 | grep -v "^$"
$ARTISAN view:clear 2>&1 | grep -v "^$"
print_success "All caches cleared"

# Verify restoration
print_step "Verifying database restoration"

# Check if model_has_roles table exists (old system)
if [ "$ENV_TYPE" = "Sail" ]; then
    OLD_SYSTEM=$($MYSQL_CMD "mysql -h $MYSQL_HOST -u $MYSQL_USER -p$MYSQL_PASS $MYSQL_DB -e \"SHOW TABLES LIKE 'model_has_roles';\"" 2>&1 | grep -c "model_has_roles")
else
    if [ -n "$MYSQL_PASS" ]; then
        OLD_SYSTEM=$(mysql -h "$MYSQL_HOST" -u "$MYSQL_USER" -p"$MYSQL_PASS" "$MYSQL_DB" -e "SHOW TABLES LIKE 'model_has_roles';" 2>&1 | grep -c "model_has_roles")
    else
        OLD_SYSTEM=$(mysql -h "$MYSQL_HOST" -u "$MYSQL_USER" "$MYSQL_DB" -e "SHOW TABLES LIKE 'model_has_roles';" 2>&1 | grep -c "model_has_roles")
    fi
fi

if [ "$OLD_SYSTEM" -gt 0 ]; then
    print_success "✓ Old Spatie Permission system restored"
else
    print_warning "✗ Old system tables not found (backup may be from new system)"
fi

# Disable maintenance mode
print_step "Disabling maintenance mode"
$ARTISAN up 2>&1
MAINTENANCE_ENABLED=false
print_success "Application is now live"

# Display summary
print_header "Rollback Completed"

echo -e "${GREEN}✓ Safety backup created${NC}"
echo -e "${GREEN}✓ Database restored${NC}"
echo -e "${GREEN}✓ Caches cleared${NC}"
echo -e "${GREEN}✓ Application is live${NC}"
echo ""

print_info "Restored from: $BACKUP_FILE"
print_info "Safety backup: $SAFETY_BACKUP"
echo ""

print_warning "Important next steps:"
echo "  1. Test user authentication"
echo "  2. Verify all functionality works correctly"
echo "  3. Check application logs: $ARTISAN tail"
echo "  4. If you need to rollback code changes, redeploy previous version"
echo ""

print_info "If you need to re-attempt the migration:"
echo "  ./deploy-role-migration.sh"
echo ""

exit 0
