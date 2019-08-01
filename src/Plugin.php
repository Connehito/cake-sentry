<?php

namespace Connehito\CakeSentry;

use Cake\Core\BasePlugin;
use Cake\Error\Middleware\ErrorHandlerMiddleware as CakeErrorHandlerMiddleware;
use Connehito\CakeSentry\Error\Middleware\ErrorHandlerMiddleware;

class Plugin extends BasePlugin
{
    /**
     * {@inheritDoc}
     */
    public function middleware($middleware)
    {
        $middleware = parent::middleware($middleware);
        $middleware->insertAfter(
            CakeErrorHandlerMiddleware::class,
            new ErrorHandlerMiddleware()
        );

        return $middleware;
    }
}
