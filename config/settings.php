<?php

$isDev = getenv('APP_ENV') === 'dev';

return [
    'settings' => [
        'displayErrorDetails' => $isDev,
        'logErrors' => true,
        'logErrorDetails' => $isDev,
        'logger' => [
            'name' => 'app',
            'path' => __DIR__ . '/../logs/app.log',
            'level' => $isDev ? \Monolog\Logger::DEBUG : \Monolog\Logger::WARNING
        ],
        'redis' => [
            'host' => getenv('REDIS_HOST'),
            'port' => getenv('REDIS_PORT'),
            'password' => getenv('REDIS_PASSWORD'),
            'prefix' => $isDev ? 'dev:' : 'prod:',
            'database' => $isDev ? 1 : 0
        ]
    ]
]; 