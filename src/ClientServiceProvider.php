<?php

namespace YiluTech\FileCenter;

use Illuminate\Support\ServiceProvider;

class ClientServiceProvider extends ServiceProvider
{
    /**
     * Define your route model bindings, pattern filters, etc.
     *
     * @return void
     */
    public function boot()
    {
        class_alias(FileCenterClientFacade::class, 'FileCenterClient');

        app()->bind('FileCenterClient', function ($app) {
            return new ClientManage();
        });
    }
}
