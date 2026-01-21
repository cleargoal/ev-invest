# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project Overview

This is a Laravel 11 application with Filament admin panels for managing an investment tracking system. The application handles vehicle investments, investor payments, and profit distribution calculations. It features dual admin interfaces - one for general admin tasks and another specifically for investors.

## Development Commands

### Laravel/PHP Commands
- `php artisan serve` - Start development server (default Laravel)
- `php artisan migrate` - Run database migrations  
- `php artisan db:seed` - Run database seeders
- `php artisan tinker` - Open Laravel REPL
- `php artisan test` - Run PHPUnit tests
- `composer install` - Install PHP dependencies
- `vendor/bin/pint` - Run Laravel Pint code formatter

### Custom Commands
- `php artisan user:ensure-admin` - Create or upgrade admin user
  - Interactive mode: prompts for details
  - Non-interactive: use --email, --name, --password, --force flags
  - Safe to run multiple times, preserves existing data
- `php artisan diagnose:missing-contributions --investors=1,7` - Diagnose missing contribution records
- `php artisan db:backup` - Create database backup (automatic rotation keeps 10 most recent)
- `php artisan db:backup --keep=20` - Create backup and keep 20 most recent backups

### Frontend Commands  
- `npm run dev` - Start Vite development server with hot reload
- `npm run build` - Build production assets
- `npm install` - Install Node.js dependencies

### Docker (Laravel Sail)
The project uses Laravel Sail with custom ports to avoid conflicts:
- Application runs on port 8006 (http://localhost:8006)
- Check `.env` file for port configurations if conflicts occur

## Core Architecture

### Multi-Panel Filament Setup
- **Admin Panel** (`/admin`) - General administration (access controlled by admin role)
- **Investor Panel** (`/investor`) - Investor-specific interface (open access with role-based features)

### Key Models & Relationships
- **User** - Investors/operators with simple custom role system (`role` column)
- **Payment** - Investment payments made by users
- **Total** - Running total calculations of the investment pool
- **Vehicle** - Investment vehicles that can be bought/sold
- **Contribution** - User contribution records with first/last relationships
- **Leasing** - Leasing income tracking
- **Operation** - General business operations

### Service Layer Architecture
Key services handle business logic:
- `TotalService` - Manages investment pool calculations and running totals
- `ContributionService` - Handles user contribution processing and percentage recalculations
- `PaymentService` - Processes payment validations, confirmations, and contribution creation  
- `VehicleService` - Manages vehicle lifecycle (creation, buying, selling, unselling)
- `VehicleCancellationService` - Handles vehicle sale cancellations and unselling operations
- `LeasingService` - Handles leasing income calculations
- `WidgetGeneralChartsService` & `WidgetPersonalChartsService` - Chart data generation

### Authentication & Authorization
- Uses Laravel Breeze for basic authentication
- Simple custom role system with single `role` column in users table
- Roles: `admin`, `operator`, `investor`, `company`
- **Operator Role**: Major investor who receives income and notifications but separated in statistics
- Panel access controlled in `User::canAccessPanel()`
- Role checking via custom `User::hasRole()` method
- See `docs/ROLES.md` for detailed role behavior

### Business Logic Flow
1. **Investment Process**: Investors make payments (FIRST/CONTRIB operations)
2. **Payment Confirmation**: Operators confirm payments → creates **1 contribution per investor** with updated amounts AND percentages
3. **Vehicle Purchase**: Company buys vehicles → **auto backup**
4. **Vehicle Sale**: Distributes profit using **existing percentages** (no recalc) → **auto backup**
5. **Vehicle Unselling**: Reverses income using **existing percentages** (no recalc) → **auto backup**
6. **Contribution Logic**: Each confirmed payment creates ONE contribution record per investor (not duplicate records). Payment owner gets updated amount, all investors get recalculated percentages.

### Operation Types & Money Flow
- **FIRST/CONTRIB** (Positive): Initial investments and additional contributions
- **BUY_CAR** (Positive): Pool money spent on vehicle purchases
- **INCOME** (Positive): Investor share of vehicle sale profits (50% total, distributed by percentage)
- **REVENUE** (Positive): Company commission from vehicle sales (50%)
- **WITHDRAW** (Negative): Investor withdrawals stored as negative values (e.g., -$100)
- **RECULC** (Negative): System reversals for unsold vehicles

### Database Structure
- Uses MySQL for development and testing (configured in Laravel Sail)
- Test database: `testing` (automatically created by Sail's database initialization)
- Key tables: users, payments, totals, contributions, vehicles, leasings, operations
- Relationships managed through Eloquent models with proper foreign keys

### Money Handling & Casts
- **MoneyCast**: Used across models (Vehicle, Payment, Contribution, Total)
- **Database Storage**: All monetary values stored in cents for precision
- **Application Display**: MoneyCast automatically converts cents ↔ dollars
- **Important**: Database aggregations (sum/avg) bypass MoneyCast and return raw cents
- **Widget Calculations**: Manual conversion required for sum() operations (`/ 100`)
- **Testing Note**: MoneyCast may cause conversion issues in tests (e.g., 500.0 vs 5.0), use flexible assertions

### Testing
- PHPUnit configured with Feature and Unit test directories using MySQL test database
- Comprehensive test coverage including:
  - `AllOperationsContributionTest` - Verifies all operation types create proper contributions
  - `UnsoldVehicleContributionTest` - Tests vehicle unselling and contribution reversals
  - `ContributionAlgorithmExplanationTest` - Documents contribution calculation behavior
  - `CompleteUnsoldContributionFlowTest` - End-to-end unselling workflow tests
  - `MissingInvestorsContributionBugTest` - Regression tests for contribution edge cases
  - `VehicleServiceTest` - Tests vehicle selling, unselling, and zero-profit scenarios
  - `VehicleResourceFixTest` - Tests UI filtering and business logic correctness
- Run tests with `./vendor/bin/sail test` (preferred) or `php artisan test`
- **Testing Status**: 82 passing, 14 failing (as of latest fixes - mainly MoneyCast conversion issues)

## Development Notes

### User Roles & Access
Default test accounts (password: 'password'):
- admin@mail.org (admin) - Full administrative access
- operator@mail.org (operator) - Major investor + operations management
- investor@mail.org (investor) - Regular minority investor
- company@mail.com (company) - Company commissions only

**Note**: Operator receives investment income and notifications. See `docs/ROLES.md` for role details.

### Key Directories
- `app/Filament/` - Filament admin panel configurations (separate Admin/Investor directories)
- `app/Services/` - Business logic services including VehicleCancellationService for unselling
- `app/Models/` - Eloquent models with relationships and MoneyCast integration
- `database/seeders/` - Realistic database seeders:
  - `VehicleSeeder` - Creates test vehicles with costs and planned sales prices
  - `PaymentSeeder` - Creates BUY_CAR payments for vehicles + initial investor payments
  - `TotalSeeder` - Processes all payments to create running pool totals
- `resources/views/filament/` - Custom Filament view templates

### Widget System
Filament widgets for dashboard analytics:
- `StatsOverviewGeneral` - Investment pool summary with proper money unit handling
- Personal investor performance tracking  
- Vehicle sales monitoring with profit calculations
- Payment confirmation interfaces

### Data Seeding Order
For realistic test data, run seeders in this order:
1. `UserSeeder` - Creates/updates users with roles (uses `updateOrCreate()` to preserve existing users)
2. `AdminSeeder` - (Optional) Quick admin user creation/restore for production
3. `VehicleSeeder` - Creates test vehicles
4. `PaymentSeeder` - Creates vehicle purchase payments + investor payments
5. `TotalSeeder` - Calculates running pool totals

### Database Backups
**Event-Driven Strategy** (automatic backups on critical operations):
- **Triggers**: Vehicle purchase, vehicle sale, vehicle unselling, payment confirmation
- **Storage**: `storage/app/backups/` with format `db_backup-YYYY-MM-DD_HH-ii-ss.sql`
- **Execution**: Background process using `nohup` to prevent blocking web requests
- **Rotation**: Keeps 10 most recent backups by default (customizable with `--keep`)
- **Production Setup**:
  - Fix permissions: `sudo chown -R www-data:www-data storage/ && sudo chmod -R 775 storage/`
  - Test manually: `php artisan db:backup`
- See `docs/BACKUP_STRATEGY.md` for details

### Important Notes
- **Contribution Creation**: Each payment creates 1 contribution record per investor (unified amount + percentage update)
- **Contribution Resource**: Displayed as "Історія зміни Балансу" (Balance History) in investor panel, filtered to logged-in user only
- **Withdrawal Handling**: Stored as negative amounts in database, automatically decreases contribution balance
- **Vehicle Unselling**: Completely resets vehicle to for-sale state, reverses contributions using existing percentages
- **User Balance**: Use `$user->lastContribution->amount` (no `actual_contribution` field)
- **Chart Data**: Only shows confirmed payments via `active()` scope (excludes pending/cancelled)
- **Seeder Safety**: UserSeeder uses `updateOrCreate()` - safe to run multiple times, preserves existing user IDs and passwords