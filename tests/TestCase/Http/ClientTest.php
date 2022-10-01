<?php
declare(strict_types=1);

namespace Connehito\CakeSentry\Test\TestCase\Http;

use Cake\Core\Configure;
use Cake\Error\PhpError;
use Cake\Event\Event;
use Cake\Event\EventManager;
use Cake\TestSuite\TestCase;
use Connehito\CakeSentry\Http\SentryClient;
use Exception;
use Prophecy\Argument;
use Prophecy\PhpUnit\ProphecyTrait;
use RuntimeException;
use Sentry\ClientInterface;
use Sentry\EventId;
use Sentry\Options;
use Sentry\Severity;
use Sentry\State\Hub;
use Sentry\State\Scope;

final class ClientTest extends TestCase
{
    use ProphecyTrait;

    /**
     * @inheritDoc
     */
    public function setUp(): void
    {
        parent::setUp();

        Configure::write('Sentry.dsn', 'https://yourtoken@example.com/yourproject/1');
    }

    /**
     * Check constructor sets Hub instance
     */
    public function testSetupClient(): void
    {
        $subject = new SentryClient([]);

        $this->assertInstanceOf(Hub::class, $subject->getHub());
    }

    /**
     * Check the configuration values are merged into the default-config.
     */
    public function testSetUpClientMergeConfig(): void
    {
        $userConfig = [
            'dsn' => false,
            'in_app_exclude' => ['/app/vendor', '/app/tmp',],
            'server_name' => 'test-server',
        ];

        Configure::write('Sentry', $userConfig);
        $subject = new SentryClient([]);

        $this->assertSame([APP], $subject->getConfig('sentry.prefixes'), 'Default value not applied');
        $this->assertSame($userConfig['in_app_exclude'], $subject->getConfig('sentry.in_app_exclude'), 'Default value is not overwritten');
        $this->assertSame(false, $subject->getConfig('sentry.dsn'), 'Set value is not addes');
    }

    /**
     * Check constructor throws exception unless dsn is given
     */
    public function testSetupClientNotHasDsn(): void
    {
        Configure::delete('Sentry.dsn');
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Sentry DSN not provided.');

        new SentryClient([]);
    }

    /**
     * Check constructor passes options to sentry client
     */
    public function testSetupClientSetOptions(): void
    {
        Configure::write('Sentry.server_name', 'test-server');

        $subject = new SentryClient([]);
        $options = $subject->getHub()->getClient()->getOptions();

        $this->assertSame('test-server', $options->getServerName());
    }

    /**
     * Check constructor fill before_send option
     */
    public function testSetupClientSetSendCallback(): void
    {
        $callback = function (\Sentry\Event $event, ?\Sentry\EventHint $hint) {
            return 'this is user callback';
        };
        Configure::write('Sentry.before_send', $callback);

        $subject = new SentryClient([]);
        $actual = $subject
            ->getHub()
            ->getClient()
            ->getOptions()
            ->getBeforeSendCallback();

        $this->assertSame(
            $callback(\Sentry\Event::createEvent(), null),
            $actual(\Sentry\Event::createEvent(), null)
        );
    }

    /**
     * Check constructor dispatch event Client.afterSetup
     */
    public function testSetupClientDispatchAfterSetup(): void
    {
        $called = false;
        EventManager::instance()->on(
            'CakeSentry.Client.afterSetup',
            function () use (&$called) {
                $called = true;
            }
        );

        new SentryClient([]);

        $this->assertTrue($called);
    }

    /**
     * Test capture exception
     */
    public function testCaptureException(): void
    {
        $subject = new SentryClient([]);
        $sentryClientP = $this->prophesize(ClientInterface::class);
        $subject->getHub()->bindClient($sentryClientP->reveal());

        $exception = new RuntimeException('something wrong.');
        $subject->captureException($exception);

        $sentryClientP
            ->captureException($exception, Argument::type(Scope::class), null)
            ->shouldHaveBeenCalledOnce();
    }

    /**
     * Test capture error
     */
    public function testCaptureError(): void
    {
        $subject = new SentryClient([]);
        $sentryClientP = $this->prophesize(ClientInterface::class);
        $subject->getHub()->bindClient($sentryClientP->reveal());

        $error = new PhpError(E_USER_WARNING, 'something wrong.');
        $subject->captureError($error);

        $sentryClientP
            ->captureMessage(
                $error->getMessage(),
                Argument::type(Severity::class),
                Argument::type(Scope::class),
                null
            )
            ->shouldHaveBeenCalledOnce();
    }

    /**
     * Test capture other than exception
     *
     * @return array // FIXME: In fact array<string,MethodProphecy[]>, but getMethodProphecies declare as MethodProphecy[]
     */
    public function testCaptureNotHavingException(): array
    {
        $subject = new SentryClient([]);
        $sentryClientP = $this->prophesize(ClientInterface::class);
        $sentryClientP->getOptions()->willReturn(new Options());

        $phpError = new PhpError(E_WARNING, 'Some error');
        $sentryClientP
            ->captureMessage(
                'Some error',
                Severity::fromError(E_WARNING),
                Argument::type(Scope::class),
                null
            )
            ->shouldBeCalledOnce()
            ->willReturn(EventId::generate());
            // NOTE:
            // This itself is not of interest for the test case,
            // but for ProphecyMock's technical reasons, the return-value needs to be a real `EvnetId`

        $subject->getHub()->bindClient($sentryClientP->reveal());

        $subject->captureError($phpError);

        return $sentryClientP->getMethodProphecies();
    }

    /**
     * Test capture exception pass cakephp-log's context as additional data
     */
    public function testCaptureExceptionWithAdditionalData(): void
    {
        $callback = function (\Sentry\Event $event, ?\Sentry\EventHint $hint) use (&$actualEvent) {
            $actualEvent = $event;
        };

        $userConfig = [
            'dsn' => false,
            'before_send' => $callback,
        ];

        Configure::write('Sentry', $userConfig);
        $subject = new SentryClient([]);

        $extras = ['this is' => 'additional'];
        $exception = new RuntimeException('Some error');
        $subject->captureException($exception, null, $extras);

        $this->assertSame($extras, $actualEvent->getExtra());
    }

    /**
     * Test capture error pass cakephp-log's context as additional data
     */
    public function testCaptureErrorWithAdditionalData(): void
    {
        $callback = function (\Sentry\Event $event, ?\Sentry\EventHint $hint) use (&$actualEvent) {
            $actualEvent = $event;
        };

        $userConfig = [
            'dsn' => false,
            'before_send' => $callback,
        ];

        Configure::write('Sentry', $userConfig);
        $subject = new SentryClient([]);

        $extras = ['this is' => 'additional'];
        $phpError = new PhpError(E_USER_WARNING, 'Some error');
        $subject->captureError($phpError, null, $extras);

        $this->assertSame($extras, $actualEvent->getExtra());
    }

    /**
     * Check capture dispatch before exception capture
     */
    public function testCaptureDispatchBeforeExceptionCapture(): void
    {
        $subject = new SentryClient([]);
        $sentryClientP = $this->prophesize(ClientInterface::class);
        $subject->getHub()->bindClient($sentryClientP->reveal());

        $called = false;
        EventManager::instance()->on(
            'CakeSentry.Client.beforeCapture',
            function () use (&$called) {
                $called = true;
            }
        );

        $exception = new RuntimeException('Some error');
        $subject->captureException($exception, null, ['exception' => new Exception()]);

        $this->assertTrue($called);
    }

    /**
     * Check capture dispatch before error capture
     */
    public function testCaptureDispatchBeforeErrorCapture(): void
    {
        $subject = new SentryClient([]);
        $sentryClientP = $this->prophesize(ClientInterface::class);
        $subject->getHub()->bindClient($sentryClientP->reveal());

        $called = false;
        EventManager::instance()->on(
            'CakeSentry.Client.beforeCapture',
            function () use (&$called) {
                $called = true;
            }
        );

        $phpError = new PhpError(E_USER_WARNING, 'Some error');
        $subject->captureError($phpError, null, ['exception' => new Exception()]);

        $this->assertTrue($called);
    }

    /**
     * Check capture dispatch after exception capture and receives lastEventId
     */
    public function testCaptureDispatchAfterExceptionCapture(): void
    {
        $lastEventId = EventId::generate();

        $subject = new SentryClient([]);
        $sentryClientP = $this->prophesize(ClientInterface::class);
        $sentryClientP->captureException(Argument::cetera())
            ->shouldBeCalledOnce()
            ->willReturn($lastEventId);
        $subject->getHub()->bindClient($sentryClientP->reveal());

        $called = false;
        EventManager::instance()->on(
            'CakeSentry.Client.afterCapture',
            function (Event $event) use (&$called, &$actualLastEventId) {
                $called = true;
                $actualLastEventId = $event->getData('lastEventId');
            }
        );

        $phpError = new RuntimeException('Some error');
        $subject->captureException($phpError, null, ['exception' => new Exception()]);

        $this->assertTrue($called);
        $this->assertSame($lastEventId, $actualLastEventId);
    }

    /**
     * Check capture dispatch after error capture and receives lastEventId
     */
    public function testCaptureDispatchAfterErrorCapture(): void
    {
        $lastEventId = EventId::generate();

        $subject = new SentryClient([]);
        $sentryClientP = $this->prophesize(ClientInterface::class);
        $sentryClientP->captureMessage(Argument::cetera())
            ->shouldBeCalledOnce()
            ->willReturn($lastEventId);
        $subject->getHub()->bindClient($sentryClientP->reveal());

        $called = false;
        EventManager::instance()->on(
            'CakeSentry.Client.afterCapture',
            function (Event $event) use (&$called, &$actualLastEventId) {
                $called = true;
                $actualLastEventId = $event->getData('lastEventId');
            }
        );

        $phpError = new PhpError(E_USER_WARNING, 'Some error');
        $subject->captureError($phpError, null, ['exception' => new Exception()]);

        $this->assertTrue($called);
        $this->assertSame($lastEventId, $actualLastEventId);
    }
}
