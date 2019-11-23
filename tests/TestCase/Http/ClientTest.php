<?php

namespace Connehito\CakeSentry\Test\TestCase\Http;

use Cake\Core\Configure;
use Cake\Event\Event;
use Cake\Event\EventManager;
use Cake\Http\Exception\NotFoundException;
use Cake\TestSuite\TestCase;
use Connehito\CakeSentry\Http\Client;
use Prophecy\Argument;
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
     * {@inheritdoc}
     */
    public function setUp()
    {
        parent::setUp();

        Configure::write('Sentry.dsn', 'https://user:pass@example.com/yourproject');
    }

    /**
     * Check constructor sets Hub instance
     *
     * @return void
     */
    public function testSetupClient()
    {
        $subject = new Client([]);

        $this->assertInstanceOf(
            Hub::class,
            $subject->getHub()
        );
    }

    /**
     * Check constructor throws exception unless dsn is given
     *
     * @return void
     */
    public function testSetupClientNotHasDsn()
    {
        Configure::delete('Sentry.dsn');
        $this->expectException(RuntimeException::class);

        new Client([]);
    }

    /**
     * Check constructor passes options to sentry client
     *
     * @return void
     */
    public function testSetupClientSetOptions()
    {
        Configure::write('Sentry.excluded_exceptions', [NotFoundException::class]);

        $subject = new Client([]);
        $options = $subject->getHub()->getClient()->getOptions();

        $this->assertSame(
            [NotFoundException::class],
            $options->getExcludedExceptions()
        );
    }

    /**
     * Check constructor fill before_send option
     *
     * @return void
     */
    public function testSetupClientSetSendCallback()
    {
        $subject = new Client([]);
        $actual = $subject
            ->getHub()
            ->getClient()
            ->getOptions()
            ->getBeforeSendCallback();

        $this->assertInstanceOf(
            \Closure::class,
            $actual
        );
    }

    /**
     * Check constructor dispatch event Client.afterSetup
     *
     * @return void
     */
    public function testSetupClientDispatchAfterSetup()
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
     * test capture exception
     *
     * @return void
     */
    public function testCaptureException()
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
     * test capture error
     *
     * @return void
     */
    public function testCaptureError()
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
    }

    /**
     * test capture error fill breadcrumbs
     *
     * @return void
     */
    public function testCaptureErrorBuildBreadcrumbs()
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

        $subject->capture(
            'warning',
            'some error',
            []
        );
    }

    /**
     * Check capture dispatch beforeCapture
     *
     * @return void
     */
    public function testCaptureDispatchBeforeCapture()
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

        $subject->capture(
            'info',
            'some error',
            ['exception' => new \Exception()]
        );

        $this->assertTrue($called);
    }

    /**
     * Check capture dispatch afterCapture and receives lastEventId
     *
     * @return void
     */
    public function testCaptureDispatchAfterCapture()
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

        $subject->capture(
            'info',
            'some error',
            ['exception' => new \Exception()]
        );

        $this->assertTrue($called);
        $this->assertSame($lastEventId, $actualLastEventId);
    }
}
