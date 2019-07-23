<?php

namespace Connehito\CakeSentry;

use Cake\Core\BasePlugin;
use Cake\Error\Middleware\ErrorHandlerMiddleware as CakeErrorHandlerMiddleware;
use Cake\Event\EventManager;
use Cake\Http\MiddlewareQueue;
use Connehito\CakeSentry\Error\Middleware\ErrorHandlerMiddleware;

class Plugin extends BasePlugin
{
    /**
     * {@inheritDoc}
     */
    public function middleware($middleware)
    {
        $middleware = parent::middleware($middleware);

        EventManager::instance()->on('Server.buildMiddleware', function ($event, $queue) {
            /* @var MiddlewareQueue $queue */
            $middleware = new ErrorHandlerMiddleware();
            $queue->insertAfter(CakeErrorHandlerMiddleware::class, $middleware);
        });

        return $middleware;
    }
}
