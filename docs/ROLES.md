# Role System Documentation

## Overview

This application uses a simple custom role system with a single `role` column in the users table. Each user has exactly one role that determines their permissions and behavior.

## Role Definitions

### investor (Minority Investor)
**Purpose**: Regular investor who contributes to the pool

**Permissions**:
- ✅ Receives income from vehicle sales and leasing
- ✅ Receives email notifications (pool changes, vehicle purchases, unselling)
- ✅ Can create payments (make contributions)
- ✅ Included in "minority investor" statistics
- ✅ Access to investor panel
- ✅ Percentage calculated in pool share

**Typical User**: Individual investors who contribute funds to the vehicle pool

---

### operator (Major Investor)
**Purpose**: Major investor who also manages operations

**Permissions**:
- ✅ Receives income from vehicle sales and leasing
- ✅ Receives email notifications (pool changes, vehicle purchases, unselling)
- ✅ Can create payments (make contributions)
- ✅ Access to investor panel
- ✅ Owns vehicles (user_id on vehicle records)
- ✅ Makes initial pool funding

**Statistics Behavior**:
- ✅ Included in total pool calculations
- ❌ Excluded from "minority investor" statistics (shown separately)
- ❌ Not included in "minority investor percentage" calculations

**Why Separated?**: To show clear distinction between major investor (operator) and regular minority investors in reporting.

**Typical User**: Company owner or primary investor who operates the business

---

### admin
**Purpose**: Administrative access to manage system

**Permissions**:
- ✅ Full access to admin panel
- ✅ Can edit payments, contributions, vehicles
- ✅ Can manage users and roles
- ❌ Does not receive investment income
- ❌ Does not receive investor notifications

**Typical User**: System administrator

---

### company
**Purpose**: Receives company commissions

**Permissions**:
- ✅ Receives company commissions (50% of profits)
- ❌ Cannot be investor (business rule)
- ❌ Cannot create payments
- ❌ Does not receive investor notifications
- ❌ Cannot access investor panel

**Typical User**: Company entity for commission tracking

---

## Income Distribution

When vehicles are sold or leasing income is generated:

**Recipients**: Users with `role IN ('investor', 'operator')` who have contributions

**Distribution Logic**:
1. Total profit calculated
2. Company commission deducted (50%)
3. Remaining amount distributed to investors + operator based on their contribution percentages

**Example**:
```
Vehicle sold for profit: $10,000
Company commission: $5,000 (50%)
Remaining for distribution: $5,000

Operator has 60% share → Receives $3,000
Investor A has 30% share → Receives $1,500
Investor B has 10% share → Receives $500
```

---

## Notification Recipients

**Email Notifications Sent To**: Users with `role IN ('investor', 'operator')`

**Notification Types**:
1. Pool total changes (`TotalListener`)
2. Vehicle purchases (`VehicleListener`)
3. Vehicle unselling (`VehicleCancellationService`)

---

## Statistics & Widgets

### StatsOverviewGeneral
- **Total Pool**: Includes operator + all investors
- **Minority Investments**: Excludes operator (shows only investors)
- **Minority Investor Share %**: Excludes operator

### StatsOverviewPersonal
- **My Share %**: Calculated against investor pool only (excludes operator)

**Rationale**: Provides clear reporting on minority investor positions separate from major investor (operator).

---

## Multi-Role Users

**Policy**: One account = one role

If a user needs multiple roles (e.g., admin who is also investor):
- **Option 1**: Create two separate accounts
  - Account 1: john@company.com (admin)
  - Account 2: john-investor@company.com (investor)
- **Option 2**: Choose primary role
  - Decide which role is most important
  - Use that role exclusively

**Admin Management**: Roles are managed manually by administrators through Filament panels.

---

## Technical Implementation

### Role Column
- **Location**: `users.role` (string, indexed)
- **Default**: 'investor'
- **Values**: 'admin', 'operator', 'investor', 'company'

### Custom Methods
```php
// User model
public function hasRole(string|array $roles): bool
public function assignRole(string $role): void
public function getRoleAttribute($value): string
```

### Income Distribution
```php
// HandlesInvestmentCalculations trait
User::whereIn('role', ['investor', 'operator'])
    ->whereHas('contributions')
    ->get();
```

### Notifications
```php
// Listeners
User::whereIn('role', ['investor', 'operator'])->get();
```

---

## Migration History

**Previous System**: Spatie Laravel Permission (many-to-many roles)
**Current System**: Simple role column (one role per user)

**Migration Date**: 2026-01-19

**Why Changed**:
- Eliminated database deadlocks in tests
- Simplified architecture (5 fewer tables)
- Better performance (no relationship joins)
- Easier to understand and maintain

**See Also**: `docs/ROLE_SYSTEM_MIGRATION.md` for production deployment guide
