<?php
declare(strict_types=1);

namespace Connehito\CakeSentry\Http;

use Cake\Core\Configure;
use Cake\Core\InstanceConfigTrait;
use Cake\Error\PhpError;
use Cake\Event\Event;
use Cake\Event\EventDispatcherTrait;
use Cake\Utility\Hash;
use Psr\Http\Message\ServerRequestInterface;
use RuntimeException;
use Sentry\SentrySdk;
use Sentry\Severity;
use Sentry\State\HubInterface;
use Sentry\State\Scope;
use function Sentry\captureException;
use function Sentry\captureMessage;
use function Sentry\init;

class SentryClient
{
    use EventDispatcherTrait;
    use InstanceConfigTrait;

    /* @var array default instance config */
    protected $_defaultConfig = [
        'sentry' => [
            'prefixes' => [
                APP,
            ],
            'in_app_exclude' => [
                ROOT . DS . 'vendor' . DS,
            ],
        ],
    ];

    protected HubInterface $hub;

    /**
     * Client constructor.
     *
     * @param array $config config for uses Sentry
     */
    public function __construct(array $config)
    {
        $userConfig = Configure::read('Sentry');
        if ($userConfig) {
            $this->_defaultConfig['sentry'] = array_merge($this->_defaultConfig['sentry'], $userConfig);
        }
        $this->setConfig($config);
        $this->setupClient();
    }

    /**
     * Construct Raven_Client and inject config.
     *
     * @return void
     */
    protected function setupClient(): void
    {
        $config = $this->getConfig('sentry');
        if (!Hash::check($config, 'dsn')) {
            throw new RuntimeException('Sentry DSN not provided.');
        }

        init($config);
        $this->hub = SentrySdk::getCurrentHub();

        $event = new Event('CakeSentry.Client.afterSetup', $this);
        $this->getEventManager()->dispatch($event);
    }

    /**
     * Method responsible for passing on the exception to sentry
     *
     * @param \Throwable $exception The thrown exception
     * @param \Psr\Http\Message\ServerRequestInterface|null $request The associated request object if available
     * @param array|null $extras Extras passed down to the hub
     * @return void
     */
    public function captureException(
        \Throwable $exception,
        ?ServerRequestInterface $request = null,
        ?array $extras = null
    ) {
        $eventManager = $this->getEventManager();
        $event = new Event('CakeSentry.Client.beforeCapture', $this, compact('exception', 'request'));
        $eventManager->dispatch($event);

        if ($extras) {
            $this->hub->configureScope(function (Scope $scope) use ($extras) {
                $scope->setExtras($extras);
            });
        }

        $lastEventId = captureException($exception);
        $event = new Event('CakeSentry.Client.afterCapture', $this, compact('exception', 'request', 'lastEventId'));
        $eventManager->dispatch($event);
    }

    /**
     * Method responsible for passing on the error to sentry
     *
     * @param \Cake\Error\PhpError $error The error instance
     * @param \Psr\Http\Message\ServerRequestInterface|null $request The associated request object if available
     * @param array|null $extras Extras passed down to the hub
     * @return void
     */
    public function captureError(
        PhpError $error,
        ?ServerRequestInterface $request = null,
        ?array $extras = null
    ) {
        $eventManager = $this->getEventManager();
        $event = new Event('CakeSentry.Client.beforeCapture', $this, compact('error', 'request'));
        $eventManager->dispatch($event);

        if ($extras) {
            $this->hub->configureScope(function (Scope $scope) use ($extras) {
                $scope->setExtras($extras);
            });
        }

        $lastEventId = captureMessage(
            $error->getMessage(),
            Severity::fromError($error->getCode())
        );
        $event = new Event('CakeSentry.Client.afterCapture', $this, compact('error', 'request', 'lastEventId'));
        $eventManager->dispatch($event);
    }

    /**
     * Accessor for current hub
     *
     * @return \Sentry\State\HubInterface
     */
    public function getHub(): HubInterface
    {
        return $this->hub;
    }
}
