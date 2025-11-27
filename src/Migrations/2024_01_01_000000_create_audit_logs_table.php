<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Migration to create the audit_logs table.
 * 
 * This table stores all database change history including:
 * - What table and record changed
 * - What action was performed (insert, update, delete)
 * - Who made the change and from where
 * - The old and new data states
 */
class CreateAuditLogsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('super_audit_logs', function (Blueprint $table) {
            $table->id();
            $table->string('table_name')->index();
            $table->string('record_id')->index();
            $table->string('action'); // 'insert', 'update', 'delete'
            $table->unsignedBigInteger('user_id')->nullable()->index();
            $table->text('url')->nullable();
            $table->json('old_data')->nullable();
            $table->json('new_data')->nullable();
            $table->timestamps();

            // Composite index for common queries
            $table->index(['table_name', 'record_id']);
            $table->index(['table_name', 'action']);
            $table->index(['user_id', 'created_at']);
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('super_audit_logs');
    }
}
