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
     * @param mixed $level error level
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
            $stacks = array_slice(debug_backtrace(DEBUG_BACKTRACE_PROVIDE_OBJECT), 3);
            if (is_string($level) && method_exists(Severity::class, $level)) {
                $severity = (Severity::class . '::' . $level)();
            } else {
                $severity = Severity::fromError($level);
            }
            $stackTrace = $this->stackTraceBuilder->buildFromBacktrace($stacks, $stacks[0]['file'], $stacks[0]['line']);

            $hint = new EventHint();
            $hint->extra = $context;
            $hint->stacktrace = $stackTrace;

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
}
