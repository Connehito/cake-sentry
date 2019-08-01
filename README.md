# CakePHP Sentry Plugin
CakePHP integration for Sentry.

[![Build Status](https://travis-ci.org/Connehito/cake-sentry.svg?branch=master)](https://travis-ci.org/Connehito/cake-sentry)
[![codecov](https://codecov.io/gh/connehito/cake-sentry/branch/master/graph/badge.svg)](https://codecov.io/gh/connehito/cake-sentry)
[![MIT License](http://img.shields.io/badge/license-MIT-blue.svg?style=flat)](https://github.com/Connehito/cake-sentry/blob/master/LICENSE)

## Requirements
- PHP 7.0+
- CakePHP 3.5+
- and [Sentry](https://sentry.io) account


## Installation
### With composer install.
```
composer require connehito/cake-sentry
```

## Usage

### Set config files.
Write your sentry account info.
```php
// in `config/app.php`
return [
  'Sentry' => [
    'dsn' => YOUR_SENTRY_DSN_HERE
  ]
];
```

### Loading plugin.
In Application.php
```php
public function bootstrap()
{
    parent::bootstrap();

    $this->addPlugin(\Connehito\CakeSentry\Plugin::class);
}
```

Or prior to 3.6.0, in `config/bootstrap.php`
```php
Plugin::load('Connehito/CakeSentry', ['bootstrap' => true]);
```

Or use cake command.
```
bin/cake plugin load Connehito/CakeSentry --bootstrap
```

That's all! :tada:

### Advanced Usage

#### Ignore noisy exceptions
You can filter out exceptions that make a fuss and harder to determine the issues to address(like PageNotFoundException)
Set exceptions not to log in `Error.skipLog`.  

ex)
```php
// in `config/app.php`
'Error' => [
    'skipLog' => [
        NotFoundException::class,
        MissingRouteException::class,
        MissingControllerException::class,
    ],
]
```

ref: CakePHP Cookbook  
https://book.cakephp.org/3.0/en/development/errors.html#error-exception-configuration

#### Send more context
Client dispatch `CakeSentry.Client.beforeCapture` event before sending error to sentry.  
You can set context with EventListener.Calling Raven_Client's API or returning values, error context will be sent. The Returned values will be passed to `Raven_Client::captureMessage()` 3rd arguments(Additional attributes to pass with this event).

Now, cake-sentry supports to get `Request` instance in implemented event via `$event->getSubject()->getRequest()`.

ex)
```php
use Cake\Event\Event;
use Cake\Event\EventListenerInterface;

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
        $request = $event->getSubject()->getRequest();
        $request->trustProxy = true;
        $raven = $event->getSubject()->getRaven();
        $raven->user_context([
                'ip_address' => $request->clientIp()
            ]);
        $raven->tags_context([
            'app_version' => $request->getHeaderLine('App-Version') ?: 1.0,
        ]);

        return [
            'extra' => [
                'foo' => 'bar',
            ]
        ];
    }
}
```

And in `config/bootstrap.php`
```php
EventManager::instance()->on(new SentryErrorContext());
```

ref: Sentry official PHP SDK document.  
https://docs.sentry.io/clients/php/

#### Register send callback
The plugin allows you to inject `send_callback` option to Raven client.  
It will be called in after client send  data to Sentry.  
See also [offcial doc](https://docs.sentry.io/clients/php/config/).

ex)
```php
// In app.php, setup callback closure for receiving event id from Raven.
// This sample enables you to get "Event ID" via `$Session` in your controller.
// cf) https://docs.sentry.io/learn/user-feedback/
'Sentry' => [
    'dsn' => env('SENTRY_DSN'),
    'options' => [
        'send_callback' => function ($data) {
            $request = \Cake\Http\ServerRequestFactory::fromGlobals();
            $session = $request->getSession();
            $session->write('last_event_id', $data['event_id']);
        }
    ],
],
```


## Contributing
Pull requests and feedback are very welcome :)  
on GitHub at https://github.com/connehito/cake-sentry .

## License
The plugin is available as open source under the terms of the [MIT License](https://github.com/Connehito/cake-sentry/blob/master/LICENSE).
