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
        app()->router->group(['namespace' => 'YiluTech\\FileCenter', 'prefix' => env('FILE_CENTER_URI_FREFIX')], function ($router) {
            $name_prefix = rtrim(env('FILE_CENTER_URI_NAME_PREFIX'), '.') . '.';
            $router->get('info', 'FileController@info')->name($name_prefix . 'move');
            
            $router->post('move', 'FileController@move')->name($name_prefix . 'move');
            $router->post('delete', 'FileController@delete')->name($name_prefix . 'delete');
            $router->post('recover', 'FileController@recover')->name($name_prefix . 'revover');
        });
    }
}
