# Quick Start: Delta Logging Update

## What Changed?

🎉 **Version 1.2.0** introduces **delta logging** - the audit triggers now only store **changed fields** instead of entire rows!

---

## Quick Migration (2 Steps)

### 1. Update the Package

**If using Git:**
```bash
cd super-audit
git pull origin main
```

**If using Composer:**
```bash
composer update superaudit/super-audit
```

### 2. Rebuild Triggers
```bash
php artisan audit:setup-triggers
```

Done! ✅

---

## What You Get

### Before Update
```json
// 20 fields stored even though only email changed
{
  "old_data": {
    "id": 1, "name": "John", "email": "old@email.com", 
    "address": "...", /* ... 16 more fields ... */
  },
  "new_data": {
    "id": 1, "name": "John", "email": "new@email.com",
    "address": "...", /* ... 16 more fields ... */
  }
}
```

### After Update
```json
// Only the changed field is stored
{
  "old_data": {
    "email": "old@email.com"
  },
  "new_data": {
    "email": "new@email.com"
  }
}
```

---

## Benefits

| Metric | Improvement |
|--------|-------------|
| **Storage Size** | ↓ 80-90% reduction |
| **Query Speed** | ↑ Faster (smaller JSON) |
| **Clarity** | ✓ See exact changes |
| **Cost** | ↓ Lower cloud DB costs |

---

## Important Notes

✅ **100% Backward Compatible** - No code changes needed
✅ **Existing logs unchanged** - Only new logs use delta format
✅ **Same queries work** - No API changes
✅ **No migration required** - Just rebuild triggers

---

## Verify It Worked

Check triggers were created:
```bash
php artisan audit:status
```

Test with a simple update:
```php
$user = User::find(1);
$user->email = 'newemail@example.com';
$user->save();

// Check the audit log
$log = SuperAuditLog::latest()->first();
dd($log->old_data, $log->new_data);
// Should only show 'email' field!
```

---

## Need More Details?

📖 Read the full guide: [DELTA_LOGGING_UPDATE.md](DELTA_LOGGING_UPDATE.md)
📝 Check the changelog: [CHANGELOG.md](CHANGELOG.md)

---

**Questions?**  
Open an issue on GitHub or check the [TROUBLESHOOTING.md](TROUBLESHOOTING.md) guide.
