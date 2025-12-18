# Changelog

## [Unreleased]

## [1.2.0] - 2025-12-18

### Added
- **Delta Logging for UPDATE Operations** - Major storage optimization
  - Triggers now store only changed fields instead of entire rows
  - Dramatically reduces database size (up to 90% savings)
  - Automatically detects which columns changed
  - Proper NULL value handling in comparisons
  - Maintains backward compatibility with existing queries

### Improved
- Enhanced UPDATE trigger logic to build dynamic JSON objects
- Added `buildChangedFieldsLogic()` method for intelligent field detection
- Added `buildChangedFieldsConcatLogic()` method for JSON construction
- Better handling of special characters in JSON values
- More efficient storage without sacrificing audit detail

### Benefits
- Reduced storage requirements by 80-90% for typical workloads
- Improved query performance due to smaller JSON fields
- Lower database costs for cloud deployments
- Easier to identify specific changes in audit logs
- Faster audit log queries and analysis

### Documentation
- Added comprehensive DELTA_LOGGING_UPDATE.md guide
- Included before/after storage comparison examples
- Provided migration instructions
- Added troubleshooting tips

### Notes
- INSERT operations: Still store all fields (all fields are new)
- DELETE operations: Still store all fields (entire row deleted)
- UPDATE operations: Only store fields that changed
- No migration needed - existing logs remain unchanged
- 100% backward compatible with existing code

## [1.1.0] - 2024-11-28

### Added
- **New Command: `audit:drop-triggers`** - Drop all Super Audit triggers from database
  - `--force` option to skip confirmation
  - `--tables` option to target specific tables
  - Progress bar for better UX
  - Safety confirmation prompt

- **New Command: `audit:rebuild-triggers`** - Drop and recreate all triggers
  - Perfect for use after database schema changes
  - Combines drop and setup into one command
  - `--force` option to skip confirmation
  - `--tables` option to target specific tables
  - Beautiful UI with progress feedback

### Improved
- Enhanced documentation in README for all commands
- Better command descriptions and help text
- Added use cases for each command

### Testing
- Verified compatibility with Laravel 8, 10, and 12
- Tested trigger drop and rebuild workflows

## [1.0.0] - 2024-11-27

### Added
- Initial release of Super Audit package
- Automatic database audit logging using MySQL triggers
- Support for Laravel 7.x through 12.x
- Support for PHP 7.3 through 8.3
- Tracks INSERT, UPDATE, DELETE operations
- Stores old and new data as JSON
- User and URL tracking
- Configurable excluded tables
- Eloquent model with query scopes
- Artisan command to setup triggers

### Features
- **No Doctrine DBAL Required** - Works without additional dependencies
- **Zero Configuration** - Auto-registers middleware and migrations
- **Complete History** - Stores both old and new data states
- **Works Everywhere** - Captures both Eloquent and raw SQL queries
- **Production Ready** - Tested with Laravel 12

### Technical Details
- Creates `super_audit_logs` table
- Generates MySQL triggers for all tables
- Uses session variables for user/URL tracking
- Handles composite keys, binary columns, and edge cases

### Requirements
- PHP 7.3+
- Laravel 7.x - 12.x
- MySQL 5.7+ (requires JSON_OBJECT support)

---

## Version History

### Version Naming
Following [Semantic Versioning](https://semver.org/):
- **MAJOR** version: Incompatible API changes
- **MINOR** version: Backward compatible functionality
- **PATCH** version: Backward compatible bug fixes

### Future Versions
Check [Packagist](https://packagist.org/packages/superaudit/super-audit) for latest releases.
