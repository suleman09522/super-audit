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

];
