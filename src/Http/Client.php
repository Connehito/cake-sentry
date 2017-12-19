<?php
namespace Connehito\CakeSentry\Http;

use Cake\Core\Configure;
use Cake\Core\InstanceConfigTrait;
use Cake\Error\PHP7ErrorException;
use Cake\Event\Event;
use Cake\Event\EventDispatcherTrait;
use Cake\Http\ServerRequestFactory;
use Cake\Routing\Router;
use Cake\Utility\Hash;
use InvalidArgumentException;
use Psr\Http\Message\ServerRequestInterface;
use Raven_Client;
use ReflectionMethod;

class Client
{
    use EventDispatcherTrait;
    use InstanceConfigTrait;

    /* @var array default instance config */
    protected $_defaultConfig = [];

    /* @var \Raven_Client */
    private $raven;

    /* @var \Psr\Http\Message\ServerRequestInterface */
    private $request;

    /**
     * Client constructor.
     *
     * @param array $config config for uses Sentry
     * @param \Psr\Http\Message\ServerRequestInterface $request request context message
     */
    public function __construct(array $config, ServerRequestInterface $request = null)
    {
        $this->setConfig($config);
        if (self::isHttpRequest()) {
            $this->setRequest($request);
        }
        $this->setupClient();
    }

    /**
     * Set context RequestMessage.
     *
     * @param null|\Psr\Http\Message\ServerRequestInterface $request if null, factory from global
     * @return void
     */
    private function setRequest($request)
    {
        if ($request && !($request instanceof ServerRequestInterface)) {
            throw new InvalidArgumentException('Request must be ServerRequestInterface');
        } elseif (!$request) {
            $request = Router::getRequest(true) ?: ServerRequestFactory::fromGlobals();
        }
        $this->request = $request;
    }

    /**
     * Set context RequestMessage.
     *
     * @return null|\Psr\Http\Message\ServerRequestInterface contextual request
     */
    public function getRequest()
    {
        return $this->request;
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
    public function capture($level, string $message, array $context)
    {
        $event = new Event('CakeSentry.Client.beforeCapture', $this, $context);
        $data = (array)$this->getEventManager()->dispatch($event)->getResult();

        $exception = Hash::get($context, 'exception');
        if ($exception) {
            if ($exception instanceof PHP7ErrorException) {
                $exception = $exception->getError();
            }
            $this->raven->captureException($exception, $data);
        } else {
            $stack = array_slice(debug_backtrace(), 3);
            $data += $context;
            if (!isset($data['level'])) {
                $data['level'] = $level;
            }
            foreach (['file', 'line'] as $stackField) {
                if (!isset($data[$stackField])) {
                    $data[$stackField] = $stack[0][$stackField];
                }
            }
            $data = array_filter($data, function ($val) {
                return !is_null($val);
            });
            $this->raven->captureMessage($message, [], $data, $stack);
        }

        if ($this->raven->getLastError() || $this->raven->getLastSentryError()) {
            $event = new Event('CakeSentry.Client.captureError', $this, $data);
            $this->getEventManager()->dispatch($event)->getResult();
        }
    }

    /**
     * Accessor for Raven_Client instance.
     *
     * @return Raven_Client
     */
    public function getRaven()
    {
        return $this->raven;
    }

    /**
     * Construct Raven_Client and inject config.
     *
     * @return void
     */
    private function setupClient()
    {
        $config = (array)Configure::read('Sentry');
        $dsn = Hash::get($config, 'dsn');
        if (!$dsn) {
            throw new InvalidArgumentException('Sentry DSN not provided.');
        }
        $options = (array)Hash::get($config, 'options');
        if (!Hash::get($options, 'send_callback')) {
            $options['send_callback'] = function () {
                $event = new Event('CakeSentry.Client.afterCapture', $this, func_get_args());
                $this->getEventManager()->dispatch($event)->getResult();
            };
        }
        $raven = new Raven_Client($dsn, $options);

        $this->raven = $raven;
    }

    /**
     * Detect Http request or not.
     * (Delegate Raven_Client private logic.)
     *
     * @return bool
     */
    private static function isHttpRequest()
    {
        $isHttpRequest = new ReflectionMethod(Raven_Client::class, 'is_http_request');
        $isHttpRequest->setAccessible(true);

        return $isHttpRequest->invoke(null);
    }
}
