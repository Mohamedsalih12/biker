<?php

/*
 * Retained only so the historical RBAC-table migration can run on fresh
 * installations. The permission package is no longer installed or used.
 */
return [
    'teams' => false,
    'table_names' => [
        'roles' => 'roles',
        'permissions' => 'permissions',
        'model_has_permissions' => 'model_has_permissions',
        'model_has_roles' => 'model_has_roles',
        'role_has_permissions' => 'role_has_permissions',
    ],
    'column_names' => [
        'role_pivot_key' => 'role_id',
        'permission_pivot_key' => 'permission_id',
        'model_morph_key' => 'model_id',
        'team_foreign_key' => 'team_id',
    ],
    'cache' => [
        'store' => 'default',
        'key' => 'spatie.permission.cache',
    ],
];
