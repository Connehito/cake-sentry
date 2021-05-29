<?php
declare(strict_types=1);

namespace Connehito\CakeSentry\Test\TestCase\Http;

use Cake\Core\Configure;
use Cake\Event\Event;
use Cake\Event\EventManager;
use Cake\TestSuite\TestCase;
use Connehito\CakeSentry\Http\Client;
use Exception;
use Prophecy\Argument;
use Prophecy\PhpUnit\ProphecyTrait;
use Prophecy\Prophecy\MethodProphecy; // phpcs:ignore SlevomatCodingStandard.Namespaces.UnusedUses.UnusedUse
use RuntimeException;
use Sentry\ClientInterface;
use Sentry\EventHint;
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
        $subject = new Client([]);

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
        $subject = new Client([]);

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

        new Client([]);
    }

    /**
     * Check constructor passes options to sentry client
     */
    public function testSetupClientSetOptions(): void
    {
        Configure::write('Sentry.server_name', 'test-server');

        $subject = new Client([]);
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

        $subject = new Client([]);
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

        new Client([]);

        $this->assertTrue($called);
    }

    /**
     * Test capture exception
     */
    public function testCaptureException(): void
    {
        $subject = new Client([]);
        $sentryClientP = $this->prophesize(ClientInterface::class);
        $subject->getHub()->bindClient($sentryClientP->reveal());

        $exception = new RuntimeException('something wrong.');
        $subject->capture(
            'error',
            'some exception',
            ['exception' => $exception]
        );

        $sentryClientP
            ->captureException($exception, Argument::type(Scope::class), null)
            ->shouldHaveBeenCalledOnce();
    }

    /**
     * Test capture error
     *
     * @return array // FIXME: In fact array<string,MethodProphecy[]>, but getMethodProphecies declare as MethodProphecy[]
     */
    public function testCaptureError(): array
    {
        $subject = new Client([]);
        $sentryClientP = $this->prophesize(ClientInterface::class);
        $sentryClientP->getOptions()->willReturn(new Options());
        $sentryClientP
            ->captureMessage(
                'some error',
                Severity::fromError(E_WARNING),
                Argument::type(Scope::class),
                Argument::type(EventHint::class)
            )
            ->shouldBeCalledOnce()
            ->willReturn(EventId::generate());
            // NOTE:
            // This itself is not of interest for the test case,
            // but for ProphecyMock's technical reasons, the return-value needs to be a real `EvnetId`

        $subject->getHub()->bindClient($sentryClientP->reveal());

        $subject->capture(
            'warning',
            'some error',
            []
        );

        return $sentryClientP->getMethodProphecies();
    }

    /**
     * Test capture error compatible with  the error-level is specified by int or string
     *
     * @depends testCaptureError
     * @param array&array<string,MethodProphecy[]> $mockMethodList
     */
    public function testCaptureErrorWithErrorLevelInteger(array $mockMethodList): void
    {
        // Rebuild ObjectProphecy in the same context with testCaptureError.
        $sentryClientP = $this->prophesize(ClientInterface::class);
        foreach ($mockMethodList as $mockMethod) {
            $sentryClientP->addMethodProphecy($mockMethod[0]);
        }

        $subject = new Client([]);
        $subject->getHub()->bindClient($sentryClientP->reveal());

        $subject->capture(E_USER_WARNING, 'some error', []);
    }

    /**
     * Test capture error fill with injected breadcrumbs
     */
    public function testCaptureErrorBuildBreadcrumbs(): void
    {
        $stackTrace = debug_backtrace(DEBUG_BACKTRACE_PROVIDE_OBJECT);

        $subject = new Client([]);
        $sentryClientP = $this->prophesize(ClientInterface::class);
        $sentryClientP->getOptions()->willReturn(new Options());
        $sentryClientP
            ->captureMessage(
                Argument::any(),
                Argument::any(),
                Argument::any(),
                Argument::that(function (EventHint $actualHint) use ($stackTrace) {
                    $frames = $actualHint->stacktrace->getFrames();
                    $actual = array_pop($frames);
                    if ($actual->getFile() !== $stackTrace[0]['file']) {
                        $this->fail('first frame does not match with "file"');
                    }
                    if ($actual->getLine() !== $stackTrace[0]['line']) {
                        $this->fail('first frame does not match with "line"');
                    }

                    return true;
                })
            )
            ->shouldBeCalledOnce()
            ->willReturn(EventId::generate());
            // NOTE:
            // This itself is not of interest for the test case,
            // but for ProphecyMock's technical reasons, the return-value needs to be a real `EvnetId`

        $subject->getHub()->bindClient($sentryClientP->reveal());

        $subject->capture('warning', 'some error', compact('stackTrace'));
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
        $subject = new Client([]);

        $context = ['this is' => 'additional'];
        $subject->capture('warning', 'some error', $context);

        $this->assertSame($context, $actualEvent->getExtra());
    }

    /**
     * Check capture dispatch beforeCapture
     */
    public function testCaptureDispatchBeforeCapture(): void
    {
        $subject = new Client([]);
        $sentryClientP = $this->prophesize(ClientInterface::class);
        $subject->getHub()->bindClient($sentryClientP->reveal());

        $called = false;
        EventManager::instance()->on(
            'CakeSentry.Client.beforeCapture',
            function () use (&$called) {
                $called = true;
            }
        );

        $subject->capture('info', 'some error', ['exception' => new Exception()]);

        $this->assertTrue($called);
    }

    /**
     * Check capture dispatch afterCapture and receives lastEventId
     */
    public function testCaptureDispatchAfterCapture(): void
    {
        $lastEventId = EventId::generate();

        $subject = new Client([]);
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

        $subject->capture('info', 'some error', ['exception' => new Exception()]);

        $this->assertTrue($called);
        $this->assertSame($lastEventId, $actualLastEventId);
    }
}
