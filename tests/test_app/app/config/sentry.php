<?php

use Cake\Http\Exception\NotFoundException;
use Sentry\Integration\IgnoreErrorsIntegration;

return [
    'Sentry' => [
        'dsn' => env('SENTRY_DSN'),
        'integrations' => [
            IgnoreErrorsIntegration::class => [
                'ignore_exceptions' => [
                    NotFoundException::class,
                ],
            ],
        ],
    ],
];
