<?php

namespace Connehito\CakeSentry;

use Cake\Core\BasePlugin;
use Cake\Core\Configure;
use Cake\Error\Middleware\ErrorHandlerMiddleware as CakeErrorHandlerMiddleware;
use Cake\Event\EventManager;
use Cake\Http\MiddlewareQueue;
use Connehito\CakeSentry\Error\Middleware\ErrorHandlerMiddleware;
use LogicException;

class Plugin extends BasePlugin
{
    /**
     * {@inheritDoc}
     */
    public function middleware($middleware)
    {
        $middleware = parent::middleware($middleware);

        $appClass = Configure::read('App.namespace') . '\Application';
        if (class_exists($appClass)) {
            EventManager::instance()->on('Server.buildMiddleware', function ($event, $queue) {
                /* @var MiddlewareQueue $queue */
                $middleware = new ErrorHandlerMiddleware();
                try {
                    $queue->insertAfter(CakeErrorHandlerMiddleware::class, $middleware);
                } catch (LogicException $e) {
                    $queue->prepend($middleware);
                }
            });
        }

        return $middleware;
    }
}
