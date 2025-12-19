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

        // setup-triggers: Merge excluded tables from config
        $configExcluded = config('super-audit.excluded_tables');
        
        // Fallback check for underscore syntax
        if (empty($configExcluded)) {
            $configExcluded = config('super_audit.excluded_tables');
        }

        // Ensure it's an array
        if (!is_array($configExcluded)) {
            $configExcluded = [];
        }

        $this->skipTables = array_merge($this->skipTables, $configExcluded);

        // Debug info to verify config is loaded
        if (!empty($configExcluded)) {
            $this->comment('✓ Loaded custom excluded tables: ' . implode(', ', $configExcluded));
        } else {
            // Check if user has published config
            if (file_exists(base_path('config/super-audit.php'))) {
                 $this->warn('! Config file found at config/super-audit.php but excluded_tables is empty or not read.');
                 $this->line('  Current config("super-audit") dump: ' . json_encode(config('super-audit')));
            } else {
                 $this->comment('! No custom excluded tables found. (No config/super-audit.php found or array is empty)');
            }
        }

        $tables = $this->getTables();
        $successCount = 0;
        $skippedCount = 0;
        $errorCount = 0;

        foreach ($tables as $table) {
            // Skip excluded tables (case-insensitive)
            if (in_array(strtolower($table), array_map('strtolower', $this->skipTables))) {
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
     *
     * @return array
     */
    protected function getTables()
    {
        $database = DB::getDatabaseName();
        $tables = DB::select("
            SELECT TABLE_NAME 
            FROM INFORMATION_SCHEMA.TABLES 
            WHERE TABLE_SCHEMA = ? 
            AND TABLE_TYPE = 'BASE TABLE'
        ", [$database]);
        
        return array_map(function($table) {
            return $table->TABLE_NAME;
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
     * Build SQL logic to store only changed fields in audit log.
     *
     * @param array $columns
     * @param string $table
     * @param string $primaryKeyColumn
     * @return string
     */
    protected function buildChangedFieldsLogic($columns, $table, $primaryKeyColumn)
    {
        $oldJsonParts = [];
        $newJsonParts = [];
        
        foreach ($columns as $column) {
            $escapedColumn = str_replace("'", "\\'", $column);
            
            // Build condition to check if column changed
            // Need to handle NULL values properly (NULL != NULL comparison)
            $condition = "(OLD.`{$column}` IS NULL AND NEW.`{$column}` IS NOT NULL) OR " .
                        "(OLD.`{$column}` IS NOT NULL AND NEW.`{$column}` IS NULL) OR " .
                        "(OLD.`{$column}` != NEW.`{$column}`)";
            
            // For each changed column, add it to the JSON parts with a conditional
            $oldJsonParts[] = "IF({$condition}, '{$escapedColumn}', NULL), " .
                            "IF({$condition}, OLD.`{$column}`, NULL)";
            $newJsonParts[] = "IF({$condition}, '{$escapedColumn}', NULL), " .
                            "IF({$condition}, NEW.`{$column}`, NULL)";
        }
        
        // Build the complete INSERT statement with dynamic JSON objects
        // We'll use a stored procedure-like approach to build JSON only with changed fields
        $logic = "
                    SET @old_json = CONCAT('{', 
                        TRIM(BOTH ',' FROM CONCAT_WS(',',
                            " . $this->buildChangedFieldsConcatLogic($columns, 'OLD') . "
                        )),
                    '}');
                    
                    SET @new_json = CONCAT('{', 
                        TRIM(BOTH ',' FROM CONCAT_WS(',',
                            " . $this->buildChangedFieldsConcatLogic($columns, 'NEW') . "
                        )),
                    '}');
                    
                    -- Only log if there are actual changes
                    IF @old_json != '{}' AND @new_json != '{}' THEN
                        INSERT INTO super_audit_logs (table_name, record_id, action, user_id, url, old_data, new_data, created_at)
                        VALUES (
                            '{$table}',
                            NEW.`{$primaryKeyColumn}`,
                            'update',
                            @current_user_id,
                            @current_url,
                            @old_json,
                            @new_json,
                            NOW()
                        );
                    END IF;";
        
        return $logic;
    }
    
    /**
     * Build CONCAT_WS logic for changed fields detection.
     *
     * @param array $columns
     * @param string $prefix (OLD or NEW)
     * @return string
     */
    protected function buildChangedFieldsConcatLogic($columns, $prefix)
    {
        $parts = [];
        
        foreach ($columns as $column) {
            $escapedColumn = str_replace("'", "\\'", $column);
            
            // Build condition to check if column changed
            $condition = "(OLD.`{$column}` IS NULL AND NEW.`{$column}` IS NOT NULL) OR " .
                        "(OLD.`{$column}` IS NOT NULL AND NEW.`{$column}` IS NULL) OR " .
                        "(OLD.`{$column}` != NEW.`{$column}`)";
            
            // Build JSON key-value pair for this column if it changed
            $jsonPair = "CONCAT('\"', '{$escapedColumn}', '\":', " .
                       "COALESCE(JSON_QUOTE({$prefix}.`{$column}`), 'null'))";
            
            $parts[] = "IF({$condition}, {$jsonPair}, NULL)";
        }
        
        return implode(",\n                            ", $parts);
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
        
        // Get columns for change detection
        $columns = $this->getTableColumns($table);
        
        // Build the conditional JSON objects that only include changed fields
        $changedFieldsLogic = $this->buildChangedFieldsLogic($columns, $table, $primaryKeyColumn);

        DB::unprepared("
            CREATE TRIGGER {$triggerName}
            AFTER UPDATE ON `{$table}`
            FOR EACH ROW
            BEGIN
                -- Only insert if there's an actual change in data
                IF {$oldJsonObject} != {$newJsonObject} THEN
                    {$changedFieldsLogic}
                END IF;
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
