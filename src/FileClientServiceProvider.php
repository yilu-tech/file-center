<?php

namespace YiluTech\FileCenter;

use Illuminate\Support\ServiceProvider;
use YiluTech\FileCenter\Facade\FileCenterClientFacade;

class FileClientServiceProvider extends ServiceProvider
{
    /**
     * Define your route model bindings, pattern filters, etc.
     *
     * @return void
     */
    public function boot()
    {
        if (!class_exists('FileCenterClient')) {
            class_alias(FileCenterClientFacade::class, 'FileCenterClient');
        }

        app()->bind('FileCenterClient', function ($app) {
            return new FileClientManager();
        });
    }
}
