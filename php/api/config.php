<?php

// Basic configuration (no frameworks)
// Use environment variables if available, otherwise defaults for local dev

return [
    'db' => [
        'dsn' => getenv('DB_DSN') ?: 'pgsql:host=127.0.0.1;port=5432;dbname=amp',
        'user' => getenv('DB_USER') ?: 'postgres',
        'pass' => getenv('DB_PASS') ?: 'postgres',
    ],
    'dev' => [
        'mode' => getenv('DEV_MODE') ?: '0',
    ],
    'jwt' => [
        'secret' => getenv('JWT_SECRET') ?: 'change_this_secret',
        'issuer' => 'amp',
        'audience' => 'amp-clients',
        'ttl_seconds' => 3600,
    ],
];


