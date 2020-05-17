<?php

namespace Connehito\CakeSentry\Error;

use Cake\Core\InstanceConfigTrait;
use Connehito\CakeSentry\Http\Client;
use ErrorException;

trait SentryErrorHandlerTrait
{
    /**
     * Change error messages into ErrorException and write exception log.
     *
     * @param string $level The level name of the log.
     * @param array $data Array of error data.
     * @return bool
     */
    protected function _logError($level, $data): bool
    {
        $error = new ErrorException($data['description'], 0, $data['code'], $data['file'], $data['line']);

        return $this->logException($error);
    }
}
