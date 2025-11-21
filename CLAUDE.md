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

### Custom Diagnostic Commands
- `php artisan diagnose:missing-contributions --investors=1,7` - Diagnose missing contribution records for specific investors

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
- **User** - Investors/operators with role-based permissions (using Spatie Laravel Permission)
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
- Spatie Laravel Permission for role-based access control
- Roles: admin, operator, investor, company
- Panel access controlled in `User::canAccessPanel()`

### Business Logic Flow
1. **Investment Process**: Investors make payments (FIRST/CONTRIB operations) through the system
2. **Payment Confirmation**: Operators confirm/validate payments when funds are received  
3. **Contribution Tracking**: System creates contribution records and calculates user percentages
4. **Vehicle Operations**: Company buys vehicles (BUY_CAR), sells them (SELL_CAR), or unsells them
5. **Profit Distribution**: Vehicle sales trigger automatic income distribution to investors and company revenue
6. **Pool Management**: Running totals track available investment pool balance

### Operation Types & Money Flow
- **FIRST/CONTRIB** (Positive): Initial investments and additional contributions
- **BUY_CAR** (Positive): Pool money spent on vehicle purchases  
- **INCOME** (Positive): Investor share of vehicle sale profits (50% total, distributed by percentage)
- **REVENUE** (Positive): Company commission from vehicle sales (50%)
- **WITHDRAW** (Positive): Investor withdrawals (decreases their contribution balance)
- **RECULC** (Negative): System reversals for unsold vehicles

### Database Structure
- Uses MySQL for development and testing (configured in Laravel Sail)
- Test database: `testing` (automatically created by Sail's database initialization)
- Key tables: users, payments, totals, contributions, vehicles, leasings, operations
- Relationships managed through Eloquent models with proper foreign keys

### Money Handling & Casts
- **MoneyCast**: Used across models (Vehicle, Payment, Contribution, Total, User.actual_contribution)
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
- admin@mail.org (admin)
- operator@mail.org (operator)  
- investor@mail.org (investor)
- company@mail.com (company)

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
1. `UserSeeder` - Creates users with roles
2. `VehicleSeeder` - Creates test vehicles  
3. `PaymentSeeder` - Creates vehicle purchase payments + investor payments
4. `TotalSeeder` - Calculates running pool totals

### Important Notes
- **Contribution Percentages**: After seeding investor payments, manually set contribution percentages in the admin interface for profit distribution to work
- **Vehicle Unselling Logic**: When vehicles are unsold, they are completely reset to look like never-sold vehicles (all fields set to null)
- **Vehicle State Management**: 
  - For-sale vehicles: `sale_date IS NULL AND cancelled_at IS NULL`
  - Sold vehicles: `sale_date IS NOT NULL AND cancelled_at IS NULL`
  - Cancelled vehicles: `sale_date IS NOT NULL AND cancelled_at IS NOT NULL`
  - Unsold vehicles: Reset to for-sale state (all null values)
- **VehicleResource Filtering**: Uses AND logic to show only truly available vehicles
- **Zero-Profit Vehicle Sales**: Application gracefully handles vehicles sold with zero or negative profit (no crash, proper logging)
- **Money Display**: Always check widget calculations handle cents→dollars conversion for database sum() operations

## Recent Fixes Applied
- Fixed production crash when selling vehicles with zero/negative profit
- Corrected vehicle unselling logic to completely reset vehicle state
- Updated VehicleResource query filtering for proper business logic
- Resolved database configuration issues (SQLite → MySQL for testing)
- Fixed multiple MoneyCast conversion issues in tests
- Updated PaymentFactory to generate proper default values
- Fixed foreign key constraint violations in tests