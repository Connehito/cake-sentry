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
if (Configure::read('Sentry.enabled')) {
  Plugin::load('Connehito/CakeSentry', ['bootstrap' => true]);
}
```

in `config/app.php`
```php
return [
  'Sentry' => [
    'dsn' => YOUR_SENTRY_DSN_HERE
  ]
];
```

### Advanced Usage
Client dispatch `CakeSentry.Client.beforeCapture` event before sending error to sentry, you can set context with EventListener.

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
        $event->getSubject()->getRaven()
            ->user_context([
                'ip_address' => $request->clientIp()
            ]);
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
