<?php

namespace SuperAudit\SuperAudit\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class SetupAuditTriggers extends Command
{
    protected $signature = 'audit:setup-triggers';
    protected $description = 'Set up database triggers for audit logging';

    // Data types to skip in audit logs
    protected $skipDataTypes = [
        'blob', 'binary', 'varbinary', 'longblob', 'mediumblob', 'tinyblob',
        'geometry', 'point', 'linestring', 'polygon', 'multipoint', 'multilinestring', 'multipolygon', 'geometrycollection'
    ];

    // Tables to skip
    protected $skipTables = [
        'migrations',
        'super_audit_logs',
        'password_reset_tokens',
        'sessions',
        'cache',
        'cache_locks',
        'jobs',
        'job_batches',
        'failed_jobs',
        'personal_access_tokens'
    ];

    public function handle()
    {
        $this->info('Setting up audit triggers...');
        $this->newLine();

        $tables = $this->getTables();
        $successCount = 0;
        $skippedCount = 0;
        $errorCount = 0;

        foreach ($tables as $table) {
            // Skip excluded tables
            if (in_array($table, $this->skipTables)) {
                $this->line("⊘ Skipped (excluded): {$table}");
                $skippedCount++;
                continue;
            }

            try {
                // Drop existing triggers
                $this->dropTriggers($table);

                // Create new triggers
                if ($this->createTriggers($table)) {
                    $this->info("✓ Created triggers for: {$table}");
                    $successCount++;
                } else {
                    $this->warn("⚠ Skipped table: {$table}");
                    $skippedCount++;
                }
            } catch (\Exception $e) {
                $this->error("✗ Failed to create triggers for {$table}: " . $e->getMessage());
                $errorCount++;
            }
        }

        $this->newLine();
        $this->info("=== Summary ===");
        $this->info("Success: {$successCount}");
        $this->warn("Skipped: {$skippedCount}");
        if ($errorCount > 0) {
            $this->error("Errors: {$errorCount}");
        }

        return $errorCount > 0 ? 1 : 0;
    }

    /**
     * Get all tables in the database.
     * Compatible with Laravel 8-12+
     *
     * @return array
     */
    protected function getTables()
    {
        $connection = DB::connection();
        
        // Try Laravel 8-10 method (Doctrine)
        if (method_exists($connection, 'getDoctrineSchemaManager')) {
            try {
                return $connection->getDoctrineSchemaManager()->listTableNames();
            } catch (\Exception $e) {
                // Fall through to alternative method
            }
        }
        
        // Laravel 11+ / Fallback method using raw SQL
        $databaseName = DB::getDatabaseName();
        $tables = DB::select("SHOW TABLES");
        
        $tableKey = "Tables_in_{$databaseName}";
        
        return array_map(function($table) use ($tableKey) {
            return $table->$tableKey;
        }, $tables);
    }

    /**
     * Drop existing triggers for a table.
     *
     * @param string $table
     * @return void
     */
    protected function dropTriggers($table)
    {
        $triggers = ['after_insert', 'after_update', 'after_delete'];

        foreach ($triggers as $trigger) {
            $triggerName = "{$trigger}_{$table}";
            try {
                DB::unprepared("DROP TRIGGER IF EXISTS {$triggerName}");
            } catch (\Exception $e) {
                // Trigger might not exist, that's fine
            }
        }
    }

    /**
     * Create audit triggers for a table.
     *
     * @param string $table
     * @return bool
     */
    protected function createTriggers($table)
    {
        // Get primary key info
        $primaryKey = $this->getPrimaryKey($table);

        if (!$primaryKey) {
            $this->warn("Table {$table} has no primary key. Skipping.");
            return false;
        }

        $primaryKeyColumn = $primaryKey['column'];
        $primaryKeyType = $primaryKey['type'];

        // Skip if primary key is unsupported type
        if (in_array(strtolower($primaryKeyType), $this->skipDataTypes)) {
            $this->warn("Table {$table} has a primary key of unsupported type {$primaryKeyType}. Skipping.");
            return false;
        }

        // Get columns for JSON object construction
        $columns = $this->getTableColumns($table);

        if (empty($columns)) {
            $this->warn("Table {$table} has no suitable columns. Skipping.");
            return false;
        }

        // Build JSON_OBJECT strings
        $newJsonObject = $this->buildJsonObject($columns, 'NEW');
        $oldJsonObject = $this->buildJsonObject($columns, 'OLD');

        // Create AFTER INSERT trigger
        $this->createInsertTrigger($table, $primaryKeyColumn, $newJsonObject);

        // Create AFTER UPDATE trigger
        $this->createUpdateTrigger($table, $primaryKeyColumn, $oldJsonObject, $newJsonObject);

        // Create AFTER DELETE trigger
        $this->createDeleteTrigger($table, $primaryKeyColumn, $oldJsonObject);

        return true;
    }

    /**
     * Get the primary key column and type for a table.
     *
     * @param string $table
     * @return array|null
     */
    protected function getPrimaryKey($table)
    {
        try {
            $keys = DB::select("
                SELECT 
                    kcu.COLUMN_NAME,
                    c.DATA_TYPE
                FROM INFORMATION_SCHEMA.KEY_COLUMN_USAGE kcu
                JOIN INFORMATION_SCHEMA.COLUMNS c 
                    ON kcu.TABLE_SCHEMA = c.TABLE_SCHEMA 
                    AND kcu.TABLE_NAME = c.TABLE_NAME 
                    AND kcu.COLUMN_NAME = c.COLUMN_NAME
                WHERE kcu.TABLE_SCHEMA = ? 
                    AND kcu.TABLE_NAME = ? 
                    AND kcu.CONSTRAINT_NAME = 'PRIMARY'
                ORDER BY kcu.ORDINAL_POSITION
            ", [DB::getDatabaseName(), $table]);

            if (empty($keys)) {
                return null;
            }

            // Skip composite primary keys
            if (count($keys) > 1) {
                $this->warn("Table {$table} has a composite primary key. Skipping.");
                return null;
            }

            return [
                'column' => $keys[0]->COLUMN_NAME,
                'type' => $keys[0]->DATA_TYPE
            ];
        } catch (\Exception $e) {
            return null;
        }
    }

    /**
     * Get table columns suitable for audit logging.
     *
     * @param string $table
     * @return array
     */
    protected function getTableColumns($table)
    {
        $columns = DB::select("
            SELECT COLUMN_NAME, DATA_TYPE
            FROM INFORMATION_SCHEMA.COLUMNS
            WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ?
        ", [DB::getDatabaseName(), $table]);

        $filteredColumns = [];

        foreach ($columns as $column) {
            // Skip binary and spatial types
            if (in_array(strtolower($column->DATA_TYPE), $this->skipDataTypes)) {
                continue;
            }
            $filteredColumns[] = $column->COLUMN_NAME;
        }

        return $filteredColumns;
    }

    /**
     * Build JSON_OBJECT string for trigger.
     *
     * @param array $columns
     * @param string $prefix (NEW or OLD)
     * @return string
     */
    protected function buildJsonObject($columns, $prefix)
    {
        $parts = [];

        foreach ($columns as $column) {
            // Escape column name for JSON key
            $escapedColumn = str_replace("'", "\\'", $column);
            $parts[] = "'{$escapedColumn}', {$prefix}.`{$column}`";
        }

        return 'JSON_OBJECT(' . implode(', ', $parts) . ')';
    }

    /**
     * Create AFTER INSERT trigger.
     *
     * @param string $table
     * @param string $primaryKeyColumn
     * @param string $newJsonObject
     * @return void
     */
    protected function createInsertTrigger($table, $primaryKeyColumn, $newJsonObject)
    {
        $triggerName = "after_insert_{$table}";

        DB::unprepared("
            CREATE TRIGGER {$triggerName}
            AFTER INSERT ON `{$table}`
            FOR EACH ROW
            BEGIN
                INSERT INTO super_audit_logs (table_name, record_id, action, user_id, url, old_data, new_data, created_at)
                VALUES (
                    '{$table}',
                    NEW.`{$primaryKeyColumn}`,
                    'insert',
                    @current_user_id,
                    @current_url,
                    NULL,
                    {$newJsonObject},
                    NOW()
                );
            END;
        ");
    }

    /**
     * Create AFTER UPDATE trigger.
     *
     * @param string $table
     * @param string $primaryKeyColumn
     * @param string $oldJsonObject
     * @param string $newJsonObject
     * @return void
     */
    protected function createUpdateTrigger($table, $primaryKeyColumn, $oldJsonObject, $newJsonObject)
    {
        $triggerName = "after_update_{$table}";

        DB::unprepared("
            CREATE TRIGGER {$triggerName}
            AFTER UPDATE ON `{$table}`
            FOR EACH ROW
            BEGIN
                INSERT INTO super_audit_logs (table_name, record_id, action, user_id, url, old_data, new_data, created_at)
                VALUES (
                    '{$table}',
                    NEW.`{$primaryKeyColumn}`,
                    'update',
                    @current_user_id,
                    @current_url,
                    {$oldJsonObject},
                    {$newJsonObject},
                    NOW()
                );
            END;
        ");
    }

    /**
     * Create AFTER DELETE trigger.
     *
     * @param string $table
     * @param string $primaryKeyColumn
     * @param string $oldJsonObject
     * @return void
     */
    protected function createDeleteTrigger($table, $primaryKeyColumn, $oldJsonObject)
    {
        $triggerName = "after_delete_{$table}";

        DB::unprepared("
            CREATE TRIGGER {$triggerName}
            AFTER DELETE ON `{$table}`
            FOR EACH ROW
            BEGIN
                INSERT INTO super_audit_logs (table_name, record_id, action, user_id, url, old_data, new_data, created_at)
                VALUES (
                    '{$table}',
                    OLD.`{$primaryKeyColumn}`,
                    'delete',
                    @current_user_id,
                    @current_url,
                    {$oldJsonObject},
                    NULL,
                    NOW()
                );
            END;
        ");
    }
}
