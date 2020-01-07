<?php

namespace Connehito\CakeSentry\Error;

use Cake\Core\InstanceConfigTrait;
use Cake\Error\PHP7ErrorException;
use Cake\Log\Log;
use Connehito\CakeSentry\Http\Client;
use ErrorException;
use Exception;

trait SentryErrorHandlerTrait
{
    use InstanceConfigTrait;

    /* @var Client */
    protected $client;
}
