# Migration Guide: Upgrading to v1.2.0 (Delta Logging)

## Overview

This guide will help you upgrade from any previous version (1.0.x or 1.1.x) to version 1.2.0, which includes the delta logging optimization.

---

## Pre-Migration Checklist

Before upgrading, make sure you have:

- [ ] **Backup your database** (especially the `super_audit_logs` table)
- [ ] **Note your current database size** for before/after comparison
- [ ] **Test in a development/staging environment first**
- [ ] **Check Laravel and PHP versions** (requires Laravel 7-12, PHP 7.3-8.3)
- [ ] **Review the CHANGELOG** to understand what's changing

---

## Migration Steps

### Step 1: Update the Package

Choose one of the following methods:

#### Option A: Via Composer (Recommended)

```bash
composer update superaudit/super-audit
```

#### Option B: Via Git (If using the repository directly)

```bash
cd /path/to/super-audit
git pull origin main
```

### Step 2: Verify the Update

Check the version was updated:

```bash
composer show superaudit/super-audit
```

You should see version `1.2.0` or higher.

### Step 3: Rebuild Database Triggers

This is the **most important step**. The triggers need to be recreated to use the new delta logging logic:

```bash
php artisan audit:rebuild-triggers
```

**What this does:**
- Drops all existing audit triggers
- Creates new triggers with delta logging logic
- Shows progress and summary

**Output example:**
```
Rebuilding audit triggers...

⊘ Skipped (excluded): migrations
⊘ Skipped (excluded): super_audit_logs
✓ Rebuilt triggers for: users
✓ Rebuilt triggers for: posts
✓ Rebuilt triggers for: comments

=== Summary ===
Success: 3
Skipped: 2
Errors: 0
```

### Step 4: Verify Triggers Were Created

Check that triggers are in place:

```bash
# Using artisan command (if available)
php artisan audit:status

# Or directly in MySQL
mysql -u your_user -p your_database -e "SHOW TRIGGERS;"
```

You should see triggers like:
- `after_insert_users`
- `after_update_users`
- `after_delete_users`
- (and similar for other tables)

### Step 5: Test the Delta Logging

Perform a simple test to verify delta logging is working:

```php
// In tinker or a test script
php artisan tinker

>>> $user = \App\Models\User::first();
>>> $user->email = 'newemail@example.com';
>>> $user->save();

// Check the audit log
>>> $log = \SuperAudit\SuperAudit\Models\SuperAuditLog::latest()->first();
>>> $log->old_data;  // Should only show 'email' field
>>> $log->new_data;  // Should only show 'email' field
```

**Expected result:**
```php
// old_data
[
    "email" => "old@example.com"
]

// new_data
[
    "email" => "newemail@example.com"
]
```

✅ If you only see the `email` field (not all user fields), delta logging is working!

---

## What Changes

### Database Schema
**No changes** - The `super_audit_logs` table structure remains the same.

### Trigger Logic
**Updated** - UPDATE triggers now use delta logging to store only changed fields.

### Application Code
**No changes** - Your application code doesn't need any modifications.

### Existing Audit Logs
**Unchanged** - Old audit logs remain as-is with full row data.

### New Audit Logs
**Delta format** - New logs will only contain changed fields for UPDATE operations.

---

## Expected Results

### Storage Savings

After migration, you should see:

**Immediate:**
- **No change** in existing audit log size
- **Smaller new audit logs** for UPDATE operations

**Over time** (as new logs accumulate):
- **80-90% reduction** in storage growth rate
- **Faster queries** due to smaller JSON fields
- **Lower costs** for cloud database hosting

### Before/After Comparison

**Example for a high-volume table:**

| Metric | Before (v1.0-1.1) | After (v1.2.0) | Savings |
|--------|-------------------|----------------|---------|
| Average UPDATE log size | 2,000 bytes | 200 bytes | **90%** |
| Daily storage growth | 20 MB | 2 MB | **90%** |
| Yearly storage growth | 7.3 GB | 730 MB | **90%** |

---

## Rollback Plan

If you need to rollback for any reason:

### Option 1: Downgrade Package

```bash
# Downgrade to v1.1.0
composer require superaudit/super-audit:^1.1

# Rebuild triggers with old logic
php artisan audit:rebuild-triggers
```

### Option 2: Manual Trigger Recreation

If you saved your old trigger definitions, you can recreate them manually:

```sql
DROP TRIGGER IF EXISTS after_update_users;

CREATE TRIGGER after_update_users
AFTER UPDATE ON `users`
FOR EACH ROW
BEGIN
    -- Your old trigger logic here
END;
```

---

## Troubleshooting

### Issue: Triggers not being created

**Symptom:** `audit:rebuild-triggers` shows errors

**Solution:**
1. Check database permissions (user needs CREATE TRIGGER privilege)
2. Check MySQL version (requires 5.7+)
3. Review error messages for specific issues

```bash
# Check permissions
mysql -u your_user -p -e "SHOW GRANTS;"

# Should include: GRANT TRIGGER ON database_name.*
```

### Issue: Audit logs still showing full data

**Symptom:** New UPDATE logs still contain all fields

**Solution:**
1. Verify you ran `audit:rebuild-triggers` (not just `audit:setup-triggers`)
2. Check that triggers were actually recreated (use `SHOW TRIGGERS;`)
3. Clear any application cache

```bash
php artisan cache:clear
php artisan config:clear
```

### Issue: Performance degradation

**Symptom:** Slower database operations after upgrade

**Solution:**
1. This is unlikely but possible in edge cases
2. Check trigger execution time with MySQL profiling
3. Consider excluding very high-volume tables
4. Contact support if issues persist

---

## Verification Checklist

After migration, verify:

- [ ] Package version is 1.2.0+
- [ ] Triggers were rebuilt successfully
- [ ] Test UPDATE shows only changed fields in audit log
- [ ] Test INSERT shows all fields in audit log (expected)
- [ ] Test DELETE shows all fields in audit log (expected)
- [ ] Application functionality unchanged
- [ ] No errors in application logs
- [ ] Database size growth is slower for new logs

---

## Production Deployment

### Recommended Approach

1. **Test in development** first
2. **Deploy to staging** and monitor
3. **Run for 24-48 hours** in staging
4. **Verify storage savings** and functionality
5. **Deploy to production** during low-traffic window

### Deployment Commands

```bash
# 1. Update package
composer update superaudit/super-audit

# 2. Rebuild triggers (brief downtime for trigger recreation)
php artisan audit:rebuild-triggers --force

# 3. Verify
php artisan audit:status

# 4. Monitor logs
tail -f storage/logs/laravel.log
```

### Zero-Downtime Strategy

If you need absolute zero downtime:

1. The trigger rebuild takes only a few seconds per table
2. During rebuild, that table's triggers are unavailable
3. Any changes during those seconds won't be audited
4. This is usually acceptable for audit logs

If not acceptable:
1. Create a read replica
2. Test migration on replica
3. Promote replica to primary (if using managed database)

---

## Monitoring Post-Migration

Track these metrics after migration:

### Database Size
```sql
-- Check audit log table size
SELECT 
    table_name,
    ROUND(((data_length + index_length) / 1024 / 1024), 2) AS 'Size (MB)'
FROM information_schema.TABLES
WHERE table_name = 'super_audit_logs';
```

### Growth Rate
```sql
-- Check daily growth
SELECT 
    DATE(created_at) as date,
    COUNT(*) as log_count,
    ROUND(SUM(LENGTH(JSON_UNQUOTE(old_data)) + LENGTH(JSON_UNQUOTE(new_data))) / 1024, 2) as total_kb
FROM super_audit_logs
WHERE created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
    AND action = 'update'
GROUP BY DATE(created_at)
ORDER BY date DESC;
```

### Field Count per Log
```sql
-- Average fields stored per UPDATE
SELECT 
    AVG(JSON_LENGTH(old_data)) as avg_fields_old,
    AVG(JSON_LENGTH(new_data)) as avg_fields_new
FROM super_audit_logs
WHERE action = 'update'
    AND created_at >= DATE_SUB(NOW(), INTERVAL 1 DAY);
```

---

## Support

### Need Help?

- 📖 [Delta Logging Guide](DELTA_LOGGING_UPDATE.md)
- 🔧 [Troubleshooting Guide](TROUBLESHOOTING.md)
- 📝 [Changelog](CHANGELOG.md)
- 💬 [GitHub Issues](https://github.com/your-repo/super-audit/issues)

### Reporting Issues

If you encounter problems:

1. Check existing GitHub issues
2. Gather information:
   - Laravel version
   - MySQL version
   - Error messages
   - Trigger status output
3. Create a detailed issue report

---

## FAQ

**Q: Will I lose my existing audit logs?**  
A: No. Existing logs remain unchanged. Only new logs use delta format.

**Q: Do I need to update my application code?**  
A: No. The API remains the same. Queries work identically.

**Q: Can I use delta logging for INSERT/DELETE?**  
A: Delta logging only applies to UPDATE operations (where it provides the most benefit).

**Q: How do I see the storage savings?**  
A: Compare `super_audit_logs` table size before and after migration, and monitor growth rate.

**Q: Can I configure which columns to always audit?**  
A: Not yet. This feature may be added in a future version.

---

**Congratulations!** You've successfully migrated to Super Audit v1.2.0 with delta logging! 🎉

Enjoy your **80-90% storage savings**! 💰
