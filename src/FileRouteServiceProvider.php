<?php

namespace YiluTech\FileUpload;

use Illuminate\Support\ServiceProvider;

class FileRouteServiceProvider extends ServiceProvider
{
    /**
     * Define your route model bindings, pattern filters, etc.
     *
     * @return void
     */
    public function boot()
    {
        app()->router->group(['namespace'=> 'YiluTech\\FileUpload'], function ($router) {
            $router->post('move', 'FileController@move');
            $router->post('delete', 'FileController@delete');
            $router->post('recover', 'FileController@recover');
        });
    }
}
