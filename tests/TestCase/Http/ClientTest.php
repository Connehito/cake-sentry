<?php
declare(strict_types=1);

namespace Connehito\CakeSentry\Test\TestCase\Http;

use Cake\Core\Configure;
use Cake\Event\Event;
use Cake\Event\EventManager;
use Cake\Http\Exception\NotFoundException;
use Cake\TestSuite\TestCase;
use Closure;
use Connehito\CakeSentry\Http\Client;
use Exception;
use Prophecy\Argument;
use Prophecy\Prophecy\MethodProphecy;
use ReflectionProperty;
use RuntimeException;
use Sentry\ClientInterface;
use Sentry\Options;
use Sentry\Severity;
use Sentry\State\Hub;
use Sentry\State\Scope;

final class ClientTest extends TestCase
{
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
        Configure::write('Sentry.excluded_exceptions', [NotFoundException::class]);
        $beforeSend = (new class
        {
            public function __invoke()
            {
                return true;
            }
        });
        Configure::write('Sentry.before_send', $beforeSend);

        $subject = new Client([]);
        $options = $subject->getHub()->getClient()->getOptions();

        $this->assertSame(
            [NotFoundException::class],
            $options->getExcludedExceptions()
        );
        $this->assertSame(
            get_class($beforeSend),
            get_class($options->getBeforeSendCallback())
        );
    }

    /**
     * Check constructor fill before_send option
     */
    public function testSetupClientSetSendCallback(): void
    {
        $subject = new Client([]);
        $actual = $subject
            ->getHub()
            ->getClient()
            ->getOptions()
            ->getBeforeSendCallback();

        $this->assertInstanceOf(Closure::class, $actual);
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
            ->captureException($exception, Argument::type(Scope::class))
            ->shouldHaveBeenCalled();
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
        $sentryClientP->getOptions()->shouldBeCalled()->willReturn(new Options());
        $sentryClientP
            ->captureMessage(
                'some error',
                Severity::fromError(E_WARNING),
                Argument::type(Scope::class)
            )
            ->shouldBeCalled();
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
     *
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
     * Test capture error fill breadcrumbs
     */
    public function testCaptureErrorBuildBreadcrumbs(): void
    {
        $stacks = debug_backtrace(DEBUG_BACKTRACE_PROVIDE_OBJECT);
        $expect = [
            'file' => $stacks[2]['file'],
            'line' => $stacks[2]['line'],
        ];

        $subject = new Client([]);
        $sentryClientP = $this->prophesize(ClientInterface::class);
        $sentryClientP->getOptions()->shouldBeCalled()->willReturn(new Options());
        $sentryClientP
            ->captureMessage(
                Argument::any(),
                Argument::any(),
                Argument::that(function ($a) use (&$expect) {
                    $breadcrumbsProp = new ReflectionProperty($a, 'breadcrumbs');
                    $breadcrumbsProp->setAccessible(true);
                    $breadcrumbs = $breadcrumbsProp->getValue($a);
                    $metadata = $breadcrumbs[0]->getMetaData();
                    foreach ($expect as $field => $val) {
                        if ($metadata[$field] !== $val) {
                            return false;
                        }
                    }

                    return true;
                })
            )
            ->shouldBeCalled();
        $subject->getHub()->bindClient($sentryClientP->reveal());

        $subject->capture('warning', 'some error', []);
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
        $lastEventId = 'aaa';

        $subject = new Client([]);
        $sentryClientP = $this->prophesize(ClientInterface::class);
        $sentryClientP->captureException(Argument::cetera())
            ->shouldBeCalled()
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
