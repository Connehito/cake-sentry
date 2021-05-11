<?php

use Cake\Http\Exception\NotFoundException;
use Sentry\Integration\IgnoreErrorsIntegration;

return [
    'Sentry' => [
        'dsn' => env('SENTRY_DSN'),
        'integrations' => [
            new IgnoreErrorsIntegration([
                'ignore_exceptions' => [
                    NotFoundException::class,
                ],
            ]),
        ],
    ],
];
