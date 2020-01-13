<?php

namespace YiluTech\FileCenter;

use Illuminate\Support\ServiceProvider;
use YiluTech\FileCenter\Commands\ClearTempCommand;

class FileServiceProvider extends ServiceProvider
{
    /**
     * Define your route model bindings, pattern filters, etc.
     *
     * @return void
     */
    public function boot()
    {

    }

    public function register()
    {
        $this->registerRoute();

        if ($this->app->runningInConsole()) {
            $this->commands([
                ClearTempCommand::class
            ]);
        }
    }

    protected function registerRoute()
    {
        app()->router->group(['namespace' => 'YiluTech\\FileCenter\\Http\\Controller', 'prefix' => env('FILE_CENTER_URI_PREFIX')], function ($router) {
            $name_prefix = rtrim(env('FILE_CENTER_URI_NAME_PREFIX'), '.') . '.';

            $router->get('info', ['uses' => 'FileController@info', 'as' => $name_prefix . 'info']);
            $router->post('move', ['uses' => 'FileController@move', 'as' => $name_prefix . 'move']);
            $router->post('delete', ['uses' => 'FileController@delete', 'as' => $name_prefix . 'delete']);
            $router->post('recover', ['uses' => 'FileController@recover', 'as' => $name_prefix . 'recover']);
        });
    }
}
