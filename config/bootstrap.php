<?php
namespace Connehito\CakeSentry;

use Cake\Core\Configure;
use Cake\Error\ErrorTrap;
use Cake\Error\ExceptionTrap;
use Connehito\CakeSentry\Error\SentryErrorLogger;

Configure::write('Error.logger', SentryErrorLogger::class);

(new ErrorTrap(Configure::read('Error')))->register();
(new ExceptionTrap(Configure::read('Error')))->register();
