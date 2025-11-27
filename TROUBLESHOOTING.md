# Troubleshooting Guide

## Common Issues and Solutions

### Issue: Doctrine DBAL Error (Laravel 10+)

**Error Message:**
```
Class "Doctrine\DBAL\Driver\AbstractMySQLDriver" not found
```

**Solution:**
✅ **Already Fixed in v1.0.0+**

This package does not require Doctrine DBAL. If you're seeing this error:

1. **Update to latest version:**
   ```bash
   composer update superaudit/super-audit
   ```

2. **Or reinstall:**
   ```bash
   composer remove superaudit/super-audit
   composer require superaudit/super-audit
   ```

**Note:** Version 1.0.0+ uses native MySQL queries instead of Doctrine DBAL, making it fully compatible with Laravel 10-12 without additional dependencies.

---

### Issue: Migration Already Exists

**Error Message:**
```
Migration table already exists
```

**Solution:**
```bash
# Remove the published migration
rm database/migrations/*_create_audit_logs_table.php

# Or skip if using package migrations
php artisan migrate --path=vendor/superaudit/super-audit/src/Migrations
```

---

### Issue: Triggers Not Created

**Error Message:**
```
No triggers created
```

**Solution:**

1. **Check MySQL version** (needs 5.7+):
   ```bash
   php artisan tinker
   DB::select('SELECT VERSION()');
   ```

2. **Check database connection:**
   ```bash
   php artisan db:show
   ```

3. **Run with verbose output:**
   ```bash
   php artisan audit:setup-triggers -v
   ```

4. **Recreate triggers:**
   ```bash
   php artisan audit:setup-triggers --drop
   ```

---

### Issue: JSON_OBJECT Not Supported

**Error Message:**
```
FUNCTION database.JSON_OBJECT does not exist
```

**Solution:**
Upgrade MySQL to version 5.7 or higher. JSON_OBJECT was introduced in MySQL 5.7.

Check your MySQL version:
```bash
mysql --version
```

---

### Issue: Triggers Fire But No Logs Created

**Symptoms:**
- Triggers exist in database
- Data changes happen
- No entries in `super_audit_logs`

**Solutions:**

1. **Check middleware is registered:**
   ```bash
   php artisan route:list --middleware=web
   ```

2. **Manually check if middleware runs:**
   ```php
   // In a controller
   dd(request()->fullUrl());
   ```

3. **Check session variables:**
   ```bash
   php artisan tinker
   DB::select('SELECT @current_user_id, @current_url');
   ```

4. **For console/Tinker, variables are NULL** (expected behavior)

---

### Issue: Composite Primary Key Error

**Error Message:**
```
Table has a composite primary key. Skipping.
```

**Solution:**
This is expected. The package only supports single-column primary keys.

**Workaround:**
If you need to audit a table with composite keys, consider:
1. Adding an auto-increment `id` column
2. Or creating a custom trigger manually

---

### Issue: Large old_data/new_data Fields

**Symptoms:**
- Audit logs table growing quickly
- Slow queries on audit_logs

**Solutions:**

1. **Exclude large tables:**
   ```php
   // config/super-audit.php
   'excluded_tables' => [
       'sessions',
       'cache',
       'large_data_table',
   ],
   ```

2. **Archive old logs:**
   ```php
   // Example: Keep only last 90 days
   AuditLog::where('created_at', '<', now()->subDays(90))->delete();
   ```

3. **Add indexes for common queries:**
   ```sql
   CREATE INDEX idx_created_at ON super_audit_logs(created_at);
   CREATE INDEX idx_table_action_created ON super_audit_logs(table_name, action, created_at);
   ```

---

### Issue: Binary/BLOB Columns Missing in Audit

**Symptoms:**
- Some columns not appearing in old_data/new_data

**Solution:**
This is expected behavior. Binary, BLOB, and spatial columns are automatically excluded from audit JSON to prevent errors and keep audit logs manageable.

Excluded column types:
- blob, binary, varbinary
- tinyblob, mediumblob, longblob
- geometry, point, linestring, polygon, etc.

---

### Issue: Class Not Found After Installation

**Error Message:**
```
Class 'SuperAudit\SuperAudit\SuperAuditServiceProvider' not found
```

**Solutions:**

1. **Clear caches:**
   ```bash
   php artisan config:clear
   php artisan cache:clear
   composer dump-autoload
   ```

2. **Check composer.json has autoload:**
   ```json
   "psr-4": {
       "SuperAudit\\SuperAudit\\": "vendor/superaudit/super-audit/src/"
   }
   ```

3. **Reinstall package:**
   ```bash
   composer remove superaudit/super-audit
   composer require superaudit/super-audit
   ```

---

### Issue: Triggers Slow Down Application

**Symptoms:**
- Slow INSERT/UPDATE/DELETE operations
- High database CPU

**Solutions:**

1. **Exclude high-frequency tables:**
   ```php
   'excluded_tables' => [
       'sessions',
       'cache',
       'jobs',
       'failed_jobs',
   ],
   ```

2. **Partition audit_logs table:**
   ```sql
   -- Example: Partition by month
   ALTER TABLE super_audit_logs
   PARTITION BY RANGE (TO_DAYS(created_at)) (
       PARTITION p202401 VALUES LESS THAN (TO_DAYS('2024-02-01')),
       PARTITION p202402 VALUES LESS THAN (TO_DAYS('2024-03-01')),
       -- etc
   );
   ```

3. **Run archival regularly:**
   ```bash
   # Schedule in app/Console/Kernel.php
   $schedule->call(function () {
       AuditLog::where('created_at', '<', now()->subMonths(6))->delete();
   })->monthly();
   ```

---

### Issue: Works in Tinker but Not in Web Requests

**Symptoms:**
- Tinker: user_id is NULL (expected)
- Web: user_id is still NULL (unexpected)

**Solutions:**

1. **Check middleware is registered:**
   ```php
   // Check in app/Http/Kernel.php or bootstrap/app.php (Laravel 11+)
   ```

2. **Verify auth is working:**
   ```php
   // In controller
   dd(Auth::id(), Auth::user());
   ```

3. **Check session variables manually:**
   ```php
   DB::statement("SET @current_user_id = ?", [1]);
   DB::statement("SET @current_url = ?", ['test']);
   
   // Then create a record and check
   User::create([...]);
   dd(AuditLog::latest()->first());
   ```

---

## Testing Checklist

After installation, verify:

- [ ] `super_audit_logs` table exists
- [ ] Triggers exist (`SHOW TRIGGERS;`)
- [ ] INSERT creates audit log
- [ ] UPDATE creates audit log with old/new data
- [ ] DELETE creates audit log with old data
- [ ] user_id captured in web requests
- [ ] url captured in web requests
- [ ] Raw SQL queries are logged

---

## Getting Help

If you're still experiencing issues:

1. **Check Laravel version compatibility:**
   - Laravel 7-12 supported
   - PHP 7.3-8.3 supported
   - MySQL 5.7+ required

2. **Enable debug mode:**
   ```php
   // In SetAuditVariables middleware, add:
   logger()->info('Audit variables set', [
       'user_id' => Auth::id(),
       'url' => $request->fullUrl()
   ]);
   ```

3. **Check database logs:**
   ```sql
   -- Enable general log temporarily
   SET GLOBAL general_log = 'ON';
   -- Check /var/log/mysql/mysql.log
   ```

4. **Report issue on GitHub** with:
   - Laravel version
   - PHP version
   - MySQL version
   - Complete error message
   - Steps to reproduce

---

## Performance Tips

✅ **Do's:**
- Exclude system/cache tables
- Archive old audit logs regularly
- Add indexes for your query patterns
- Use pagination when viewing logs

❌ **Don'ts:**
- Don't audit every single table if you have hundreds
- Don't keep audit logs forever without archival
- Don't query old_data/new_data JSON fields directly in WHERE clauses (slow)

---

**Package Version:** 1.0.0+  
**Last Updated:** 2024-11-27
