<?php
declare(strict_types=1);

namespace Connehito\CakeSentry\Error;

use Cake\Error\ErrorHandler as CakeErrorHandler;

class ErrorHandler extends CakeErrorHandler
{
    use ErrorHandlerTrait;
}
