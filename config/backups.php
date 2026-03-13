<?php

return [
    'connections' => [
        'Hostinger' => 'sqlsrv',
        'Planejamento' => 'sqlsrv2',
        'Kinghost' => 'sqlsrv3',
    ],

    'pending_stale_hours' => (int) env('HEALTH_CONSULT_PENDING_STALE_HOURS', 36),

    'force_backup_enabled' => filter_var(env('HEALTH_CONSULT_FORCE_ENABLED', false), FILTER_VALIDATE_BOOL),

    'force_backup_dir' => env('HEALTH_CONSULT_FORCE_BACKUP_DIR', ''),
];
