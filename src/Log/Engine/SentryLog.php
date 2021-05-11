<?php
declare(strict_types=1);

namespace Connehito\CakeSentry\Log\Engine;

use Cake\Log\Engine\BaseLog;
use Connehito\CakeSentry\Http\Client;

class SentryLog extends BaseLog
{
    /* @var Client */
    protected $client;

    /**
     * @inheritDoc
     */
    public function __construct(array $config = [])
    {
        parent::__construct($config);
        $config = $this->getConfig();

        $client = new Client($config);
        $this->client = $client;
    }

    /**
     * @inheritDoc
     */
    public function log($level, $message, array $context = [])
    {
        $context['stackTrace'] = debug_backtrace();
        $this->client->capture($level, $message, $context);
    }
}
