<?php
declare(strict_types=1);

namespace Connehito\CakeSentry\Http;

use Cake\Core\Configure;
use Cake\Core\InstanceConfigTrait;
use Cake\Event\Event;
use Cake\Event\EventDispatcherTrait;
use Cake\Utility\Hash;
use RuntimeException;
use Sentry\EventHint;
use Sentry\SentrySdk;
use Sentry\Serializer\RepresentationSerializer;
use Sentry\Severity;
use Sentry\StacktraceBuilder;
use Sentry\State\Hub;
use function Sentry\init;

class Client
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

    /* @var Hub */
    protected $hub;

    /* @var StacktraceBuilder */
    protected $stackTraceBuilder;

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
        $this->stackTraceBuilder = new StacktraceBuilder(
            $this->getHub()->getClient()->getOptions(),
            new RepresentationSerializer($this->getHub()->getClient()->getOptions())
        );
    }

    /**
     * Accessor for current hub
     *
     * @return \Sentry\State\Hub
     */
    public function getHub(): Hub
    {
        return $this->hub;
    }

    /**
     * Capture exception for sentry.
     *
     * @param string|int $level error level
     * @param string $message error message
     * @param array $context subject
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
            $hint = new EventHint();

            $stackTrace = $context['stackTrace'] ?? false;
            if ($stackTrace) {
                $hint->stacktrace = $this->stackTraceBuilder->buildFromBacktrace(
                    $stackTrace,
                    $stackTrace[0]['file'],
                    $stackTrace[0]['line']
                );
                unset($context['stackTrace']);
            }
            $this->hub->configureScope(function (\Sentry\State\Scope $scope) use ($context) {
                $scope->setExtras($context);
            });

            $severity = $this->convertLevelToSeverity($level);
            $lastEventId = $this->hub->captureMessage($message, $severity, $hint);
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
     * Convert error info to severity
     *
     * @param string|int $level Error name or level(int)
     * @return \Sentry\Severity
     */
    private function convertLevelToSeverity($level): Severity
    {
        if (is_string($level) && method_exists(Severity::class, $level)) {
            return (Severity::class . '::' . $level)();
        }

        return Severity::fromError($level);
    }
}
