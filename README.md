# Super Audit

A comprehensive Laravel package for automatic database audit logging using MySQL triggers. Tracks all INSERT, UPDATE, and DELETE operations with complete old and new data, user information, and URLs.

## 🎉 What's New in v1.2.0

**Delta Logging** - Massive storage optimization! 

The latest version now stores **only changed fields** instead of entire rows for UPDATE operations:

```diff
- Old: Stores ALL 20+ columns even if only 1 changed (2 KB per update)
+ New: Stores ONLY changed columns (200 bytes per update)
```

**Benefits:**
- 📉 **80-90% storage reduction** for typical workloads
- ⚡ **Faster queries** (smaller JSON to parse)
- 💰 **Lower costs** for cloud databases
- 🔍 **Better clarity** (see exactly what changed)

**Migration:** Simply rebuild your triggers:
```bash
php artisan audit:setup-triggers
```

👉 [Read the full Delta Logging guide](DELTA_LOGGING_UPDATE.md)

---


## Features

✅ **Automatic Tracking** - Captures all database changes via MySQL triggers  
✅ **Complete History** - Stores both old and new data as JSON  
✅ **User Tracking** - Records who made each change  
✅ **URL Tracking** - Captures the request URL for web changes  
✅ **Raw SQL Support** - Works with both Eloquent and raw SQL queries  
✅ **Easy Setup** - Simple installation and configuration  
✅ **Flexible** - Exclude specific tables from auditing  
✅ **No Extra Dependencies** - Works without Doctrine DBAL (Laravel 10+ compatible)  

## Requirements

- PHP 7.3 or higher
- Laravel 7.x, 8.x, 9.x, 10.x, 11.x, or 12.x
- MySQL 5.7+ (requires JSON_OBJECT support)

## Installation

Install via Composer:

```bash
composer require superaudit/super-audit
```

The package will auto-register via Laravel's package discovery.

## Setup

### 1. Publish Configuration (Optional)

```bash
php artisan vendor:publish --tag=super-audit-config
```

### 2. Run Migrations

```bash
php artisan migrate
```

This creates the `super_audit_logs` table.

### 3. Setup Database Triggers

```bash
php artisan audit:setup-triggers
```

This command creates triggers for all your database tables (except excluded ones).

**Options:**
- `--drop` - Drop existing triggers before creating new ones
- `--tables=users,posts` - Only setup triggers for specific tables

### Available Commands

**Setup Triggers** (First time or new tables)
```bash
php artisan audit:setup-triggers
```

**Drop Triggers** (Remove all triggers)
```bash
php artisan audit:drop-triggers
```
Options:
- `--force` - Skip confirmation prompt
- `--tables=users,posts` - Only drop triggers for specific tables

**Rebuild Triggers** (After schema changes)
```bash
php artisan audit:rebuild-triggers
```
This drops all existing triggers and recreates them. Perfect for:
- After adding/removing columns
- After changing column types
- After adding new tables

Options:
- `--force` - Skip confirmation prompt
- `--tables=users,posts` - Only rebuild triggers for specific tables

## Configuration

Edit `config/super-audit.php`:

```php
return [
    // Tables to exclude from auditing
    'excluded_tables' => [
        'sessions',
        'cache',
    ],
    
    // Auto-register middleware (default: true)
    'auto_register_middleware' => true,
];
```

## Usage

Once installed and configured, Super Audit works automatically! All database changes are logged.

### Querying Audit Logs

```php
use SuperAudit\SuperAudit\Models\AuditLog;

// Get all logs for a specific table
$logs = AuditLog::forTable('users')->get();

// Get all logs for a specific record
$logs = AuditLog::forTable('users')
    ->forRecord(1)
    ->get();

// Get all logs by a specific user
$logs = AuditLog::byUser(1)->get();

// Get all INSERT operations
$logs = AuditLog::forAction('insert')->get();

// Get logs with user relationship
$logs = AuditLog::with('user')->latest()->get();

foreach ($logs as $log) {
    echo "User: " . $log->user->name;
    echo "Action: " . $log->action;
    echo "Old Data: " . json_encode($log->old_data);
    echo "New Data: " . json_encode($log->new_data);
}
```

### Available Scopes

- `forTable($tableName)` - Filter by table name
- `forRecord($recordId)` - Filter by record ID
- `forAction($action)` - Filter by action (insert, update, delete)
- `byUser($userId)` - Filter by user ID

### Example Audit Log Entry

**UPDATE operation (v1.2.0+ with Delta Logging):**
```json
{
  "id": 1,
  "table_name": "users",
  "record_id": "5",
  "action": "update",
  "user_id": 1,
  "url": "https://example.com/users/5/edit",
  "old_data": {
    "name": "John Doe",
    "email": "john@example.com"
  },
  "new_data": {
    "name": "John Smith",
    "email": "john.smith@example.com"
  },
  "created_at": "2024-01-15 10:30:00"
}
```
*Note: Only the `name` and `email` fields are stored because only those fields changed!*

**INSERT operation:**
```json
{
  "id": 2,
  "table_name": "users",
  "record_id": "6",
  "action": "insert",
  "user_id": 1,
  "url": "https://example.com/users/create",
  "old_data": null,
  "new_data": {
    "id": 6,
    "name": "Jane Doe",
    "email": "jane@example.com",
    "created_at": "2024-01-15 10:35:00"
    // ... all other fields
  },
  "created_at": "2024-01-15 10:35:00"
}
```
*Note: All fields are stored for INSERT since all fields are new.*

## How It Works

1. **Middleware** sets MySQL session variables (`@current_user_id`, `@current_url`)
2. **Database Triggers** automatically fire on INSERT, UPDATE, DELETE
3. **Triggers** insert a record into `super_audit_logs` with old/new data
4. **Model** provides easy access to query audit history

## Advanced Usage

### Manual Middleware Registration

If you disabled auto-registration in config:

```php
// app/Http/Kernel.php
protected $middlewareGroups = [
    'web' => [
        // ...
        \SuperAudit\SuperAudit\Middleware\SetAuditVariables::class,
    ],
];
```

### Rebuilding Triggers

If you add new tables or modify your schema:

```bash
php artisan audit:setup-triggers --drop
```

### Console Commands

For console commands, user_id and url will be NULL since there's no authenticated user or HTTP request.

## Performance Considerations

✅ **v1.2.0+ Delta Logging** dramatically improves storage efficiency:
- **UPDATE operations**: Only changed fields stored (80-90% reduction)
- **Smaller JSON**: Faster queries and parsing
- **Lower storage costs**: Especially beneficial for cloud databases

**Best Practices:**
- Triggers run on every database operation
- Consider regular archival of old audit logs
- Exclude high-volume tables if needed (configure in `config/super-audit.php`)
- Monitor database size and optimize accordingly

## Security

- Audit logs are tamper-evident (triggers can't be bypassed by application code)
- Even raw SQL queries are logged
- User authentication is captured automatically

## Limitations

- MySQL only (requires JSON_OBJECT function)
- Tables must have a single-column primary key
- Binary/spatial column types are skipped in audit data
- Composite primary keys are not supported

## License

MIT License

## Support

For issues, questions, or contributions, please visit:
https://github.com/suleman09522/super-audit

## Credits

Created for comprehensive database auditing in Laravel applications.
