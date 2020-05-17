<?php
declare(strict_types=1);

namespace Connehito\CakeSentry\Error;

use Cake\Error\ErrorLogger as CakeErrorLogger;
use Cake\Log\Log;
use Psr\Http\Message\ServerRequestInterface;
use Throwable;

class ErrorLogger extends CakeErrorLogger
{

    /**
     * {@inheritDoc}
     *
     * It is basically the same as the logic of `Cake\Error\ErrorLogger::log()` method.
     * As a difference, this method passes an instance of request/exception to Log::error() as a context.
     */
    public function log(Throwable $exception, ?ServerRequestInterface $request = null): bool
    {
        foreach ($this->getConfig('skipLog') as $class) {
            if ($exception instanceof $class) {
                return false;
            }
        }

        $message = $this->getMessage($exception);

        if ($request !== null) {
            $message .= $this->getRequestContext($request);
        }

        $message .= "\n\n";

        return Log::error($message, compact('exception', 'request'));
    }
}
