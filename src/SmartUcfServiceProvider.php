<?php

namespace Gentor\SmartUcf;


use Illuminate\Support\ServiceProvider;
use Gentor\SmartUcf\Service\SmartUcf;

/**
 * Class SmartUcfServiceProvider
 *
 * @package Gentor\SmartUcf
 */
class SmartUcfServiceProvider extends ServiceProvider
{

    /**
     * Indicates if loading of the provider is deferred.
     *
     * @var bool
     */
    protected $defer = true;

    /**
     * Register any application services.
     *
     * @return void
     */
    public function register()
    {
        $this->app->bind('smart-ucf', function ($app) {
            return new SmartUcf($app['config']['smart-ucf']);
        });
    }

    /**
     * Get the services provided by the provider.
     *
     * @return array
     */
    public function provides()
    {
        return ['smart-ucf'];
    }

}