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
     * Generate the error log message.
     *
     * @param Throwable $exception The exception to log a message for.
     * @param ServerRequestInterface|null $request The current request if available.
     * @return bool
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
