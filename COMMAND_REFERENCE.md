# Super Audit - Command Reference

Complete guide to all available Super Audit commands.

## Commands Overview

| Command | Purpose | When to Use |
|---------|---------|-------------|
| `audit:setup-triggers` | Create triggers | First install, new tables added |
| `audit:drop-triggers` | Remove all triggers | Disable auditing, cleanup |
| `audit:rebuild-triggers` | Drop & recreate triggers | After schema changes |

---

## 1. Setup Triggers

**Command:**
```bash
php artisan audit:setup-triggers
```

**Description:**
Creates MySQL triggers for all database tables to automatically log INSERT, UPDATE, and DELETE operations.

**When to Use:**
- ✅ First time installation
- ✅ After adding new tables to your database
- ✅ To add auditing to tables that were previously excluded

**Options:**

| Option | Description | Example |
|--------|-------------|---------|
| `--drop` | Drop existing triggers before creating | `php artisan audit:setup-triggers --drop` |
| `--tables=` | Only setup triggers for specific tables | `php artisan audit:setup-triggers --tables=users,posts` |

**Examples:**

```bash
# Setup triggers for all tables
php artisan audit:setup-triggers

# Setup triggers for specific tables only
php artisan audit:setup-triggers --tables=users,orders,products

# Recreate all triggers
php artisan audit:setup-triggers --drop
```

**Output:**
```
Setting up audit triggers...
✓ Created triggers for: users
✓ Created triggers for: posts
✓ Created triggers for: products
...

=== Summary ===
Success: 15
Skipped: 5
```

---

## 2. Drop Triggers

**Command:**
```bash
php artisan audit:drop-triggers
```

**Description:**
Removes all Super Audit triggers from your database tables. This stops automatic audit logging.

**When to Use:**
- ✅ Temporarily disable auditing
- ✅ Before uninstalling the package
- ✅ Cleanup during development/testing
- ✅ Before rebuilding triggers (use `rebuild` command instead)

**Options:**

| Option | Description | Example |
|--------|-------------|---------|
| `--force` | Skip confirmation prompt | `php artisan audit:drop-triggers --force` |
| `--tables=` | Only drop triggers for specific tables | `php artisan audit:drop-triggers --tables=users,posts` |

**Examples:**

```bash
# Drop all triggers (with confirmation)
php artisan audit:drop-triggers

# Drop triggers without confirmation
php artisan audit:drop-triggers --force

# Drop triggers for specific tables only
php artisan audit:drop-triggers --tables=users,orders
```

**Interactive Prompt:**
```
This will drop all Super Audit triggers. Do you want to continue? (yes/no) [no]:
> yes

Dropping audit triggers...
Successfully dropped 45 trigger(s).

💡 Tip: To rebuild triggers, run: php artisan audit:setup-triggers
```

**With --force (No Prompt):**
```bash
php artisan audit:drop-triggers --force
```
```
Dropping audit triggers...
██████████████████████████████████████ 100%
Successfully dropped 45 trigger(s).
```

---

## 3. Rebuild Triggers

**Command:**
```bash
php artisan audit:rebuild-triggers
```

**Description:**
Drops all existing triggers and recreates them. This is the recommended command after any database schema changes.

**When to Use:**
- ✅ After adding or removing columns
- ✅ After changing column types or names
- ✅ After database migrations that alter table structure
- ✅ After upgrading the Super Audit package
- ✅ When triggers are out of sync with schema

**Options:**

| Option | Description | Example |
|--------|-------------|---------|
| `--force` | Skip confirmation prompt | `php artisan audit:rebuild-triggers --force` |
| `--tables=` | Only rebuild triggers for specific tables | `php artisan audit:rebuild-triggers --tables=users` |

**Examples:**

```bash
# Rebuild all triggers (with confirmation)
php artisan audit:rebuild-triggers

# Rebuild without confirmation
php artisan audit:rebuild-triggers --force

# Rebuild triggers for specific tables
php artisan audit:rebuild-triggers --tables=users,products
```

**Output:**
```
🔄 Rebuilding Super Audit triggers...

This will drop and recreate all triggers. Continue? (yes/no) [no]:
> yes

━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
Step 1/2: Dropping existing triggers...
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━

Dropping audit triggers...
██████████████████████████████████████ 100%
Successfully dropped 45 trigger(s).

━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
Step 2/2: Creating new triggers...
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━

Setting up audit triggers...
✓ Created triggers for: users
✓ Created triggers for: posts
✓ Created triggers for: products
...

=== Summary ===
Success: 15
Skipped: 5

━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
✅ Triggers rebuilt successfully!
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━

Your database audit triggers are now up to date.
```

---

## Common Workflows

### Initial Setup
```bash
# 1. Install package
composer require superaudit/super-audit

# 2. Run migrations
php artisan migrate

# 3. Setup triggers
php artisan audit:setup-triggers
```

### After Database Migration
```bash
# After any schema changes
php artisan migrate

# Rebuild triggers to reflect new schema
php artisan audit:rebuild-triggers
```

### Add New Table to Auditing
```bash
# Option 1: Setup triggers for specific table
php artisan audit:setup-triggers --tables=new_table

# Option 2: Rebuild all
php artisan audit:rebuild-triggers
```

### Disable Auditing for Specific Tables
```php
// config/super-audit.php
'excluded_tables' => [
    'sessions',
    'cache',
    'table_to_exclude',
],
```
```bash
# Then rebuild
php artisan audit:rebuild-triggers
```

### Temporary Disable All Auditing
```bash
# Drop all triggers
php artisan audit:drop-triggers

# Later, re-enable
php artisan audit:setup-triggers
```

### Uninstall Package
```bash
# 1. Drop all triggers
php artisan audit:drop-triggers --force

# 2. Optional: Drop audit logs table
php artisan migrate:rollback --step=1

# 3. Remove package
composer remove superaudit/super-audit
```

---

## Command Comparison

### Setup vs Rebuild

**Use `setup-triggers`** when:
- Installing for the first time
- Adding new tables
- Re-enabling auditing

**Use `rebuild-triggers`** when:
- Schema changed (columns added/removed/modified)
- Package updated
- Triggers not working correctly

---

## Checking Trigger Status

### View All Triggers
```sql
SHOW TRIGGERS;
```

### Count Super Audit Triggers
```sql
SELECT COUNT(*) 
FROM INFORMATION_SCHEMA.TRIGGERS 
WHERE TRIGGER_NAME LIKE 'after_%';
```

### Check Specific Table Triggers
```sql
SHOW TRIGGERS FROM your_database WHERE `Table` = 'users';
```

### Via PHP
```php
use Illuminate\Support\Facades\DB;

$triggers = DB::select("
    SELECT TRIGGER_NAME, EVENT_MANIPULATION, EVENT_OBJECT_TABLE
    FROM INFORMATION_SCHEMA.TRIGGERS
    WHERE TRIGGER_SCHEMA = DATABASE()
    AND TRIGGER_NAME LIKE 'after_%'
");

dd($triggers);
```

---

## Troubleshooting Commands

### Trigger Not Working?
```bash
# Drop and recreate
php artisan audit:rebuild-triggers --force
```

### Added Column Not in Audit?
```bash
# Rebuild triggers to include new column
php artisan audit:rebuild-triggers
```

### Too Many Triggers?
```bash
# Drop all first
php artisan audit:drop-triggers --force

# Then setup with exclusions
# Edit config/super-audit.php first, then:
php artisan audit:setup-triggers
```

---

## Performance Tips

### For Large Databases
```bash
# Setup triggers for important tables only
php artisan audit:setup-triggers --tables=users,orders,payments
```

### During Maintenance
```bash
# Disable during heavy migrations
php artisan audit:drop-triggers --force

# Run migrations
php artisan migrate

# Re-enable
php artisan audit:setup-triggers
```

---

## Quick Reference

```bash
# Setup (first time)
php artisan audit:setup-triggers

# Drop (disable)
php artisan audit:drop-triggers

# Rebuild (after changes)
php artisan audit:rebuild-triggers

# Specific tables
php artisan audit:rebuild-triggers --tables=users,posts

# Skip confirmation
php artisan audit:rebuild-triggers --force
```

---

**Version:** 1.1.0+  
**Last Updated:** 2024-11-28
