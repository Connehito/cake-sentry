<?php
declare(strict_types=1);

namespace Connehito\CakeSentry\Error;

use Cake\Error\ConsoleErrorHandler as CakeConsoleErrorHandler;

class ConsoleErrorHandler extends CakeConsoleErrorHandler
{
    use ErrorHandlerTrait;
}
