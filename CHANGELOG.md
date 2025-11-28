# Changelog

## [Unreleased]

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
