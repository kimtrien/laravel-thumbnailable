<?php namespace Kjmtrue\Thumbnailable;

use Illuminate\Support\ServiceProvider;

class ThumbnailableServiceProvider extends ServiceProvider
{
    public function boot()
    {
        $this->publishes([
            __DIR__.'/../config/thumbnailable.php' => config_path('thumbnailable.php'),
        ]);
    }

    /**
     * Register the service provider.
     */
    public function register()
    {
        $this->mergeConfigFrom(
            __DIR__.'/../config/thumbnailable.php', 'thumbnailable'
        );
    }
}
