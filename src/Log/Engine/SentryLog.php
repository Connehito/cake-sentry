<?php

namespace Connehito\CakeSentry\Log\Engine;

use Cake\Log\Engine\BaseLog;
use Connehito\CakeSentry\Http\Client;

class SentryLog extends BaseLog
{
    /* @var Client */
    protected $client;

    /**
     * {@inheritdoc}
     */
    public function __construct(array $config = [])
    {
        parent::__construct($config);
        $config = $this->getConfig();

        $client = new Client($config);
        $this->client = $client;
    }

    /**
     * {@inheritdoc}
     */
    public function log($level, $message, array $context = [])
    {
        $this->client->capture($level, $message, $context);
    }
}
