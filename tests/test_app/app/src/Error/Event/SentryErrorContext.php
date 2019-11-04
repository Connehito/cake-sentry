<?php

namespace App\Error\Event;

use Cake\Event\Event;
use Cake\Event\EventListenerInterface;
use Cake\Http\ServerRequest;
use Raven_Client;


class SentryErrorContext implements EventListenerInterface
{
    public function implementedEvents()
    {
        return [
            'CakeSentry.Client.beforeCapture' => 'setContext',
        ];
    }

    public function setContext(Event $event)
    {
        /** @var ServerRequest $request */
        $request = $event->getSubject()->getRequest();
        $request->trustProxy = true;
        /** @var Raven_Client $raven */
        $raven = $event->getSubject()->getRaven();
        $raven->user_context([
            'ip_address' => $request->clientIp()
        ]);
        $raven->tags_context([
            'app_version' => $request->getHeaderLine('App-Version') ?: 1.0,
            'status' => $event->getData('exception')->getCode(),
        ]);

        $raven->setEnvironment('test_app');

        return [
            'extra' => [
                'foo' => 'bar',
                'request attributes' => $request->getAttributes(),
            ]
        ];
    }
}
