<?php

namespace Connehito\CakeSentry\Http;

use Cake\Core\Configure;
use Cake\Core\InstanceConfigTrait;
use Cake\Event\Event;
use Cake\Event\EventDispatcherTrait;
use Cake\Utility\Hash;
use Psr\Http\Message\ServerRequestInterface;
use RuntimeException;
use Sentry\Breadcrumb;
use Sentry\SentrySdk;
use Sentry\Severity;
use Sentry\State\Hub;
use function Sentry\init;

class Client
{
    use EventDispatcherTrait;
    use InstanceConfigTrait;

    /* @var array default instance config */
    protected $_defaultConfig = [];

    /* @var Hub */
    protected $hub;

    /* @var ServerRequestInterface */
    protected $request;

    /**
     * Client constructor.
     *
     * @param array $config config for uses Sentry
     */
    public function __construct(array $config)
    {
        $this->setConfig($config);
        $this->setupClient();
    }

    /**
     * Accessor for current hub
     * @return Hub
     */
    public function getHub(): Hub
    {
        return $this->hub;
    }

    /**
     * Capture exception for sentry.
     *
     * @param mixed $level error level
     * @param string $message error message
     * @param array $context subject
     *
     * @return void
     */
    public function capture($level, string $message, array $context): void
    {
        $event = new Event('CakeSentry.Client.beforeCapture', $this, $context);
        $this->getEventManager()->dispatch($event);

        $exception = Hash::get($context, 'exception');
        if ($exception) {
            $lastEventId = $this->hub->captureException($exception);
        } else {
            $stacks = array_slice(debug_backtrace(DEBUG_BACKTRACE_PROVIDE_OBJECT), 3);
            foreach ($stacks as $stack) {
                $method = isset($stack['class']) ? "{$stack['class']}::{$stack['function']}" : $stack['function'];
                unset($stack['class']);
                unset($stack['function']);
                $this->hub->addBreadcrumb(new Breadcrumb(
                    $level,
                    Breadcrumb::TYPE_ERROR,
                    'method',
                    $method,
                    $stack
                ));
            }
            if (method_exists(Severity::class, $level)) {
                $severity = (Severity::class . '::' . $level)();
            } else {
                $severity = Severity::fromError($level);
            }
            $lastEventId = $this->hub->captureMessage($message, $severity);
        }

        $context['lastEventId'] = $lastEventId;
        $event = new Event('CakeSentry.Client.afterCapture', $this, $context);
        $this->getEventManager()->dispatch($event);
    }

    /**
     * Construct Raven_Client and inject config.
     *
     * @return void
     */
    protected function setupClient(): void
    {
        $config = (array)Configure::read('Sentry');
        if (!Hash::check($config, 'dsn')) {
            throw new RuntimeException('Sentry DSN not provided.');
        }

        init($config);
        $this->hub = SentrySdk::getCurrentHub();

        $event = new Event('CakeSentry.Client.afterSetup', $this);
        $this->getEventManager()->dispatch($event);
    }
}
