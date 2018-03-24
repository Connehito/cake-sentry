# CakePHP Sentry Plugin
CakePHP integration for Sentry.

[![Build Status](https://travis-ci.org/Connehito/cake-sentry.svg?branch=master)](https://travis-ci.org/Connehito/cake-sentry)
[![codecov](https://codecov.io/gh/connehito/cake-sentry/branch/master/graph/badge.svg)](https://codecov.io/gh/connehito/cake-sentry)

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
in `config/bootstrap.php`
```php
Plugin::load('Connehito/CakeSentry', ['bootstrap' => true]);
```

```php
// in `config/app.php`
  'Sentry' => [
    'dsn' => YOUR_SENTRY_DSN_HERE
  ]
];
```

or use cake command.
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
'Error' => [$
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

## Contributing
Pull requests and feedback are very welcome :)  
on GitHub at https://github.com/connehito/cake-sentry .

## License
The plugin is available as open source under the terms of the [MIT License](http://opensource.org/licenses/MIT).
