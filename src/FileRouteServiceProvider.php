<?php

namespace YiluTech\FileCenter;

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
        app()->router->group(['namespace' => 'YiluTech\\FileCenter', 'prefix' => env('FILE_CENTER_URI_PREFIX')], function ($router) {
            $name_prefix = rtrim(env('FILE_CENTER_URI_NAME_PREFIX'), '.') . '.';

            $router->get('info', ['uses' => 'FileController@info', 'as' => $name_prefix . 'info']);
            $router->post('move', ['uses' => 'FileController@move', 'as' => $name_prefix . 'move']);
            $router->post('delete', ['uses' => 'FileController@delete', 'as' => $name_prefix . 'delete']);
            $router->post('recover', ['uses' => 'FileController@recover', 'as' => $name_prefix . 'recover']);
        });
    }
}
