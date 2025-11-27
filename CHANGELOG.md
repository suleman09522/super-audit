# Changelog

## [Unreleased]

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
