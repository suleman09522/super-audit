<?php

namespace SuperAudit\SuperAudit\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * DropAuditTriggers Command
 * 
 * Drops all Super Audit triggers from database tables.
 * Useful for cleanup, disabling auditing, or before rebuilding triggers.
 */
class DropAuditTriggers extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'audit:drop-triggers 
                            {--tables= : Comma-separated list of specific tables to drop triggers from}
                            {--force : Skip confirmation prompt}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Drop all Super Audit triggers from database tables';

    /**
     * Tables to exclude from trigger operations.
     *
     * @var array
     */
    protected $excludedTables = [
        'migrations',
        'super_audit_logs',
        'password_resets',
        'password_reset_tokens',
        'failed_jobs',
        'personal_access_tokens',
        'sessions',
    ];

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        // Get excluded tables from config
        $configExcluded = config('super-audit.excluded_tables', []);
        $this->excludedTables = array_merge($this->excludedTables, $configExcluded);

        // Get tables to process
        $tables = $this->option('tables') 
            ? explode(',', $this->option('tables')) 
            : $this->getTables();

        $triggerCount = 0;

        // Show warning if not forced
        if (!$this->option('force')) {
            if (!$this->confirm('This will drop all Super Audit triggers. Do you want to continue?')) {
                $this->info('Operation cancelled.');
                return 0;
            }
        }

        $this->info('Dropping audit triggers...');
        
        $bar = $this->output->createProgressBar(count($tables));
        $bar->start();

        foreach ($tables as $table) {
            $table = trim($table);

            if (in_array($table, $this->excludedTables)) {
                $bar->advance();
                continue;
            }

            try {
                $dropped = $this->dropTriggers($table);
                $triggerCount += $dropped;
            } catch (\Exception $e) {
                $this->newLine();
                $this->error("Failed to drop triggers for {$table}: " . $e->getMessage());
            }

            $bar->advance();
        }

        $bar->finish();
        $this->newLine(2);

        $this->info("Successfully dropped {$triggerCount} trigger(s).");
        
        if ($triggerCount > 0) {
            $this->newLine();
            $this->comment('💡 Tip: To rebuild triggers, run: php artisan audit:setup-triggers');
        }

        return 0;
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
     * @return int Number of triggers dropped
     */
    protected function dropTriggers($table)
    {
        $triggers = ['after_insert', 'after_update', 'after_delete'];
        $dropped = 0;
        
        foreach ($triggers as $trigger) {
            $triggerName = "{$trigger}_{$table}";
            
            try {
                // Check if trigger exists
                $exists = DB::select("
                    SELECT TRIGGER_NAME 
                    FROM INFORMATION_SCHEMA.TRIGGERS 
                    WHERE TRIGGER_SCHEMA = ? 
                    AND TRIGGER_NAME = ?
                ", [DB::getDatabaseName(), $triggerName]);

                if (!empty($exists)) {
                    DB::unprepared("DROP TRIGGER IF EXISTS {$triggerName}");
                    $dropped++;
                }
            } catch (\Exception $e) {
                // Trigger might not exist, continue
            }
        }

        return $dropped;
    }
}
