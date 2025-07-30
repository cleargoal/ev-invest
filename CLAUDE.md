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
- `ContributionService` - Handles user contribution processing
- `PaymentService` - Processes payment validations and confirmations  
- `VehicleService` - Manages vehicle lifecycle (creation, editing, marking as sold)
- `LeasingService` - Handles leasing income calculations
- `WidgetGeneralChartsService` & `WidgetPersonalChartsService` - Chart data generation

### Authentication & Authorization
- Uses Laravel Breeze for basic authentication
- Spatie Laravel Permission for role-based access control
- Roles: admin, operator, investor, company
- Panel access controlled in `User::canAccessPanel()`

### Business Logic Flow
1. Investors make payments through the system
2. Operators confirm/validate payments when funds are received  
3. System calculates running totals and user percentages
4. Profit distribution calculated based on contribution percentages
5. Vehicle sales trigger profit calculations and distributions

### Database Structure
- Uses SQLite for development (`database/database.sqlite`)
- Key tables: users, payments, totals, contributions, vehicles, leasings, operations
- Relationships managed through Eloquent models with proper foreign keys

### Testing
- PHPUnit configured with Feature and Unit test directories
- Includes authentication tests and business logic tests (e.g., `InvestIncomeTest`)
- Run tests with `php artisan test`

## Development Notes

### User Roles & Access
Default test accounts (password: 'password'):
- admin@mail.org (admin)
- operator@mail.org (operator)  
- investor@mail.org (investor)
- company@mail.com (company)

### Key Directories
- `app/Filament/` - Filament admin panel configurations (separate Admin/Investor directories)
- `app/Services/` - Business logic services
- `app/Models/` - Eloquent models with relationships
- `database/seeders/` - Database seeders including ComplexSeeder for comprehensive test data
- `resources/views/filament/` - Custom Filament view templates

### Widget System
Filament widgets for dashboard analytics:
- Investment pool charts and statistics
- Personal investor performance tracking  
- Vehicle sales monitoring
- Payment confirmation interfaces