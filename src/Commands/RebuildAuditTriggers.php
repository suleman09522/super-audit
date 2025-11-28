<?php

namespace SuperAudit\SuperAudit\Commands;

use Illuminate\Console\Command;

/**
 * RebuildAuditTriggers Command
 * 
 * Drops all existing triggers and recreates them.
 * Useful after database schema changes or adding new tables.
 */
class RebuildAuditTriggers extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'audit:rebuild-triggers 
                            {--tables= : Comma-separated list of specific tables to rebuild triggers for}
                            {--force : Skip confirmation prompt}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Drop and recreate all Super Audit triggers (useful after schema changes)';

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        $this->info('🔄 Rebuilding Super Audit triggers...');
        $this->newLine();

        // Prepare options for sub-commands
        $dropOptions = ['--force' => true];
        $setupOptions = [];

        if ($this->option('tables')) {
            $dropOptions['--tables'] = $this->option('tables');
            $setupOptions['--tables'] = $this->option('tables');
        }

        // Ask for confirmation if not forced
        if (!$this->option('force')) {
            if (!$this->confirm('This will drop and recreate all triggers. Continue?')) {
                $this->info('Operation cancelled.');
                return 0;
            }
        }

        $this->newLine();
        $this->line('━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━');
        $this->info('Step 1/2: Dropping existing triggers...');
        $this->line('━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━');
        $this->newLine();

        // Step 1: Drop existing triggers
        $dropResult = $this->call('audit:drop-triggers', $dropOptions);

        if ($dropResult !== 0) {
            $this->error('Failed to drop triggers. Aborting rebuild.');
            return 1;
        }

        $this->newLine();
        $this->line('━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━');
        $this->info('Step 2/2: Creating new triggers...');
        $this->line('━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━');
        $this->newLine();

        // Step 2: Setup new triggers
        $setupResult = $this->call('audit:setup-triggers', $setupOptions);

        if ($setupResult !== 0) {
            $this->error('Failed to create triggers.');
            return 1;
        }

        $this->newLine();
        $this->line('━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━');
        $this->info('✅ Triggers rebuilt successfully!');
        $this->line('━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━');
        $this->newLine();

        $this->comment('Your database audit triggers are now up to date.');
        
        return 0;
    }
}
