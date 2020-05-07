<?php

namespace Connehito\CakeSentry\Error;

use Cake\Error\ConsoleErrorHandler as CakeConsoleErrorHandler;

class ConsoleErrorHandler extends CakeConsoleErrorHandler
{
    use SentryErrorHandlerTrait;
}
