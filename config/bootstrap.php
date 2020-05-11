<?php
namespace Connehito\CakeSentry;

use Cake\Core\Configure;
use Cake\Log\Log;
use Connehito\CakeSentry\Error\ConsoleErrorHandler;
use Connehito\CakeSentry\Error\ErrorHandler;
use Connehito\CakeSentry\Error\ErrorLogger;
use Connehito\CakeSentry\Log\Engine\SentryLog;

$isCli = PHP_SAPI === 'cli';
if (!$isCli && strpos((env('argv')[0] ?? ''), '/phpunit') !== false) {
    $isCli = true;
}
if ($isCli) {
    (new ConsoleErrorHandler(Configure::read('Error', [])))->register();
} else {
    (new ErrorHandler(Configure::read('Error', [])))->register();
}

$errorLogConfig = Log::getConfig('error');
$errorLogConfig['className'] = SentryLog::class;
Log::drop('error');
Log::setConfig('error', $errorLogConfig);
Configure::write('Error.errorLogger', ErrorLogger::class);