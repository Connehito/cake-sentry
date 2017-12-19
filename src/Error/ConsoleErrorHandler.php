<?php
namespace Connehito\CakeSentry\Error;

use Cake\Console\ConsoleErrorHandler as CakeConsoleErrorHandler;

class ConsoleErrorHandler extends CakeConsoleErrorHandler
{
    use SentryErrorHandlerTrait;
}
