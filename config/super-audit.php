<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Excluded Tables
    |--------------------------------------------------------------------------
    |
    | Specify tables that should be excluded from audit logging.
    | These tables will not have triggers created for them.
    |
    */
    'excluded_tables' => [
        // Add your custom excluded tables here
        // 'example_table',
    ],

    /*
    |--------------------------------------------------------------------------
    | Auto Register Middleware
    |--------------------------------------------------------------------------
    |
    | Automatically register the SetAuditVariables middleware to web and api
    | middleware groups. Set to false if you want to manually register it.
    |
    */
    'auto_register_middleware' => true,

    /*
    |--------------------------------------------------------------------------
    | User Model
    |--------------------------------------------------------------------------
    |
    | The user model to use for the user relationship on AuditLog.
    | Defaults to Laravel's default user model.
    |
    */
    'user_model' => null, // null means use config('auth.providers.users.model')

    /*
    |--------------------------------------------------------------------------
    | Auto Recreate Triggers on Migration
    |--------------------------------------------------------------------------
    |
    | When set to true, the package will automatically attempt to recreate
    | audit triggers for any table that is modified during a migration.
    |
    */
    'auto_recreate_triggers_on_migration' => true,

    /*
    |--------------------------------------------------------------------------
    | Store Request Payload
    |--------------------------------------------------------------------------
    |
    | When set to true, HTTP request payload (request->all()) will be saved in
    | super_audit_logs to see received data. Sensitive fields can be masked.
    |
    */
    'store_request_payload' => true,

    /*
    |--------------------------------------------------------------------------
    | Hidden Payload Fields
    |--------------------------------------------------------------------------
    |
    | Fields that should be masked or omitted from stored request payloads.
    |
    */
    'hidden_payload_fields' => [
        'password',
        'password_confirmation',
        'secret',
        '_token',
    ],

];
