<?php namespace Primalbase\Migrate;

use Illuminate\Support\ServiceProvider;
use Primalbase\Migrate\Console\Commands\MigrateBuild;

class MigrateServiceProvider extends ServiceProvider
{
  public function boot()
  {
    $this->publishes([
      __DIR__.'/config/migrate-build.php' => config_path('migrate-build.php'),
    ]);
  }

  public function register()
  {
    $this->app->singleton('primalbase::migrate-build', function ($app) {
      return new MigrateBuild;
    });

    $this->commands([
      \Primalbase\Migrate\Console\Commands\MigrateBuild::class,
    ]);

    $this->mergeConfigFrom(
      __DIR__.'/config/migrate-build.php', 'migrate-build'
    );
  }
}