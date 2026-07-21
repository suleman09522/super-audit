<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Migration to add the payload column to super_audit_logs.
 *
 * This stores the HTTP request payload (request->all()) so you can see
 * exactly what data was received when a change was made.
 */
class AddPayloadToAuditLogsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('super_audit_logs', function (Blueprint $table) {
            if (!Schema::hasColumn('super_audit_logs', 'payload')) {
                // Add payload column after url
                $table->json('payload')->nullable()->after('url');
            }
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('super_audit_logs', function (Blueprint $table) {
            if (Schema::hasColumn('super_audit_logs', 'payload')) {
                $table->dropColumn('payload');
            }
        });
    }
}
