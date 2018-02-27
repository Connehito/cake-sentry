<?php
namespace Connehito\CakeSentry;

use Cake\Core\Configure;
use Cake\Error\Middleware\ErrorHandlerMiddleware as CakeErrorHandlerMiddleware;
use Cake\Event\EventManager;
use Cake\Log\Log;
use Connehito\CakeSentry\Error\ConsoleErrorHandler;
use Connehito\CakeSentry\Error\ErrorHandler;
use Connehito\CakeSentry\Error\Middleware\ErrorHandlerMiddleware;
use Connehito\CakeSentry\Log\Engine\SentryLog;
use LogicException;

$isCli = PHP_SAPI === 'cli';
if ($isCli) {
    (new ConsoleErrorHandler(Configure::read('Error')))->register();
} else {
    (new ErrorHandler(Configure::read('Error')))->register();
}

$errorLogConfig = Log::getConfig('error');
$errorLogConfig['className'] = SentryLog::class;
Log::drop('error');
Log::setConfig('error', $errorLogConfig);

$appClass = Configure::read('App.namespace') . '\Application';
if (class_exists($appClass)) {
    EventManager::instance()->on('Server.buildMiddleware', function ($event, $queue) {
        /* @var \Cake\Http\MiddlewareQueue $queue */
        $middleware = new ErrorHandlerMiddleware();
        try {
            $queue->insertAfter(CakeErrorHandlerMiddleware::class, $middleware);
        } catch (LogicException $e) {
            $queue->prepend($middleware);
        }
    });
}
