<?php

use App\Models\ActivityLog;
use Spatie\Activitylog\Actions\CleanActivityLogAction;
use Spatie\Activitylog\Actions\LogActivityAction;

return [

    /*
     * If set to false, no activities will be saved to the database.
     */
    'enabled' => env('ACTIVITYLOG_ENABLED', env('ACTIVITY_LOGGER_ENABLED', true)),

    /*
     * When the clean-command is executed, all recording activities older than
     * the number of days specified here will be deleted.
     */
    'clean_after_days' => 365,

    /*
     * If no log name is passed to the activity() helper
     * we use this default log name.
     */
    'default_log_name' => 'default',

    /*
     * You can specify an auth driver here that gets user models.
     * If this is null we'll use the current Laravel auth driver.
     */
    'default_auth_driver' => null,

    /*
     * If set to true, the subject returns soft deleted models.
     */
    'include_soft_deleted_subjects' => false,

    /*
     * This model will be used to log activity.
     * It should implement the Spatie\Activitylog\Contracts\Activity interface
     * and extend Illuminate\Database\Eloquent\Model.
     */
    'activity_model' => ActivityLog::class,

    'default_except_attributes' => [],

    'actions' => [
        'log_activity' => LogActivityAction::class,
        'clean_log' => CleanActivityLogAction::class,
    ],

    /*
     * Retained for immutable v4 migrations and the application's ActivityLog
     * model. Activitylog v5 no longer reads this setting itself.
     */
    'table_name' => env('ACTIVITY_LOGGER_TABLE_NAME', 'activity_log'),

    /*
     * Retained for immutable v4 migrations and the application's ActivityLog
     * model. If unset, Laravel's default database connection is used.
     */
    'database_connection' => env('ACTIVITY_LOGGER_DB_CONNECTION'),
];
