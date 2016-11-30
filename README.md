### install

<pre>
$ composer require primalbase/laravel5-migrate-build:dev-master
</pre>

... and append to config/app.php

<pre>
  'providers' => [
    ...
    Primalbase\Migrate\MigrateServiceProvider::class,
    ...
  ],
</pre>

### config

<pre>
$ php artisan vendor:publish --provider="Primalbase\Migrate\MigrateServiceProvider"
</pre>