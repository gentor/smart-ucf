SmartUcf
===============

SmartUcf Online Service for Laravel

Installation
------------

Installation using composer:

```
composer require gentor/smart-ucf
```


Add the service provider in `config/app.php`:

```php
Gentor\SmartUcf\SmartUcfServiceProvider::class,
```

Add the facade alias in `config/app.php`:

```php
Gentor\SmartUcf\Facades\SmartUcf::class,
```

Configuration
-------------

Change your default settings in `app/config/smart-ucf.php`:

```php
<?php
return [
    'username' => env('UCF_USERNAME'),
    'password' => env('UCF_PASSWORD'),
    'test_mode' => env('UCF_TEST_MODE', true),
];
```

Documentation
-------------

[UniCredit Consumer Financing](https://www.unicreditbulbank.bg/bg/ucfin/)

